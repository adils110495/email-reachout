[← Docs index](README.md)

# Queue & Background Jobs

---

## Setup

| Cheez | Value |
|---|---|
| Driver | `database` (`QUEUE_CONNECTION=database`) |
| Table | `jobs` (migration `2026_04_06_000001`) |
| Worker | Docker ka `queue` service |
| Command | `php artisan queue:work database --queue=emails,default --tries=3 --backoff=30 --sleep=3 --timeout=90` |

> **Redis nahi hai** — commit `395e308` me project se hata diya gaya tha. Isliye queue,
> cache aur session sab database/file par hain.

### ⚠️ Worker chalu hona zaroori hai

Worker band hai to:
- Bulk runs `pending` par atke rahenge
- Keyword search se leads nahi aayenge
- Lead emails scrape nahi honge

```bash
make queue        # worker ka log dekho
docker compose ps # service chal raha hai?
```

---

## Saare jobs

| Job | Kab | Queue | Tries | Timeout |
|---|---|---|---|---|
| `FindLeadsJob` | Keyword search | `default` | 2 | 85s |
| `ScrapeLeadEmailJob` | Har naye lead ke liye | `default` | 2 | 60s |
| `SendEmailJob` | Automated email send | `default` | 3 | 120s |
| `ProcessBulkJob` | **Naya** — bulk upload | `default` | 2 | 85s |

Sab `default` par jaate hain, jo worker pehle se consume karta hai — **extra setup nahi chahiye**.

---

## Timeout 85s kyun (90 nahi)

Worker `--timeout=90` par chalta hai. Agar job ka apna timeout bhi 90 hota, to race condition
banti: worker job ko `SIGKILL` kar deta **isse pehle** ki job apna cleanup (`failed()`
handler, status update) kar pata.

85 rakhne se job pehle khud fail hoti hai, cleanup chalta hai, aur database consistent rehta hai.

---

## `ProcessBulkJob` (naya) — sabse important

### Problem

5,000 rows ek job me = worker 90s par kill kar dega = run hamesha "processing" par atka.

### Solution — chunking + self re-dispatch

```
ProcessBulkJob::handle()
   │
   ├─ Bulk running hai?  ✗ → return
   │
   ├─ pending → processing
   │
   ├─ Agle N items lo    (verify: 20 | find: 3)
   │
   ├─ Har item: claim → process → result save
   │
   ├─ syncCounters()
   │
   └─ Pending bache?
        ├── HAAN → self::dispatch()  ← khud ko dobara queue par
        └── NAHI → completed
```

Ek job ek chunk karta hai, phir **naya job** queue par daal deta hai. Har job chhota rehta
hai, timeout kabhi hit nahi hota.

### Constants

```php
private const CHUNK_VERIFY = 20;    // DNS lookup, cached — sasta
private const CHUNK_FIND   = 3;     // poori website fetch — mehnga
private const FIND_BUDGET  = 20.0;  // per item scrape budget (link following ke liye)
public  int   $timeout     = 85;
```

**Math:**

| Type | Chunk × per item | Worst case | Timeout |
|---|---|---|---|
| verify | 20 × ~0.5s | ~10s | 85s ✅ |
| find | 3 x 20s | 60s | 85s ✅ |

Agar `find` bhi 20 hota: 20 x 20 = **400s** → worker kill → run atak jata.

### Item claiming — duplicate se bachna

```php
$item->update(['status' => BulkItem::STATUS_PROCESSING]);   // PEHLE claim
try { /* process */ } catch { /* mark failed */ }
```

Item pehle `processing` mark hota hai, tab process hota hai. Isse wo `pending` query se
nikal jata hai — agar koi duplicate job chal raha ho to wahi item dobara nahi uthayega.

### Error isolation

```php
foreach ($items as $item) {
    try {
        /* process */
    } catch (Throwable $e) {
        Log::warning(...);
        $item->update(['status' => 'failed', 'message' => 'Processing error: '.$e->getMessage()]);
        // ← baaki items chalte rahenge
    }
}
```

