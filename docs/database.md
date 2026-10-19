[← Docs index](README.md)

# Database

---

## Schema — ek nazar me

```
                    ┌───────────────┐         ┌──────────────┐
                    │  categories   │         │  platforms   │
                    └───────┬───────┘         └──────┬───────┘
                            │                        │
                            │  category_id           │  platform_id
                            ▼                        ▼
                          ┌─────────────────────────────┐
              ┌──────────▶│           leads             │◀─────────┐
              │           └──────────┬──────────────────┘          │
              │                      │                             │
              │  lead_id             │  lead_id                    │  lead_id
              │                      ▼                             │
    ┌─────────┴──────────┐   ┌──────────────┐                     │
    │email_verifications │   │ lead_emails  │                     │
    └─────────┬──────────┘   └──────────────┘                     │
              │                                                    │
              │  bulk_id                                           │
              ▼                                                    │
       ┌─────────────┐        ┌──────────────┐                    │
       │    bulks    │───────▶│  bulk_items  │                    │
       └──────┬──────┘bulk_id └──────────────┘                    │
              │                                                    │
              │  category_id ──────────────────────────────────────┘
              │  ("find" run naye leads isi category me daalta hai)
              ▼
       ┌───────────────┐
       │  categories   │
       └───────────────┘

       ┌──────────────────┐        ┌────────────┐
       │ email_templates  │        │ addresses  │   (standalone)
       └──────────────────┘        └────────────┘
```

---

## Ownership — har row ek user ki

`bulk_items` aur `app_settings` ko chhod kar **har table me `user_id`** hai
(FK → `users`, cascade delete). Model par `BelongsToUser` trait global scope lagata
hai, isliye queries apne aap user ki rows tak simit rehti hain; Super Admin sab
dekhta hai. `users` me `role`, `is_active`, `modules` columns hain.

`categories.name`, `mail_settings.type`, `gmb_leads.place_id` aur
`finder_results.domain` ab **per user** unique hain (`(user_id, …)`).

Poori detail: [access-control.md](access-control.md)

---

## Purane tables

### `leads`
Har business jo mila hai.

| Column | Type | Note |
|---|---|---|
| `id` | bigint | |
| `company_name` | string | |
| `website` | string | Duplicate check isi par hota hai |
| `email` | string, nullable | Scraping ke baad bharta hai |
| `linkedin` | string, nullable | |
| `status` | enum | `new` / `sent` / `failed` / `replied` |
| `platform_id` | FK → platforms, nullable | Lead kahan se mila |
| `category_id` | FK → categories, nullable | |
| `created_at`, `updated_at` | timestamp | Dashboard chart isi se banta hai |

**Model scopes:**
```php
Lead::new()         // status = new
Lead::withEmail()   // email IS NOT NULL AND email != ''
$lead->hasEmail()   // bool
```

### `lead_emails`
Kaunsa email kisko gaya — Leads compose modal se ek lead ko, ya **Bulk Send** (Deals) se
kai contacts ko.

| Column | Note |
|---|---|
| `lead_id` | FK, cascade delete, **nullable** — deal ko gaye bulk email me khaali |
| `cash_lead_id` | FK → cash_leads, **nullOnDelete** — Bulk Send ka deal |
| `batch_id` | uuid, indexed, nullable — ek bulk send ke saare emails ka same |
| `contact_name`, `to_email` | **Snapshot** (bulk) — deal delete ho to bhi log padhne layak; `to_email` reply match bhi karta hai |
| `subject`, `body` | Jo actually bheja gaya, placeholders bhar kar. `body` ab editor ka saaf HTML hai; purani rows plain text — dono `EmailHtml::render()` se dikhte / jaate hain |
| `attachments` | JSON array — `{name, path, size, mime}` |
| `status` | string(15): `queued` / `sending` / `sent` / `failed` / `cancelled` (pehle enum `sent`/`failed` tha) |
| `error` | text, nullable — SMTP ka reason (bulk, failed par) |
| `sent_at` | Dashboard ka "Emails Sent" isi se |

