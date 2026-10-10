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

**Files:**
```
app/Http/Controllers/EmailTemplateController.php
app/Http/Controllers/PlatformController.php
app/Http/Controllers/CategoryController.php
app/Http/Controllers/AddressController.php

resources/views/templates/   (index, create, edit, _form, _table, _quill-init)
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
| `POST /settings/templates/{id}/toggle` | Active/inactive |

**Features:**
- **Quill** rich text editor body ke liye (`_quill-init.blade.php`)
- **Attachments** — template ke saath files attach hoti hain, JSON column me metadata
- **Status** — sirf `active` templates compose modal me dikhte hain

**JSON API:**
```php
GET /api/templates
→ active templates (id, name, subject, body, attachments)
```
Ye compose modal ka dropdown bharta hai.

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

## Addresses

Aapki apni company ki addresses — outreach email ke **footer** me lagti hain.

Compose modal me user choose karta hai kaunsi address use karni hai, aur wo `OutreachMail`
ko pass hoti hai.

Fields: `address`, `email`, `phone`, `alternate_phone`, `website`, `status`

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
