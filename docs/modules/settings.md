[← Docs index](../README.md)

# Module: Settings

**URL:** `/settings/*`
Chaar simple CRUD screens jo baaki modules ko data dete hain.

> Ye module **pehle se maujood tha**. Naye modules iske data ko use karte hain.

---

## Chaar sub-modules

| Sub-module | URL | Table | Kaun use karta hai |
|---|---|---|---|
| Email Templates | `/settings/templates` | `email_templates` | Leads (compose modal) |
| Platforms | `/settings/platforms` | `platforms` | Leads, Finder (filter) |
| Categories | `/settings/categories` | `categories` | Leads, Finder, **Bulks** |
| Addresses | `/settings/addresses` | `addresses` | Leads (email footer) |
| WhatsApp Settings | `/settings/whatsapp` | `whatsapp_settings` | WhatsApp — [modules/whatsapp.md](whatsapp.md) |
| SerpAPI | `/settings/serpapi` | `serpapi_settings` | Leads › Find Leads, GMB search, AI email research — neeche dekho |

**Files:**
```
app/Http/Controllers/EmailTemplateController.php
app/Http/Controllers/PlatformController.php
app/Http/Controllers/CategoryController.php
app/Http/Controllers/AddressController.php

resources/views/templates/   (index, create, edit, _form, _table)
resources/views/platforms/   (index, _table)
resources/views/categories/  (index, _table)
resources/views/addresses/   (index, _table)
```

---

## Common pattern

Platforms / Categories / Addresses — teeno ka structure **bilkul same** hai:

```
┌─────────────────────────────────────────────────────┐
│  Title            [Export CSV]  [+ Add]             │
├─────────────────────────────────────────────────────┤
│  Search (live)    Status filter    [Clear]          │
├─────────────────────────────────────────────────────┤
│  Table  →  Name · Status · Action (Edit/Delete)     │
└─────────────────────────────────────────────────────┘
+ Add modal
+ Edit modal (delegated JS se fill hota hai)
```

Shared plumbing:
- `data-ajax-root` → `ajax-filters.js` (filter, swap)
- `ExportsCsv` trait → CSV download
- `RedirectsBack` trait → action ke baad wapas usi filtered list par

Ye wahi pattern hai jo naye modules ne bhi follow kiya. Detail: [frontend.md](../frontend.md)

---

## Email Templates

Baaki teeno se thoda alag — iske paas full pages hain (modal nahi):

| Route | Kaam |
|---|---|
| `GET /settings/templates` | List |
| `GET /settings/templates/create` | Naya form |
| `GET /settings/templates/{id}/edit` | Edit form |
| `GET /settings/templates/{id}/attachments/{file}` | Attachment kholo (`templates.attachment`) — `?download=1` = download |
| `POST /settings/templates/{id}/toggle` | Active/inactive |

**Features:**
- **CKEditor 5** body ke liye — heading, bold/italic/underline/strike, link, lists, quote, alignment, table. Shared editor: `partials/_email-editor.blade.php` + `public/assets/js/email-editor.js` (wahi Leads compose aur Deals Bulk Send me bhi). Quill hata diya.
- **Placeholders** `{{client_name}}`, `{{sender_name}}`, `{{sender_company}}` — "Insert" chips (`partials/_placeholder-chips.blade.php`) se cursor par daalo, subject ya body jahan cursor tha. Neeche dekho.
- **Attachments** — template ke saath files attach hoti hain, JSON column me metadata. Edit page par har file ka card: image ka thumbnail, naam par click = naye tab me khule, 👁 open, ⬇ download, ✕ remove. File disk par nahi (jaise prod DB local me import kiya ho) to card laal + "file missing on the server - upload it again". Naye chune hue files bhi save se pehle click karke dekh sakte ho.
- **Status** — sirf `active` templates compose modal me dikhte hain
- **Body save hone se pehle saaf** (`App\Support\EmailHtml::render()`) — neeche dekho.

**Attachment kholna — safe kaise:**