**Index:** `(user_id, to_email)` — Bulk Send ka "Skip contacts already emailed".
Migration: `2026_10_16_000002_add_bulk_columns_to_lead_emails_table`.

### `email_templates`
Ready-made templates. `attachments` JSON cast hota hai.

### `platforms` / `categories`
Simple lookup tables — `name`, `status` (`active`/`inactive`), `active()` scope ke saath.

### `addresses`
Aapki apni company addresses — email footer me lagti hain.

---

## Naye tables

3 migrations, `2026_08_26_*`. Order important hai kyunki foreign keys hain.

### `bulks` — ek bulk run ka summary

`2026_08_26_000001_create_bulks_table.php`

| Column | Type | Kaam |
|---|---|---|
| `id` | bigint | |
| `name` | string | Run ka naam (default: file name) |
| `type` | enum | `verify` / `find` |
| `category_id` | FK → categories, nullable | Sirf `find` run ke liye — naye leads yahan file hote hain |
| `original_filename` | string, nullable | Jo user ne upload ki |
| `file_path` | string, nullable | `storage/app/bulk-uploads/...` |
| `status` | enum, indexed | `pending` / `processing` / `completed` / `failed` / `cancelled` |
| `total_records` | uint | CSV se kitni usable rows mili |
| `processed_records` | uint | Kitni ho chuki |
| `successful_records` | uint | Kitni ka kaam ka jawab mila |
| `failed_records` | uint | Baaki |
| `error` | text, nullable | Job fail hone par |
| `started_at`, `completed_at` | timestamp, nullable | |

**Accessors (Model me):**
```php
$bulk->progress        // 0-100 int
$bulk->isRunning()     // pending ya processing
$bulk->status_colour   // bootstrap colour — badge/progress bar ke liye
```

### `bulk_items` — run ki har ek row

`2026_08_26_000002_create_bulk_items_table.php`

| Column | Type | Kaam |
|---|---|---|
| `bulk_id` | FK → bulks, **cascade delete** | |
| `input` | string | Email (verify) ya domain (find) |
| `extra` | string, nullable | CSV ka second column — company name |
| `status` | enum | `pending` / `processing` / `done` / `failed` |
| `result_status` | string(20), nullable | verify: `valid`/`risky`/`invalid`/`unknown` · find: `found`/`not_found` |
| `result_value` | string, nullable | Find run me jo email mila |
| `score` | tinyint, nullable | 0-100 |
| `message` | text, nullable | Human-readable reason |
| `meta` | json, nullable | Extra data (checks, candidates list) |

**Index:** `(bulk_id, status)` — kyunki job baar baar "is bulk ke pending items do" query karta hai.

### `email_verifications` — verification history

`2026_08_26_000003_create_email_verifications_table.php`

| Column | Type | Kaam |
|---|---|---|
| `email` | string, indexed | Hamesha lowercase |
| `domain` | string, indexed | |
| `status` | enum, indexed | `valid` / `invalid` / `risky` / `unknown` |
| `score` | tinyint | 0-100 confidence |
| `reason` | string, nullable | Kyun ye result aaya |
| `checks` | json, nullable | Poora breakdown (neeche dekho) |
| `source` | string(20), indexed | `single` / `bulk` / `finder` |
| `lead_id` | FK → leads, **nullOnDelete** | Agar is email ka lead hai |
| `bulk_id` | FK → bulks, **cascade delete** | Agar bulk run se aaya |

**`checks` JSON ka shape:**
```json
{
  "syntax": true,
  "domain": true,
  "mx": true,
  "smtp": null,          // null = probe nahi hua
  "catch_all": null,     // null = probe nahi hua
  "disposable": false,
  "role": false,
  "free": false,
  "mx_hosts": ["mx1.example.com", "mx2.example.com"]
}
```

