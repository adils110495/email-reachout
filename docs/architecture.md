[← Docs index](README.md)

# Architecture

---

## Tech Stack

| Cheez | Kya use hua | Kyun |
|---|---|---|
| Framework | Laravel 11 (PHP 8.2) | — |
| Database | MySQL (Docker) | — |
| Queue | `database` driver | Redis project se hata diya gaya tha (commit `395e308`) |
| Cache / Session | `file` driver | Same reason |
| Frontend | Blade + Bootstrap 5 + W3CRM theme | Koi build step nahi |
| JS | Vanilla + jQuery + Select2 | Theme ke saath aata hai |
| External API | SerpAPI, OpenAI | Lead search + email writing |
| Mail | SMTP (send) + IMAP (Sent copy) | — |

### Build step kyun nahi hai

Project me **koi npm / webpack / vite pipeline nahi** hai. Saara CSS aur JS
`public/assets/` me direct rehta hai aur browser wahi load karta hai.

Iska matlab naye code me bhi ye rule follow karna hai:

- Koi bhi nayi JS library add mat karo jab tak bilkul zaroori na ho
- Dashboard ka chart isiliye **pure CSS** se banaya gaya hai — Chart.js add karne ke liye
  ya to CDN chahiye (offline break) ya build step (jo hai hi nahi)
- Naya CSS `public/assets/css/app-custom.css` me jata hai, theme ki `style.css` me **kabhi nahi**

---

## Code Layers

```
routes/web.php
      │   URL → controller mapping. Sirf yahan paths likhe jaate hain.
      ▼
app/Http/Controllers/
      │   Request lena, validate karna, view/JSON return karna.
      │   ⚠️ Business logic yahan NAHI hai.
      ▼
app/Services/
      │   Asli kaam — scraping, verification, AI, mail.
      │   Ye classes web request aur queue job dono jagah reuse hoti hain.
      ▼
app/Models/
      │   Eloquent models — tables, relations, scopes, accessors.
      ▼
Database

app/Jobs/            Lamba kaam jo background queue par chalta hai
resources/views/     Blade templates
config/navigation.php   Sidebar — single source of truth
public/assets/       CSS / JS (no build)
```

---

## Sabse important convention: Controller patla, Service mota

Har asli kaam Service class me hai, Controller me nahi. **Kyun?**

Kyunki wahi logic do jagah se chalta hai:

```
Web request                          Background job
     │                                     │
     ▼                                     ▼
FinderController::search()          ProcessBulkJob::findFor()
     │                                     │
     └──────────┬──────────────────────────┘
                ▼
         EmailFinderService::findByDomain()
                │
                └──→ ek hi logic, ek hi jagah
```

Agar ye logic controller me hota, to bulk job ke liye dobara likhna padta — aur dono copies
alag-alag drift kar jaatin.

**Example — Verifier:**

| Kahan se call hota hai | Kaunsa method |
|---|---|
| Single verify (web) | `VerifierController::verify()` → `EmailVerifierService::verify()` |
| Paste list (web) | `VerifierController::verifyMany()` → same service |
| Bulk run (queue) | `ProcessBulkJob::verifyFor()` → same service |
| Finder ka har candidate | `EmailFinderService` → same service |

Chaar entry points, **ek** verification logic.

---

## Naye modules ne kya reuse kiya

Naya kuch bhi scratch se nahi banaya jab pehle se maujood cheez kaam kar sakti thi:

| Naya module use karta hai | Ye purani cheez |
|---|---|
| Finder (domain scrape) | `ScraperService` + `EmailExtractorService` |
| Finder (save result) | `Lead` model, `leads` table |
| Bulks (find run) | Poora `EmailFinderService` |
| Bulks (verify run) | Poora `EmailVerifierService` |
| Sab list screens | `ajax-filters.js`, `ExportsCsv`, `RedirectsBack` traits |
| Sab pages | `layouts/app.blade.php` + partials |
| Sidebar | `config/navigation.php` |

---

## Shared Traits

`app/Http/Controllers/Concerns/`

| Trait | Kaam | Kaun use karta hai |
|---|---|---|
| `ExportsCsv` | CSV streaming download | Leads, Templates, Platforms, Categories, Addresses, **Finder, Verifier, Bulks** |
| `RedirectsBack` | Row action ke baad wapas usi filtered list par | Settings modules, **Verifier, Bulks** |
| `FiltersRequests` | **Naya** — query params ko safely normalise karna | **Finder, Verifier, Bulks** |

