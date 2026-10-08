[← Docs index](../README.md)

# Module: Dashboard

**URL:** `/` (aur `/dashboard`)
**Ye ab landing page hai.** Pehle `/` par Leads tha — ab Leads `/leads` par hai.

---

## Files

| File | Kaam |
|---|---|
| `app/Http/Controllers/DashboardController.php` | Saare metrics calculate karta hai |
| `resources/views/dashboard/index.blade.php` | Poora page |
| `public/assets/css/app-custom.css` | `.stat-card`, `.activity-chart`, `.score-meter` |

**Routes:**
```php
GET  /                  → dashboard          (landing)
GET  /dashboard         → dashboard.index    (wahi page)
GET  /dashboard/stats   → dashboard.stats    (JSON)
```

---

## Ye module kya karta hai

Baaki saare modules ka **live summary** ek jagah dikhata hai. Yahan koi cheez create/edit
nahi hoti — ye purely read-only screen hai.

**Saara data database se live aata hai.** Kuch bhi hardcoded ya dummy nahi hai.

---

## Page ka layout

```
┌────────────────────────────────────────────────────────────────────┐
│  [Total Leads] [Contactable] [Emails Sent] [Verified Valid]        │  ← 4 stat cards
├────────────────────────────────────┬───────────────────────────────┤
│  Last 14 Days (bar chart)          │  Lead Pipeline                │
│  leads found vs emails sent        │  new/sent/replied/failed      │
├────────────────────────────────────┼───────────────────────────────┤
│  Deliverability                    │  Top Categories               │
│  valid/risky/invalid/unknown       │  leads + email coverage %     │
├────────────────────────────────────┼───────────────────────────────┤
│  Recent Leads (last 8)             │  Bulk Runs (last 5, live)     │
├────────────────────────────────────┴───────────────────────────────┤
│  Quick Actions — Finder / Verifier / Bulks / Templates             │
└────────────────────────────────────────────────────────────────────┘
```

---

## Har section ka data source

| Section | Kahan se | Query |
|---|---|---|
| Total Leads | `leads` | `GROUP BY status` |
| Contactable | `leads` | `withEmail()` scope + coverage % |
| Emails Sent | `lead_emails` | `GROUP BY status`, + today / this week |
| Verified Valid | `email_verifications` | `GROUP BY status` |
| Last 14 Days | `leads.created_at` + `lead_emails.sent_at` | `GROUP BY DATE()` |
| Lead Pipeline | `leads` | Wahi status breakdown reuse hota hai |
| Deliverability | `email_verifications` | Wahi breakdown reuse |
| Top Categories | `categories` JOIN `leads` | `GROUP BY category` |
| Recent Leads | `leads` | `latest('id')->limit(8)` |
| Bulk Runs | `bulks` | `latest('id')->limit(5)` |

---

## Flow

```
GET /
   │
   ▼
DashboardController::index()
   │
   ▼
metrics()  ← ek hi method jo sab kuch nikalta hai
   │
   ├── Lead::groupBy('status')          →  $leadStats
   ├── LeadEmail::groupBy('status')     →  $emailStats
   ├── EmailVerification::groupBy(...)  →  $verifyStats
   ├── Bulk counts                      →  $bulkStats
   ├── activity()                       →  $activity (14 din ka chart)
   ├── topCategories()                  →  $topCategories
   ├── recentLeads / runningBulks       →  tables
   └── templateCount                    →  quick action card
   │
   ▼
view('dashboard.index', $data)
```

`metrics()` alag method me isliye hai kyunki **do jagah** se use hota hai — `index()`
(HTML) aur `stats()` (JSON).

---

## Performance — kya khaas kiya

### 1. Grouped queries, per-status count nahi

Seedha tareeka ye hota:

```php
// ❌ 4 alag queries
$new     = Lead::where('status','new')->count();
$sent    = Lead::where('status','sent')->count();
$failed  = Lead::where('status','failed')->count();
$replied = Lead::where('status','replied')->count();
```

Yahan iski jagah **ek** query hai:

```php
// ✅ 1 query
$byStatus = Lead::select('status', DB::raw('COUNT(*) as total'))
    ->groupBy('status')
    ->pluck('total', 'status');

$total = $byStatus->sum();   // extra query nahi lagti
```