`null` ka matlab "check hua hi nahi" hai — `false` (fail hua) se alag. UI me
tino states alag icon dikhate hain: ✅ pass, ❌ fail, ➖ not checked.

### `finder_results` — Finder ka apna record

`2026_08_26_000004_create_finder_results_table.php`

| Column | Type | Kaam |
|---|---|---|
| `mode` | enum, indexed | `domain` / `person` — kis search se mila |
| `domain` | string, indexed | Jo company search hui |
| `person` | string, nullable | Person mode: naam |
| `company` | string, nullable | Site se scrape kiya hua naam |
| `email` | string, indexed | |
| `status` | enum, indexed | `valid` / `risky` / `invalid` / `unknown` |
| `score` | tinyint | 0-100 |
| `reason` | string, nullable | |
| `source` | string(20) | `website` (publish tha) / `pattern` (generate hua) |
| `guessed` | bool, indexed | Pattern se bana hai? |
| `type` | string(20) | `personal` / `generic` (info@, sales@) |
| `pattern` | string(40), nullable | Person mode: kaunsa pattern |
| `lead_id` | FK → leads, **nullOnDelete** | Lead ban gaya to link |

**Unique:** `(domain, email)` — wahi company dobara search karo to rows refresh
hoti hain, duplicate nahi banti.

> **Ye table kyun chahiye thi?** `leads` me ek company ka **ek** row aur us par
> **ek** address hota hai — outreach ke liye sahi hai, par iska matlab:
> - ek search me 5 address mile to sirf best save ho sakta tha, baaki gayab
> - person search ke guesses store hi nahi ho sakte the (unpar email chala jata)
> - kaunsi search ne kya diya, iska koi record nahi tha
>
> `finder_results` Finder ka apna record hai: saare candidates, unke scores, aur
> lead ban gaya to uska link.

---

## WhatsApp tables

3 migrations, `2026_10_15_*` (`000003` = bulk ke `batch_id` + `params`), aur Embedded Signup ke 2,
`2026_10_19_*` (`wa_accounts`, `whatsapp_contacts` + `whatsapp_messages.direction`). Module detail: [modules/whatsapp.md](modules/whatsapp.md)

### `serpapi_settings` — har user ki SerpAPI key

Migration `2026_10_16_000003`. Detail: [modules/settings.md](modules/settings.md) › SerpAPI

| Column | Type | Kaam |
|---|---|---|
| `user_id` | FK, **unique**, cascade | Ek user = ek row |
| `api_key` | text, **encrypted**, nullable | Khaali = server ka `SERPAPI_KEY` |
| `country` | string(2), nullable | Find Leads ka Google `gl` (`in`) |
| `language` | string(10), nullable | Find Leads ka Google `hl` (`en`) |
| `is_active` | bool | Off = key aur defaults ignore, `.env` wale chalein |

### `whatsapp_settings` — har user ka Cloud API connection

| Column | Type | Kaam |
|---|---|---|
| `user_id` | FK, **unique**, cascade | Ek user = ek row |
| `default_country_code` | string(4), nullable | Bina code wale number ke aage (`91`) |
| `phone_number_id` | string, nullable | Meta: bhejne wale number ki id |
| `business_account_id` | string, nullable | Meta: WABA id — templates list karne ke liye |
| `access_token` | text, **encrypted** | |
| `app_secret` | text, **encrypted** | Webhook signature check |
| `public_url` | string, nullable | Callback URL isi domain par banta hai (ngrok / live domain) |
| `webhook_key` | string(64), unique | Random 40 — webhook URL me, user pehchanta hai |
| `verify_token` | string(64) | Random 32 by default, user Webhook card me badal sakta hai — Meta webhook setup par echo karta hai |
| `is_active` | bool | Off = sirf "Open in WhatsApp" |

`webhook_key` / `verify_token` fillable **nahi** — model `creating` par khud banata hai.
`verify_token` sirf Webhook form (`updateWebhook`, `forceFill`) se badalta hai. Migration
`2026_10_16_000001` ne `public_url` add kiya.

