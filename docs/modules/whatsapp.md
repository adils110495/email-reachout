[← Docs index](../README.md)

# Module: WhatsApp

**URL:** `/whatsapp` (messages) · `/settings/whatsapp` (connection)
**Module key:** `whatsapp` (`User::MODULES`)

GMB Leads aur Deals ke paas phone number hota hai, email nahi — WhatsApp unke liye sabse
seedha channel hai. Ye module wahi deta hai: message bhejo, aur dekho delivered / read /
replied hua ya nahi.

---

## Do tareeke — ek setup ke saath, ek bina setup ke

| | **Cloud API** (Meta ka official) | **Open in WhatsApp** (wa.me) |
|---|---|---|
| Setup | Meta app + token (Settings › WhatsApp) | Kuch nahi |
| Kahan se jata hai | Server se, aapke business number se | Aapke apne WhatsApp (app / Web) se |
| Delivered / Read / Replied | ✅ webhook se | ❌ — sirf "Opened in WhatsApp" log hota hai |
| Pehla message | **Sirf approved template** | Kuch bhi |

> **Unofficial libraries (whatsapp-web.js, Baileys) jaan-boojh kar use nahi ki** — WhatsApp
> ke terms ke khilaf hain aur cold outreach par number ban ho jata hai.

### Meta ka 24-ghante wala rule — sabse important

```
Contact ne kabhi reply nahi kiya   →  sirf APPROVED TEMPLATE ja sakta hai
Contact ne pichhle 24 ghante me     →  free text bhi ja sakta hai
likha hai
```

Isliye compose modal me do mode hain: **Template** (default) aur **Text message**. 24
ghante ke bahar free text bhejo to Meta error `131047` deta hai — modal wahi error saaf
bhasha me dikha deta hai.

---

## Kahan se bhejte hain

| Jagah | Kaise |
|---|---|
| GMB Leads | Row ka `⋮` menu › **Send WhatsApp** (sirf jahan phone hai) |
| Deals | Row ka `⋮` menu › **Send WhatsApp** |
| WhatsApp page | **New Message** (koi bhi number), ya kisi row par **Message again** |

GMB / Deals table me phone ke aage `🟢 2` jaisa badge = is contact ko kitne WhatsApp gaye
(`withCount('whatsappMessages')`, ek hi query).

### Compose modal

```
┌──────────────────────────────────────────────┐
│ Name            WhatsApp number *            │
│ [Template] [Text message]                    │
│ Approved template [▼ hello_client (en)] [↻]  │
│ {{1}} [Acme Bakery]   {{2}} [...]            │
│ ┌ preview — jaisa contact padhega ┐          │
│ └─────────────────────────────────┘          │
│ [Open in WhatsApp]          [Cancel] [Send]  │
└──────────────────────────────────────────────┘
```

- Templates Meta se aate hain (`GET /{waba_id}/message_templates`, sirf `APPROVED`), 10 min cache. ↻ button cache todta hai.
- Pehla variable apne aap contact ke naam se bhar jata hai.
- Media header, header variable, ya variable wala button jis template me hai wo dropdown me **disabled** dikhta hai — modal un fields ke liye input nahi leta, Meta reject kar deta.
- Cloud API connected nahi → template pane chhup jata hai, sirf text + **Open in WhatsApp**.
- **Send** synchronous hai (queue nahi) — taaki Meta ka error turant modal me dikhe.

---

## Bulk send — ek template, bahut saare contacts

GMB Leads aur Deals ke card header me do button:

| Button | Kisko jata hai |
|---|---|
| **WhatsApp N selected** | Checkbox wali rows (button tabhi dikhta hai jab kuch select ho) |
| **Bulk WhatsApp** | Current filters se match hone wale **saare** contacts — sirf current page nahi |

"All matching" ke liye controller **wahi** `filtered()` query use karta hai jo list banati hai
(`GmbLeadController` / `CashLeadController`), aur JS current URL ka query string bhejta hai —
isliye jo list me dikh raha hai, bilkul wahi rows jaati hain.

### Rules

