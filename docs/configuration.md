[← Docs index](README.md)

# Configuration

> ⚠️ Is file me **koi real credential nahi** hai — sab placeholder hain. Asli values
> `.env` me hain, jo git me commit nahi honi chahiye.

---

## Setup

```bash
make migrate     # 3 naye tables: bulks, bulk_items, email_verifications
make up          # saare containers
make queue       # worker ke logs
```

| Command | Kaam |
|---|---|
| `make up` / `make down` | Containers start / stop |
| `make migrate` | Migrations |
| `make shell` | App container me bash |
| `make artisan` | Artisan command |
| `make queue` | Worker logs |
| `make logs` | Saare logs |

**Config cached nahi hai** (`bootstrap/cache/config.php` maujood nahi), isliye
`config/` ke changes turant apply hote hain — `config:clear` ki zaroorat nahi.
Blade files apne aap recompile hoti hain (mtime se).

---

## `.env` — naye variables

```env
# ── Email verification (Verifier / Bulks) ──────────────────────

# SMTP probe: mail server se asli RCPT TO conversation.
# Outbound port 25 chahiye — zyadatar hosts block karte hain.
# Blocked ho aur ye true ho to HAR address "unknown" aayega.
VERIFY_SMTP_PROBE=false

VERIFY_SMTP_TIMEOUT=8

# Probe khud ko is address se identify karta hai. Apne owned domain ka use karo.
VERIFY_SMTP_FROM="${MAIL_FROM_ADDRESS}"

# Domain ka DNS jawab kitni der reuse ho (seconds). 86400 = 1 din.
VERIFY_CACHE_TTL=86400

# Ek CSV se max kitni rows lein.
VERIFY_BULK_MAX_ROWS=5000
```

Details: [modules/verifier.md](modules/verifier.md)

---

## `.env` — purane variables

```env
APP_NAME="AI Client Finder"
APP_URL=http://localhost:8090

DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=cold_email_db

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database        # Redis hata diya gaya tha

# Mail (SMTP)
MAIL_MAILER=smtp
MAIL_HOST=<your-smtp-host>
MAIL_USERNAME=<your-email>
MAIL_PASSWORD=<your-password>
MAIL_FROM_ADDRESS=<your-email>

# IMAP — sent copy
IMAP_HOST=<your-imap-host>
IMAP_USERNAME=<your-email>
IMAP_PASSWORD=<your-password>
IMAP_FOLDER=INBOX.Sent

# APIs
SERPAPI_KEY=<your-serpapi-key>          # 100 free searches/month
OPENAI_API_KEY=<your-openai-key>
OPENAI_MODEL=gpt-3.5-turbo

# Outreach identity
SENDER_NAME="Your Name"

# Lead search
LEAD_COUNTRY=                    # khaali = global
LEAD_LANGUAGE=
LEAD_FETCH_LIMIT=25
```

---

## `config/services.php`

```php
'serpapi' => [
    'key' => env('SERPAPI_KEY'),
],

'openai' => [
    'key'   => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
],

'email_verifier' => [                                        // ← naya
    'smtp'          => filter_var(env('VERIFY_SMTP_PROBE', false), FILTER_VALIDATE_BOOLEAN),
    'smtp_timeout'  => (int) env('VERIFY_SMTP_TIMEOUT', 8),
    'smtp_from'     => env('VERIFY_SMTP_FROM', env('MAIL_FROM_ADDRESS')),
    'cache_ttl'     => (int) env('VERIFY_CACHE_TTL', 86400),
    'bulk_max_rows' => (int) env('VERIFY_BULK_MAX_ROWS', 5000),
],
```

> `filter_var(..., FILTER_VALIDATE_BOOLEAN)` isliye kyunki `.env` se sab **string** aata
> hai. `"false"` PHP me truthy hota hai — bina iske `VERIFY_SMTP_PROBE=false` bhi probe
> **on** kar deta.

---

## `config/navigation.php` — Sidebar

Sidebar ka **single source of truth**.

```php
return [
    'sidebar' => [
        [
            // 'title' => 'Main'   ← hata diya gaya (user request)
            'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard',      'active' => ['dashboard', 'dashboard.index'], 'icon' => 'bi-speedometer2'],
                ['label' => 'Finder',    'route' => 'finder.index',   'active' => ['finder.*'],   'icon' => 'bi-search'],
                ['label' => 'Verifier',  'route' => 'verifier.index', 'active' => ['verifier.*'], 'icon' => 'bi-patch-check'],
                ['label' => 'Bulks',     'route' => 'bulks.index',    'active' => ['bulks.*'],    'icon' => 'bi-stack'],
                ['label' => 'Leads',     'route' => 'leads.index',    'active' => ['leads.index'],'icon' => 'bi-people'],
                [
                    'label'    => 'Settings',
                    'active'   => ['templates.*', 'platforms.*', 'categories.*', 'addresses.*'],
                    'icon'     => 'bi-gear',
                    'children' => [ /* ... */ ],
                ],
            ],
        ],
    ],
];
```