### `whatsapp_messages` — har bheja gaya message

| Column | Type | Kaam |
|---|---|---|
| `gmb_lead_id` | FK → gmb_leads, **nullOnDelete** | Kis GMB lead ko |
| `cash_lead_id` | FK → cash_leads, **nullOnDelete** | Kis deal ko |
| `contact_name` | string, nullable | **Snapshot** — record delete ho to bhi log padhne layak |
| `phone` | string(20) | Normalised digits, `+` nahi (`919876543210`) |
| `channel` | string(10) | `api` (Cloud API) / `link` (wa.me) / `phone` (Business app — echo ya history) |
| `direction` | string(3), default `out` | `out` = humne / phone app ne bheja, `in` = history me aaya hua (status `received`). Live replies pehle jaise `reply_body` me |
| `type` | string(10) | `template` / `text` |
| `batch_id` | uuid, indexed, nullable | Bulk send — ek bulk ke saare messages ka same |
| `template_name`, `template_language` | nullable | Template message ho to |
| `params` | json, nullable | Bulk: is contact ke template values (`{{name}}` bhar kar) — job inhi se bhejta hai |
| `body` | text, nullable | Jo bheja (template ho to variables bhar kar) |
| `wamid` | string, **unique**, nullable | Meta ki message id — webhook status isi se match |
| `status` | string(15) | `queued` / `sending` / `sent` / `delivered` / `read` / `failed` / `cancelled` / `manual` |
| `error` | text, nullable | Meta ka reason (failed par) |
| `sent_at`, `delivered_at`, `read_at`, `replied_at` | timestamp | |
| `reply_body` | text, nullable | Pehla reply |

**Index:** `(user_id, phone)` — incoming reply par "is number ko last message kaunsa gaya" dhoondhna.
`phone` naam isliye (`to` nahi) kyunki `TO` MySQL ka reserved word hai.

### `wa_accounts` — Embedded Signup se juda number

| Column | Type | Kaam |
|---|---|---|
| `user_id` | FK, cascade | Owner (multi-user) |
| `waba_id` | string(32), indexed | WhatsApp Business Account |
| `phone_number_id` | string(32), **unique**, nullable | Ek number = ek owner |
| `display_phone_number`, `verified_name` | nullable | Meta se (`GET /{phone_number_id}`) |
| `business_id` | string(32), nullable | Meta Business portfolio |
| `access_token` | text, **encrypted** | Code exchange wala business token — kabhi log nahi |
| `token_type`, `token_expires_at` | nullable | 60 din wala token — expiry settings me dikhti hai |
| `two_step_pin` | text, **encrypted**, nullable | Sirf `FINISH` (naya number `register`) par |
| `is_coexistence` | bool | Business app ke saath chal raha number |
| `onboarding_event` | string(64) | `FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING` / `FINISH` / `FINISH_ONLY_WABA` / `UNKNOWN` |
| `contacts_sync_requested_at`, `history_sync_requested_at` | timestamp | `smb_app_data` kab maanga |
| `onboarded_at` | timestamp | Aakhri connect — Meta ki 24h sync window yahan se |
| `history_status` | string(16), nullable | `requested` / `importing` / `complete` / `declined` |
| `status` | string(16) | `connected` / `disconnected` |
| `last_error` | text, nullable | Sync warnings |

Bhejte waqt `WhatsappSetting` pehle owner ka **connected** `wa_accounts` row padhta hai
(`sendingPhoneNumberId()`, `sendingToken()`, `sendingBusinessAccountId()`), na ho to apne fields.

### `whatsapp_contacts` — Business app ke contacts