Ek item ka fail hona **poora chunk nahi girata**. Ek dead website 19 baaki addresses ko
nahi rokti.

### Stuck items ka cleanup

Job mar gaya beech me → kuch items `processing` par atke reh gaye. Wo `pending` query me
nahi aayenge, to run kabhi complete nahi hota.

Isliye jab pending khatam ho jaate hain:

```php
$bulk->items()->where('status', 'processing')->update([
    'status'        => 'failed',
    'result_status' => 'unknown',
    'message'       => 'Interrupted before the result was recorded.',
]);
```

Phir user **Retry** dabakar inhe dobara chala sakta hai.

### Counters derive hote hain

```php
$processed  = $bulk->items()->whereIn('status', ['done','failed'])->count();
$successful = $bulk->items()->where('status','done')
                    ->whereIn('result_status', ['valid','risky','found'])->count();
```

Increment nahi kyunki job retry ho sakti hai → double counting → progress 130%.
Derive karne se **kitni bhi baar chale, answer same**.

### `failed()` handler

```php
public function failed(Throwable $e): void
{
    Bulk::where('id', $this->bulkId)->update([
        'status' => 'failed', 'error' => $e->getMessage(), 'completed_at' => now(),
    ]);
}
```

Saare retries khatam hone par run `failed` mark hota hai aur error UI me dikhta hai —
run silently atka nahi rehta.

---

## Purane jobs

### `FindLeadsJob`

```
keyword + categoryId + country + language
   │
   ▼
LeadFinderService::find()  →  SerpAPI  →  max 25 results
   │
   ▼
Har result: duplicate check → Lead::create() → ScrapeLeadEmailJob::dispatch()
```

> `country` / `language` **constructor se pass hote hain**, job ke andar `env()` se nahi
> padhe jaate. Kyunki `env()` queued worker me reliable nahi hai jab config cache ho.
> Ye pattern har job me follow karna chahiye.

### `ScrapeLeadEmailJob`

Per-lead email scraping. Alag job isliye taaki **ek slow website baaki 24 leads ko na roke**.

```php
if (! empty($this->lead->email)) return;    // pehle se hai to skip
$html = $scraper->fetch($this->lead->website, self::SCRAPE_BUDGET);  // 45s
$emails = $extractor->extract($html);
if (! empty($emails)) $this->lead->update(['email' => $emails[0]]);
```

### `SendEmailJob`

3 tries, 60s backoff. Sirf **aakhri** attempt par lead `failed` mark hota hai —
temporary SMTP glitch par lead permanently failed nahi hota.

> Compose modal ise use nahi karta (wo direct bhejta hai). Ye automated sending ke liye hai.

---

## Debugging

```bash
make queue                                    # worker logs
docker compose logs -f queue                  # same
tail -f storage/logs/laravel.log              # app logs
```

**Database se:**
```sql
SELECT COUNT(*) FROM jobs;                    -- pending jobs
SELECT * FROM failed_jobs ORDER BY id DESC;   -- failed
SELECT status, COUNT(*) FROM bulk_items WHERE bulk_id = 5 GROUP BY status;
```

**Common problems:**

| Symptom | Wajah | Fix |
|---|---|---|
| Bulk `pending` par atka | Worker band hai | `make up` / `docker compose up -d queue` |
| Bulk `processing` par atka | Job mar gaya | **Retry** dabao |
| Sab items `unknown` | SMTP probe on hai, port 25 blocked | `VERIFY_SMTP_PROBE=false` |
| `find` run bahut slow | Har item website fetch hai | Normal — 1000 domains ≈ 4+ ghante |
| Leads nahi aa rahe | SerpAPI key / quota | Log check karo |

**Manually job chalana (test ke liye):**
```bash
make shell
php artisan queue:work database --queue=default --once
```