### `FiltersRequests` kyun banaya

Filters URL se aate hain, aur URL user edit kar sakta hai. Agar koi `?status[]=x` bhej de
to controller ko **string ki jagah array** milega — aur `in_array($request->query('status'), ...)`
ya string interpolation TypeError de sakta hai.

Ye trait har parameter ko pehle scalar me normalise karta hai:

```php
$this->strParam($request, 'q');                        // trimmed string, array = ''
$this->enumParam($request, 'status', ['new','sent']);  // sirf whitelist se, warna ''
$this->intParam($request, 'category');                 // positive int ya null
$this->perPage($request);                              // sirf 10/25/50/100
$this->likePattern($term);                             // % aur _ escape karke
```

`strParam` `input()` se padhta hai (`query()` se nahi) taaki GET filter bars aur POST forms
dono par kaam kare — jaise Verifier ka "Clear history" form jo status carry karta hai.

---

## Naming conventions

| Cheez | Convention | Example |
|---|---|---|
| Route names | `module.action` | `finder.index`, `bulks.status` |
| List partial | `_table` ya `_results` | `finder/_results.blade.php` |
| AJAX partial ka root | ek hi `.ajax-content` div | [frontend.md](frontend.md) |
| Model status | class constant | `Lead::STATUS_SENT`, `Bulk::TYPE_FIND` |
| Badge colour | model accessor | `$bulk->status_colour`, `$item->result_colour` |

**Badge colour model me kyun hai?**
Kyunki ek hi status kai jagah dikhta hai (dashboard, list, detail page, JSON polling).
Agar har Blade file me apna `match` hota to ek jagah colour badalne par baaki jagah
purana reh jata. Ab `EmailVerification::colourFor($status)` ek hi source hai.

---

## Request lifecycle — ek normal list page

```
Browser  GET /finder?category=3&q=design
   │
   ▼
routes/web.php  →  FinderController@index
   │
   ▼
FiltersRequests se params normalise
   │
   ▼
filtered($request)  →  Eloquent query banti hai (filters lagte hain)
   │
   ▼
paginate()  →  page ke rows
   │
   ▼
verdictsFor()  →  us page ke emails ka verdict, EK query me
   │
   ▼
$request->ajax()  ?
   ├── HAAN  →  view('finder._results')     ← sirf table partial
   └── NAHI  →  view('finder.index')        ← pura page
```

Wahi controller method dono serve karta hai. Isliye filter logic **duplicate nahi hota** —
AJAX aur full page load ka result hamesha same rehta hai.

Detail: [frontend.md](frontend.md)

---

## Folder map

```
app/
├── Http/Controllers/
│   ├── Concerns/          ExportsCsv, RedirectsBack, FiltersRequests
│   ├── DashboardController.php    ← naya
│   ├── FinderController.php       ← naya
│   ├── VerifierController.php     ← naya
│   ├── BulkController.php         ← naya
│   ├── LeadController.php
│   └── (EmailTemplate|Platform|Category|Address)Controller.php
├── Jobs/
│   ├── FindLeadsJob.php
│   ├── ScrapeLeadEmailJob.php
│   ├── SendEmailJob.php
│   └── ProcessBulkJob.php         ← naya
├── Mail/OutreachMail.php
├── Models/
│   ├── Lead.php, LeadEmail.php, EmailTemplate.php
│   ├── Platform.php, Category.php, Address.php
│   ├── Bulk.php                   ← naya
│   ├── BulkItem.php               ← naya
│   └── EmailVerification.php      ← naya
└── Services/
    ├── ScraperService.php         ← modify (time budget)
    ├── EmailExtractorService.php
    ├── EmailVerifierService.php   ← naya
    ├── EmailFinderService.php     ← naya
    ├── AIService.php
    ├── EmailSenderService.php
    └── ImapService.php

resources/views/
├── layouts/app.blade.php + partials/
├── dashboard/                     ← naya
├── finder/                        ← naya
├── verifier/                      ← naya
├── bulks/                         ← naya
├── leads/
├── templates/, platforms/, categories/, addresses/
└── emails/outreach.blade.php

config/
├── navigation.php                 ← sidebar
└── services.php                   ← API keys + email_verifier settings
```