- **Sirf Cloud API + approved template.** Free text bulk me nahi — 24 ghante ke bahar Meta waise bhi reject karta.
- Template value me `{name}` likho → har contact ka apna naam (`WhatsappBulkService::personalise()`). Pehla variable apne aap `{name}` se bhar jata hai.
- **Gap between messages** (2 / 5 / 10 / 30 / 60 sec, default 5) — ek saath 200 same messages = number report hota hai, quality rating girti hai.
- **Skip contacts already messaged** (default on) — jis number ko pehle message gaya (failed / cancelled chhod kar) wo skip.
- Ek hi number do baar list me ho → ek hi message. Bina usable number wale skip.
- Max `WHATSAPP_BULK_MAX` (default **250**) contacts per bulk — Meta naye number ko din me 250 conversation shuru karne deta hai.
- Saare contacts **ek hi user** ke hone chahiye (message owner ke number se jata hai). Super Admin ko alag-alag users ke contacts ek saath bhejne ho to select karke bhejna hoga.

### Kaise chalta hai

```
Queue messages
   │
   ▼
WhatsappBulkService::queue()          (runAs owner)
   ├─ number normalise, duplicate / already-messaged skip
   ├─ har contact: whatsapp_messages row  status = queued, batch_id, params
   └─ har row: SendWhatsappMessageJob  delay = i × gap
                    │
                    ▼  (queue worker, apni baari par)
              claim: queued → sending   (UPDATE … WHERE status = queued)
                    ├─ success → sent (wamid)  → webhook se delivered / read / replied
                    └─ error   → failed (Meta ka reason)
              last message → 🔔 "WhatsApp bulk send finished — X sent, Y failed"
```

- Job **`$tries = 1`** — timeout ke baad retry se same insaan ko do baar message ja sakta tha.
- Claim wala conditional UPDATE: cancelled message ya kisi aur worker ka uthaya hua message skip.
- Queue worker chalu hona chahiye (`queue` container, `default` queue — pehle se consume hoti hai).

### Progress aur Stop

Send ke baad toast me **View progress** → `/whatsapp?batch=<uuid>`:

- Progress bar + chips: Waiting / Sent / Delivered / Read / Replied / Failed / Cancelled
- Chalte hue bulk ko page har 5 sec poll karta hai (`whatsapp.batch-status`) aur list swap karta hai — khatam hone par, 3 fail par, ya tab hidden ho to ruk jata hai.
- **Stop** → jo abhi `queued` hain wo `cancelled`; unke jobs apni baari par skip ho jaate hain.
- WhatsApp list me bulk wale message par **Bulk** badge → us bulk ka view.

---

## Phone number normalise

`WhatsappService::normalizePhone()` — WhatsApp ko `919876543210` chahiye (country code
ke saath digits, `+` nahi).

| Input | Default country code `91` | Result |
|---|---|---|
| `+91 98765-43210` | — | `919876543210` |
| `0091 98765 43210` | — | `919876543210` |
| `098765 43210` | 91 | `919876543210` (trunk `0` hata) |
| `98765 43210` | 91 | `919876543210` |
| `919876543210` | 91 | `919876543210` (pehle se code hai) |
| `98765 43210` | *(khaali)* | ❌ null — country pata nahi |

Default country code: user ka (Settings › WhatsApp) → warna `.env` ka
`WHATSAPP_DEFAULT_COUNTRY_CODE`.

---

## Status tracking — webhook

```
(bulk: queued → sending →)
Send  ──→  status = sent  (wamid save)
              │
Meta webhook  ├──→ delivered   (delivered_at)
              ├──→ read        (read_at, delivered_at bhi)
              ├──→ failed      (error me Meta ka reason)
              └──→ incoming message from same number
                        → replied_at + reply_body  +  🔔 notification
```

- Status sirf **aage** badhta hai — late aaya "delivered" kabhi "read" ko wapas nahi karta (`WhatsappMessage::applyStatus()`).
- Reply us number ko gaye **latest** message par lagta hai; sirf **pehla** reply record + notify hota hai (chat feed nahi banana).
- Phone se ya kisi aur tool se bheje messages ka status ignore — wo humare log me hain hi nahi.

### Webhook set karna — Settings › WhatsApp › Webhook card