| Cheez | Kaise |
|---|---|
| Kaunsi file | Sirf wo jo template ki `attachments` list me hai (`basename(path)` match). Owner scope — dusre user ka template = 404 |
| Browser me khulti hai | PDF, PNG, JPG, GIF, WEBP, TXT (`INLINE_TYPES`) |
| Baaki sab (HTML, SVG, DOCX…) | Sirf download — HTML/SVG browser me kholna app ke origin par script chalata |
| Headers | Asli MIME (file content se), `X-Content-Type-Options: nosniff`, PDF chhod kar `Content-Security-Policy: sandbox` (PDF viewer sandbox me nahi khulta) |

**JSON API:**
```php
GET /api/templates
→ active templates (id, name, subject, body, attachments) — body EmailHtml::render() se saaf
```
Ye compose modal ka dropdown bharta hai.

### Placeholders — `{{ }}`

| Likho | Banta hai |
|---|---|
| `{{client_name}}` | Lead / deal ka naam (Leads compose me website se scrape kiya naam) |
| `{{sender_name}}` | `.env` ka `SENDER_NAME` |
| `{{sender_company}}` | Settings › Branding ka company name |

- Spaces / case se farak nahi: `{{ Client_Name }}` bhi chalega. Aliases: `{{name}}`, `{{company_name}}` = client_name.
- **Purane templates bhi chalte hain:** `[Client Name]`, `[Company Name]`, `[Your Name]`, `[Sender Name]`, `[Your Company Name]`, `{name}`.
- Galat key (`{{website}}`) waise hi rehti hai — chupchap gayab nahi hoti, email me dikh jaati hai.
- Ek hi class sab jagah bharti hai: `App\Support\EmailPlaceholders::fill()` — Leads send, Bulk Send. HTML body me value escape hoti hai (`Tom & Jerry` → `Tom &amp; Jerry`). Leads compose JS wahi keys template chunte hi bhar deta hai (preview ke liye); bache hue send par server bharta hai.

### Email body — HTML, saaf karke

Pehle email body plain text jaati thi (editor ki formatting gum). Ab:

```
Editor HTML ──▶ EmailHtml::render()
                  ├─ block tag (<p>, <h2>, <ul>…) se shuru → clean(): allowlist
                  └─ warna plain text → har line ek <p>   (purane emails, AI body, editor load na ho)
```

- **Allowlist** (`EmailHtml::ALLOWED`): p, br, hr, strong/b, em/i, u, s, h1–h4, ul/ol/li, blockquote, a (sirf `http(s)://`, `mailto:`, `tel:`), table. Style me sirf `text-align`. Baaki tag unwrap (text bachta hai); `script`, `style`, `iframe`, `svg`, `form`… poore hat jaate hain. Output DOM se naya banta hai — koi attribute chupke se nahi bachta.
- Kyun zaroori: ek user ka likha body Super Admin ke saamne bhi khulta hai (Sent Email modal) — usme script nahi chalni chahiye. Sent Email modal ab server ka `body_html` dikhata hai (pehle body bina escape ke `innerHTML` me jaati thi).
- Khaali editor `<p>&nbsp;</p>` bhejta hai — `EmailHtml::notBlankRule()` use "khaali" maanta hai.
- Saved templates par asar: links ka `target="_blank"` aur inline colour hat jaate hain, text wahi.

### CKEditor — licence aur CDN

- `ckeditor5@48.5.2` jsDelivr se (`partials/_email-editor.blade.php`), `licenseKey: 'GPL'`. jsDelivr npm build ko CKEditor "self-hosted" maanta hai, isliye GPL key chalti hai (CKEditor ke apne cloud CDN par nahi chalti). Editor me "Powered by CKEditor" badge dikhta hai.
- **GPL:** CKEditor 5 GPL-2.0+ hai. Closed-source / commercial product ke liye CKEditor ka commercial licence chahiye ho sakta hai — apne case ke hisaab se check kar lo.
- CDN load na ho to plain textarea dikhta hai aur sab kaam karta hai (`EmailEditor.getData()` textarea padh leta hai), body plain text ki tarah jaati hai.
- Bootstrap modal me: modal par `data-bs-focus="false"` (warna link box me type nahi hota) aur `body { --ck-z-panel }` (balloon modal ke upar dikhe) — `app-custom.css`.