| Column | Type | Kaam |
|---|---|---|
| `user_id` | FK, cascade | Owner |
| `phone` | string(20) | Normalised digits. **Unique** `(user_id, phone)` — upsert |
| `name` | nullable | Phone ka naam (`smb_app_state_sync`). History naam overwrite nahi karti |
| `source` | string(16) | `app` (contacts sync) / `history` (sirf chat me mila) |
| `removed_at` | timestamp, nullable | Phone se delete hua |

Leads / GMB Leads me copy **nahi** hote — WhatsApp module ke andar hi rehte hain.

---

## Design decisions — kyun aise banaya

### `bulks` aur `bulk_items` alag kyun hain

Agar sirf ek table hota to progress bar dikhane ke liye har baar 5,000 rows scan karni
padtin. Ab:

- `bulks` me **counters** hain → progress ek row padh ke mil jata hai
- `bulk_items` me **detail** hai → kaam chhote chunks me hota hai

Aur sabse bada faayda: **resume-able** hai. Worker restart ho jaye to jo items abhi bhi
`pending` hain wo agli baar utha liye jaate hain. Ek single blob column me ye possible nahi hota.

### Counters increment kyun nahi hote

`ProcessBulkJob::syncCounters()` counters ko **items se dobara gin kar** set karta hai,
`increment()` nahi karta:

```php
$processed  = $bulk->items()->whereIn('status', ['done','failed'])->count();
$successful = $bulk->items()->where('status','done')->whereIn('result_status',['valid','risky','found'])->count();
```

**Kyun?** Agar job retry ho (`tries = 2`) aur increment hota, to wahi chunk dobara ginti me
aa jata aur `processed` `total` se zyada ho jata. Derive karne se ye kabhi nahi ho sakta —
jitni bar bhi chale, answer same rahega.

### `successful` / `failed` ka exact matlab

| Column | Kya count hota hai |
|---|---|
| `successful_records` | `result_status` ∈ `valid`, `risky`, `found` |
| `failed_records` | `processed - successful` (matlab `invalid`, `unknown`, `not_found`, error) |

`risky` ko successful me kyun gina? Kyunki `risky` ka matlab hai **mailbox real hai** —
bas usme risk hai (disposable / role-based / catch-all). Wo undeliverable nahi hai.

Exact breakdown chahiye to detail page par per-status chips hain (`BulkController::breakdown()`).

### Cascade rules

| FK | On delete | Kyun |
|---|---|---|
| `bulk_items.bulk_id` | **cascade** | Run delete = uske items ka koi matlab nahi |
| `email_verifications.bulk_id` | **cascade** | Same |
| `email_verifications.lead_id` | **null** | Lead delete ho jaye to bhi verification history rehni chahiye |
| `bulks.category_id` | **null** | Category delete ho to run ka record bacha rahe |
| `lead_emails.lead_id` | cascade | Pehle se tha (ab nullable) |
| `lead_emails.cash_lead_id` | **null** | Deal hate to bhi bulk email ka log rahe (`contact_name` / `to_email` snapshot) |

Matlab `Bulk` delete karne par uske items aur verifications **apne aap** hat jaate hain —
`BulkController::destroy()` me manually delete karne ki zaroorat nahi.

---

## Migration chalane ka tareeka

```bash
make migrate
```

Order matter karta hai:
1. `bulks` — kyunki `email_verifications` iski FK rakhta hai
2. `bulk_items` — `bulks` chahiye
3. `email_verifications` — `bulks` aur `leads` dono chahiye

`categories` pehle se maujood hai (`2026_05_16_000002`), isliye `bulks.category_id` ki FK safe hai.

---

## Common queries

```php
// Dashboard: status breakdown — 4 query ki jagah 1
Lead::select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total','status');

// Finder: ek page ke saare emails ka latest verdict — 1 query
EmailVerification::whereIn('email', $emails)->orderBy('id')->get()->keyBy('email');
// orderBy('id') asc + keyBy = duplicate keys par LAST (= newest) survive karta hai

// Bulk: agle chunk ke items
$bulk->items()->where('status','pending')->orderBy('id')->limit($n)->get();
```