| Field / button | Kaam |
|---|---|
| **Public URL of this app** | Jahan Meta app tak pahunch sake — live domain, ya localhost par testing ke liye **ngrok / cloudflared** tunnel (`https://abcd.ngrok-free.app`). Callback URL isi se banta hai (`WhatsappSetting::callbackUrl()`). Khaali = jis address par page khula hai. Sirf `https://`, max 140 chars (Meta 200 tak leta hai, path ~60 ka hai) |
| **Verify token** | Apna chuno (8–64, `A-Z a-z 0-9 . _ ~ -`) ya generated wala rakho. Khaali = purana hi rahe |
| **Set in Meta** | `POST /{WABA_ID}/subscribed_apps` + `override_callback_uri` + `verify_token` — Meta ka **webhook override**. Dashboard me paste karne ki zaroorat nahi. Meta usi waqt URL ko `hub.challenge` se check karta hai, isliye app pehle se us URL par reachable honi chahiye |
| **Check** | `GET /{WABA_ID}/subscribed_apps` — Meta abhi kis URL par bhej raha hai; humara URL hai to "Connected" |

- Card **pehli visit par hi** ready hai — page khulte hi user ki settings row (webhook key + verify token) ban jaati hai (`ownSetting()`, `createOrFirst`), kyunki Meta setup me Callback URL credentials se pehle maangta hai. Bina token wali row kuch nahi bhejti (`canSend()` false).
- Override **WABA level** par hai — isliye do users ek hi Meta app share karein to bhi har ek ke webhooks apne-apne URL par jaate hain (pehle wali limitation isse hat jaati hai).
- Set in Meta se pehle **Business Account ID, token aur App Secret** saved hone chahiye.
- `messages` webhook field App Dashboard (WhatsApp › Configuration) me ek baar subscribe karna padta hai — override sirf URL badalta hai.
- URL ya token badla to **Set in Meta** dobara dabao.
- Errors: `2200` = Meta URL tak nahi pahunch paya (https / public nahi), `2201` = verify token match nahi hua.

### Webhook security

| Cheez | Kaam |
|---|---|
| URL `/webhooks/whatsapp/{key}` | `key` = har user ka random 40-char `webhook_key` → kis user ka webhook |
| `GET` (verify) | Meta `hub.verify_token` bhejta hai → user ke `verify_token` se `hash_equals` |
| `POST` (events) | `X-Hub-Signature-256` = App Secret se HMAC → mismatch = **403** |
| App Secret khaali | **Har POST 403** — bina signature koi bhi fake "read"/reply bhej sakta tha |
| CSRF | `webhooks/*` exempt (`bootstrap/app.php`) — Meta ke paas token nahi hota |
| Processing error | Log hota hai, phir bhi `200` — warna Meta din bhar retry karta |

Processing `runAs(owner)` me hoti hai — webhook sirf usi user ke messages chhoo sakta hai.

> **Ek Meta app, kai users:** App Dashboard me ek hi callback URL hota hai. Isliye har user
> **Set in Meta** se apne WABA par override lagaye — tab har ek ke webhooks apne URL par
> aayenge. Sirf dashboard me paste karoge to webhook ek hi user ka chalega.

---

## Access control

| Cheez | Kaise |
|---|---|
| Module | `module:whatsapp` route middleware — page, send, templates, settings sab |
| GMB lead / deal ko message | Uske module ki access bhi chahiye (`gmb_leads` / `deals`) |
| Record | Owner scope — kisi aur ka `id` bhejo to 404 |
| Kis account se jata hai | Record ke **owner** ka number/token (`runAs`) — Super Admin bheje tab bhi |
| Settings | `mine()` — Super Admin bhi sirf apna token dekhta/badalta hai |
| Log | `whatsapp_messages` owned — Super Admin sab dekhta hai, **User** filter ke saath |
| Rate limit | `send` / `open-chat` par `throttle:60,1` |

Naye users (`modules = null`) ko module apne aap milta hai. Jin users ki modules list
**pehle se set** hai unhe Super Admin Settings › Users me **WhatsApp** tick kare.

---

## Setup (Meta side)