---

## Categories — sabse important

Categories teen jagah use hoti hain:

| Kahan | Kaam |
|---|---|
| **Leads** | Filter — **bina category choose kiye table khaali dikhti hai** |
| **Finder** | Optional filter; save karte waqt lead ki category |
| **Bulks** | `find` run naye leads isi category me file karta hai |
| **Dashboard** | "Top Categories" section |

`bulks.category_id` FK isi table par hai (nullable, `nullOnDelete`).

> **Setup tip:** Kaam shuru karne se pehle kam se kam ek category zaroor banao. Warna Leads
> ki table hamesha khaali dikhegi aur `find` bulk runs ke leads bina category ke reh jayenge.

---

## Platforms

Lead kahan se mila. `Google` naam ka platform **special** hai — code me directly lookup hota hai:

```php
'platform_id' => Platform::where('name', 'Google')->value('id'),
```

Ye teen jagah hai: `FindLeadsJob`, `FinderController::store()`, `ProcessBulkJob::storeLead()`.

> Agar "Google" platform delete kar do to naye leads ka `platform_id` `null` ho jayega —
> crash nahi hoga (`value()` `null` deta hai), bas platform khaali rahega.

---

## SerpAPI

**URL:** `/settings/serpapi` · **Module key:** `serpapi` · Sidebar: Settings › SerpAPI

Google search ki key — har user ki **apni**. Teen jagah lagti hai:

| Jagah | Kya karta hai | Credits |
|---|---|---|
| Leads › **Find Leads** | Keyword ke Google results → 25 tak leads (`LeadFinderService`) | Kai (har page ek) |
| GMB Leads › **Search** | Google Maps, max 3 pages (`GmbFinderService`) | 1–3 |
| AI email writer | Email likhne se pehle company ke baare me 1 search (`AIService::researchCompany()`) | 1 |

### Kaunsi key chalti hai

`App\Services\SerpApiConfigService::key()` — har search isi se key leti hai:

```
User ki saved key (Active on)  ──▶ wahi
        ↓ nahi / Active off
.env ka SERPAPI_KEY            ──▶ server ki key (pehle jaisa — kuch nahi toota)
        ↓ wo bhi nahi
koi key nahi  → Find Leads: 🔔 "no SerpAPI key. Add yours under Settings > SerpAPI"
                GMB search: 🔔 "No SerpAPI key. Add yours under Settings > SerpAPI."
```

- Background search (`FindLeadsJob`, `GmbSearchJob`) **owner** ke roop me chalta hai (`RunsAsOwner`) — isliye credits usi user ke account se katte hain jisne search chalayi.
- Page ke upar badge batata hai abhi kaunsi key chal rahi hai: **your own key** / **server's key** / **no key**.
- `mine()` — Super Admin bhi sirf apni key dekhta / badalta hai.

### Form

| Field | Kaam |
|---|---|
| API Key | `serpapi.com/manage-api-key` se. **Encrypted** store (`encrypted` cast). Khaali chhodo = purani key rahe. "Remove my saved key" = hata do (server key par wapas) |
| Country for Find Leads | Google `gl` (2 letter: `in`, `us`, `uk`). Khaali = `LEAD_COUNTRY`, wo bhi khaali = worldwide |
| Language for Find Leads | Google `hl` (`en`, `hi`, `pt-br`). Khaali = `LEAD_LANGUAGE` |
| Active | Off = meri key aur defaults mat use karo — `.env` wale chalenge |
| **Test Key** | SerpAPI ka Account API (`account.json`) — **koi credit nahi katta**. Form wali key, warna saved, warna server ki. Jawab: "Connected - Free Plan, 87 searches left, 13 used this month." ya SerpAPI ka error ("Invalid API key…") |

Country / language request me padhe jaate hain aur job ko pass hote hain (`LeadController::search()`), kyunki worker me config cache hone par `env()` bharosemand nahi.

> `leads:find` artisan command ab country / language bhi settings se leta hai — pehle wo galti se
> `--limit` ko country ki jagah bhej deta tha (`gl=25`).

### Access