### Entry ke fields

| Field | Kaam |
|---|---|
| `label` | Menu me dikhne wala text |
| `route` | **Route name** — kabhi hardcoded URL nahi |
| `active` | `request()->routeIs()` ke patterns — kab highlight ho |
| `icon` | Bootstrap Icons class (sirf top level) |
| `children` | Sub-menu, kisi bhi depth tak |

### Rules

1. **Sirf route names**, URLs nahi — `routes/web.php` me path badle to link apne aap sahi rahe
2. **Parent me saare descendant patterns** likho — warna sub-page par parent menu band ho jayega
3. `title` optional hai — na do to koi group heading nahi banti

### Naya menu item add karna

```php
['label' => 'Reports', 'route' => 'reports.index', 'active' => ['reports.*'], 'icon' => 'bi-file-bar-graph'],
```

Bas. Sidebar partial (`layouts/partials/sidebar-item.blade.php`) recursion se render kar deta hai.

---

## Routes

`routes/web.php`

### Naye routes

```php
// Dashboard
GET   /                      dashboard
GET   /dashboard             dashboard.index
GET   /dashboard/stats       dashboard.stats

// Finder
GET   /finder                finder.index
POST  /finder/search         finder.search
POST  /finder/save           finder.store
GET   /finder/export         finder.export

// Verifier
GET     /verifier            verifier.index
POST    /verifier/verify     verifier.verify
POST    /verifier/verify-many verifier.verify-many
POST    /verifier/clear      verifier.clear
DELETE  /verifier/{id}       verifier.destroy
GET     /verifier/export     verifier.export

// Bulks
GET     /bulks               bulks.index
POST    /bulks               bulks.store
GET     /bulks/{id}          bulks.show
GET     /bulks/{id}/status   bulks.status
POST    /bulks/{id}/cancel   bulks.cancel
POST    /bulks/{id}/retry    bulks.retry
GET     /bulks/{id}/export   bulks.export
DELETE  /bulks/{id}          bulks.destroy
```

### ⚠️ Ek badlav

| Pehle | Ab |
|---|---|
| `GET /` → Leads | `GET /` → **Dashboard** |
| — | `GET /leads` → Leads |

Route ka **naam** wahi hai (`leads.index`). Poore project me links `route('leads.index')`
se bane hain, isliye kahin kuch break nahi hua.

### `whereNumber` kyun

```php
Route::get('/bulks/{id}', ...)->whereNumber('id');
```

Warna `/bulks/export` jaisa path `{id}` me match ho jata aur controller ko `"export"`
milta jahan integer expect tha.

---

## Docker

`docker-compose.yml` ke services:

| Service | Kaam | Port |
|---|---|---|
| `app` | PHP-FPM | — |
| `webserver` | Nginx | `APP_PORT` (8090) |
| `mysql` | Database | `DB_PORT_EXTERNAL` |
| `phpmyadmin` | DB UI | `PMA_PORT` |
| `queue` | Worker | — |

**Queue worker:**
```
php artisan queue:work database --queue=emails,default --tries=3 --backoff=30 --sleep=3 --timeout=90
```

`ProcessBulkJob` `default` par jata hai — jo pehle se consume ho raha hai. **Naya worker
setup nahi chahiye.**

---

## Production checklist

| Cheez | Value |
|---|---|
| `APP_DEBUG` | `false` |
| `APP_ENV` | `production` |
| `LOG_LEVEL` | `warning` ya `error` |
| Queue worker | Supervisor / restart policy ke saath |
| `VERIFY_SMTP_PROBE` | `true` **sirf** agar port 25 khula hai |
| `.env` | Git me **nahi** |
| Storage permissions | `storage/` aur `bootstrap/cache/` writable |

Agar `php artisan config:cache` chalate ho to yaad rakho — jobs me `env()` reliable nahi
rehta. Isliye `FindLeadsJob` apne country/language **constructor se** leta hai, `env()` se
nahi. Naye jobs me bhi yahi pattern follow karo.

---

## Troubleshooting

| Problem | Wajah | Fix |
|---|---|---|
| Sidebar me naya item nahi | Config cached | `php artisan config:clear` |
| `Table ... doesn't exist` | Migrations nahi chali | `make migrate` |
| Bulk `pending` par atka | Worker band | `docker compose up -d queue` |
| Sab verify `unknown` | Port 25 blocked | `VERIFY_SMTP_PROBE=false` |
| `Undefined array key` view me | Controller/view key mismatch | Dono side check karo |
| Leads nahi mil rahe | SerpAPI key / quota | `laravel.log` |
| 404 naye routes par | Routes cached | `php artisan route:clear` |