Yahi pattern `lead_emails` aur `email_verifications` par bhi hai.

### 2. Top Categories — ek JOIN, N+1 nahi

Har category ke liye alag `leads()->count()` chalane ke bajaye:

```php
Category::select('categories.id', 'categories.name')
    ->selectRaw('COUNT(leads.id) as leads_count')
    ->selectRaw("SUM(CASE WHEN leads.email IS NOT NULL AND leads.email != '' THEN 1 ELSE 0 END) as with_email")
    ->join('leads', 'leads.category_id', '=', 'categories.id')
    ->groupBy('categories.id', 'categories.name')
    ->orderByDesc('leads_count')
    ->limit(5)
```

Total aur "with email" dono **ek hi pass** me nikal aate hain.

### 3. Recent leads — sirf zaroori relation

`->with('category')` hai, `platform` nahi — kyunki table me sirf category dikhti hai.
Jo load nahi karna, wo load nahi hota.

---

## Chart — koi library nahi

Chart **pure CSS** se bana hai (`.activity-chart`). Kyun:

- Project me koi build step nahi hai ([architecture.md](../architecture.md))
- CDN se Chart.js load karte to offline / CSP me break hota
- 14 bars ke liye 60 KB library download karna waste hai

**Kaise banta hai:**

```php
// SQL me group
$leads = Lead::select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
    ->where('created_at', '>=', $from)->groupBy('day')->pluck('total', 'day');

// PHP me poori 14 din ki range par replay
for ($i = 0; $i < 14; $i++) {
    $date = $from->copy()->addDays($i);
    $leadSeries[] = (int) ($leads[$date->toDateString()] ?? 0);   // ← missing din = 0
}
```

> **Ye `?? 0` important hai.** SQL sirf un dino ki rows deta hai jab activity thi. Agar
> seedha wahi loop karte, to jis din kuch nahi hua wo din **chart se gayab** ho jata aur
> baaki din shift ho jaate — chart jhooth bolta. Isliye poori date range banayi jaati hai
> aur khaali din `0` se bharte hain.

Bar ki height:

```blade
style="height: {{ max(2, round(($leadCount / $activity['max']) * 100)) }}%"
```

- `$activity['max']` dono series ka shared max hai → dono bars comparable rehti hain
- `max(2, ...)` se `0` wale din bhi ek patli line dikhti hai (bilkul gayab nahi hoti)
- `max` kabhi `0` nahi hota (`max(1, ...)`) → division by zero se safe

---

## `/dashboard/stats` — JSON endpoint

```json
{
  "leads":         { "total": 144, "new": 139, "sent": 3, ... },
  "emails":        { "sent": 24, "today": 0, "reply_rate": 0, ... },
  "verifications": { "total": 0, "valid": 0, ... },
  "bulks":         { "total": 2, "running": 1 },
  "generated_at":  "2026-08-26T12:28:00+00:00"
}
```

Abhi UI isko poll nahi karta (page load par hi fresh data aata hai). Ye endpoint isliye hai
taaki baad me auto-refresh add karna ho to controller badalna na pade — `metrics()` already
dono ke liye ready hai.

---

## Empty states

Har section ka apna empty state hai, aur **har ek agle step ka link** deta hai:

| Section | Khaali hone par |
|---|---|
| Chart | "No activity in the last 14 days" + Finder ka link |
| Pipeline | "No leads yet" + Finder ka link |
| Deliverability | "Nothing verified yet" + Verifier ka link |
| Top Categories | "No categorised leads yet" |
| Recent Leads | "No leads yet" |
| Bulk Runs | "No bulk runs yet" + Bulks ka link |

Naya user ko khaali dashboard par **dead end nahi** milta — har jagah se aage ka raasta hai.

---

## Gotcha jo mila tha

Pehle controller `$bulkStats` me key `'rows'` bhej raha tha, par view `$bulkStats['records']`
padh rahi thi → `Undefined array key "records"` error (`laravel.log`).

**Fix:** controller me key `records` kar di — kyunki `BulkController::totals()` pehle se
usi figure ko `records` bolta hai. Ab dono module me ek hi naam hai.

**Seekh:** controller ke array keys aur view ke keys ko match karna manual kaam hai —
naya section add karte waqt dono side check kar lena.