Naya module key `serpapi` (`User::MODULES`). Naye users (`modules = null`) ko apne aap milta hai;
jin users ki modules list pehle se set hai unhe Super Admin Settings › Users me **SerpAPI** tick kare.
Module na ho to bhi unki searches server ki key se chalti rahengi.

**Files:** `app/Models/SerpapiSetting.php`, `app/Services/SerpApiConfigService.php`,
`app/Http/Controllers/SerpapiSettingController.php`, `resources/views/serpapi-settings/index.blade.php`,
migration `2026_10_16_000003_create_serpapi_settings_table`.

```php
GET   /settings/serpapi        serpapi-settings.index
PUT   /settings/serpapi        serpapi-settings.update
POST  /settings/serpapi/test   serpapi-settings.test     (throttle:10,1)
```

---

## Addresses

Aapki apni company ki addresses — outreach email ke **footer** me lagti hain.

Compose modal me user choose karta hai kaunsi address use karni hai, aur wo `OutreachMail`
ko pass hoti hai.

Fields: `address`, `email`, `phone`, `alternate_phone`, `website`, `status`

**Active / Inactive:** row ke `⋮` menu me **Set Active** / **Set Inactive** (Email Templates
jaisa) → `POST /settings/addresses/{id}/toggle` (`addresses.toggle`). Edit modal se bhi badal
sakte ho. Inactive address compose modal ke dropdown me nahi aati, aur default footer ke liye
bhi nahi chuni jaati (`Address::active()`). Filter / list wahi rehti hai (`_redirect_back`).

---

## Branding

**URL:** `/settings/branding` — brand name aur logo. Table: `app_settings` (key/value).

| Key | Kahan dikhta hai | Default |
|---|---|---|
| `company_name` | Brand name — email copyright line, AI prompts, logo alt text | `SabRight` |
| `admin_logo` | Sidebar header, login/signup page **aur outreach email ka header** | `images/sabright-logo.png` |
| `admin_icon` | Collapsed sidebar, mobile header, favicon | logo (left side crop) |

- Brand name sirf yahin se aata hai — `.env` ka `SENDER_COMPANY` ab use nahi hota.
- Email aur admin panel ka logo **ek hi** hai, isliye logo PNG/JPG/GIF hi ho sakta hai (mail clients WEBP/SVG nahi dikhate).
- Email me logo **inline (CID) embed** hota hai — `APP_URL` localhost ho tab bhi dikhta hai.
- Files `public/uploads/branding/` me save hoti hain (git-ignored).
- Naya upload purani file delete kar deta hai; "Reset" default par wapas le jata hai.
- Code me use: `AppSetting::companyName()`, `adminLogoUrl()`, `adminIconUrl()`, `emailLogoUrl()`.

**Email Footer** (same page, neeche wala card):

| Key | Kaam |
|---|---|
| `social_linkedin`, `social_facebook`, `social_x`, `social_instagram`, `social_youtube` | Footer icons — sirf wahi dikhte hain jinka URL bhara ho |

Footer ka address/email/phone/website **Addresses** se aata hai: compose modal me chuna hua
address, warna pehla active address. Koi active address na ho to wo block hide ho jata hai.

---

## Sidebar me kahan hai

`config/navigation.php` me Settings ek **collapsible parent** hai:

```php
[
    'label'    => 'Settings',
    'active'   => ['templates.*', 'platforms.*', 'categories.*', 'addresses.*', 'mail-settings.*', 'branding.*'],
    'icon'     => 'bi-gear',
    'children' => [
        ['label' => 'Email Templates', 'route' => 'templates.index', ...],
        ['label' => 'Platforms',  'route' => 'platforms.index',  ...],
        ['label' => 'Categories', 'route' => 'categories.index', ...],
        ['label' => 'Addresses',  'route' => 'addresses.index',  ...],
        ['label' => 'Mail Settings', 'route' => 'mail-settings.index', ...],
        ['label' => 'Branding',   'route' => 'branding.index',   ...],
    ],
],
```

`active` array me **saare descendant patterns** likhne padte hain — taaki jab aap kisi bhi
settings page par ho, parent menu khula aur highlighted rahe.

Detail: [configuration.md](../configuration.md)