1. [developers.facebook.com](https://developers.facebook.com/apps) par **Business** app → **WhatsApp** product add karo.
2. WhatsApp › API Setup: business number add karo, **Phone Number ID** aur **WhatsApp Business Account ID** copy karo.
3. Business Settings › System Users: **permanent token** (`whatsapp_business_messaging`, `whatsapp_business_management`). API Setup wala temporary token 24 ghante me expire hota hai.
4. App settings › Basic: **App Secret**.
5. App me Settings › WhatsApp: sab bharo → **Save** → **Test Connection**.
6. Settings › WhatsApp › Webhook card: **Public URL** daalo (live domain ya ngrok) → Save → **Set in Meta**. Ya Meta › WhatsApp › Configuration › Webhook me Callback URL + Verify token khud paste karo. Dono case me **`messages`** field subscribe karo.
7. WhatsApp Manager › Message templates: template banao, approve hone do.

> Test number (development mode) se sirf un numbers par bhej sakte ho jo API Setup me
> "recipient" list me add hain — warna error `131030`.

---

## Files

```
app/Http/Controllers/WhatsappController.php          page, export, templates, send, open-chat
app/Http/Controllers/WhatsappSettingController.php   connection form + test
app/Http/Controllers/WhatsappWebhookController.php   Meta webhook (public)
app/Http/Controllers/Concerns/QueuesWhatsappBulk.php bulk endpoint (GMB + Deals controllers use it)
app/Services/WhatsappService.php                     number, Graph API, templates, webhook
app/Services/WhatsappBulkService.php                 bulk: queue, cancel, progress
app/Jobs/SendWhatsappMessageJob.php                  one queued bulk message
app/Models/WhatsappSetting.php                       per-user connection
app/Models/WhatsappMessage.php                       log + statuses + badges

resources/views/whatsapp/index.blade.php             messages page (+ bulk progress poller)
resources/views/whatsapp/_table.blade.php            AJAX partial (+ bulk progress panel)
resources/views/whatsapp/_compose.blade.php          shared modal, single + bulk (GMB, Deals, WhatsApp)
resources/views/whatsapp/_bulk-bar.blade.php         bulk buttons on GMB / Deals
resources/views/whatsapp-settings/index.blade.php    settings page
```

## Routes

```php
GET   /whatsapp                     whatsapp.index
GET   /whatsapp/export              whatsapp.export
GET   /whatsapp/templates           whatsapp.templates      ?source=gmb|deal|direct&id=
POST  /whatsapp/send                whatsapp.send
POST  /whatsapp/open-chat           whatsapp.open-chat
GET   /whatsapp/batches/{uuid}      whatsapp.batch-status   (JSON, poller)
POST  /whatsapp/batches/{uuid}/cancel  whatsapp.batch-cancel
POST  /gmb-leads/whatsapp           gmb-leads.whatsapp      bulk  (module:gmb_leads + whatsapp)
POST  /cash-leads/whatsapp          cash-leads.whatsapp     bulk  (module:deals + whatsapp)
GET   /settings/whatsapp            whatsapp-settings.index
PUT   /settings/whatsapp            whatsapp-settings.update
POST  /settings/whatsapp/test       whatsapp-settings.test
PUT   /settings/whatsapp/webhook    whatsapp-settings.webhook           public URL + verify token
POST  /settings/whatsapp/webhook/register  whatsapp-settings.webhook-register   Set in Meta
GET   /settings/whatsapp/webhook/status    whatsapp-settings.webhook-status     Check

// public — Meta
GET   /webhooks/whatsapp/{key}      webhooks.whatsapp.verify
POST  /webhooks/whatsapp/{key}      webhooks.whatsapp
```

## Common errors

| Code | Matlab | Fix |
|---|---|---|
| `190` | Token invalid / expired | Permanent System User token daalo |
| `131030` | Test mode — number allowed list me nahi | API Setup me recipient add karo, ya live number |
| `131047` | 24 ghante bahar free text | Template bhejo |
| `132001` | Template us language me nahi / approved nahi | WhatsApp Manager check karo |
| `132000` | Variables ki ginti galat | Template ke saare `{{n}}` bharo |
| `131026` | Deliver nahi hua | Number shayad WhatsApp par nahi |
