[← Docs index](../README.md)

# Module: Bulks

**URL:** `/bulks`
CSV upload karke poori list par Verifier ya Finder chalane ke liye.

---

## Files

| File | Kaam |
|---|---|
| `app/Http/Controllers/BulkController.php` | Upload, list, detail, status, cancel, retry, export |
| `app/Jobs/ProcessBulkJob.php` | **Background processing** — chunk me chalta hai |
| `app/Models/Bulk.php` | Run — counters, progress accessor |
| `app/Models/BulkItem.php` | Har row |
| `resources/views/bulks/index.blade.php` | Runs list + upload modal + live polling |
| `resources/views/bulks/_table.blade.php` | Runs table partial |
| `resources/views/bulks/show.blade.php` | Detail page + progress + polling |
| `resources/views/bulks/_items.blade.php` | Results table partial |

**Routes:**
```php
GET     /bulks              → bulks.index     saare runs
POST    /bulks              → bulks.store     CSV upload
GET     /bulks/{id}         → bulks.show      detail + results
GET     /bulks/{id}/status  → bulks.status    live progress (JSON)
POST    /bulks/{id}/cancel  → bulks.cancel    run rok do
POST    /bulks/{id}/retry   → bulks.retry     adhoore items dobara
GET     /bulks/{id}/export  → bulks.export    results CSV
DELETE  /bulks/{id}         → bulks.destroy   run delete
```

---

## Do type ke run

| Type | CSV me kya | Kya karta hai | Extra |
|---|---|---|---|
| `verify` | Email addresses | Har address verify | `email_verifications` me records |
| `find` | Company domains | Har domain ka email dhundhta hai | **Leads bhi banata hai** |

`find` run ke liye ek **category** choose hoti hai — naye leads usi category me file hote
hain (kyunki Leads module category se filter karta hai).

---

## Flow 1 — Upload

```
User CSV choose karta hai + type + column numbers + category
   │
   ▼
POST /bulks  →  BulkController::store()
   │
   ▼
validate: mimes:csv,txt · max 10 MB · column 1-50
   │
   ▼
readCsv()
   │  fgetcsv se STREAM hota hai (poori file memory me load nahi hoti)
   │  ├── header row skip (agar checkbox on)
   │  ├── UTF-8 BOM hata do (pehli cell se)
   │  ├── blank lines skip
   │  ├── verify: lowercase + "@" nahi hai to DROP
   │  ├── find:   normaliseDomain() + invalid to DROP
   │  ├── duplicate DROP
   │  └── max 5,000 rows (config se)
   │
   ▼
0 rows mili?  →  back() with error, form dobara bhar ke
   │
   ▼
File store karo → storage/app/bulk-uploads/
   │
   ▼
Bulk record (status = pending, total_records = N)
   │
   ▼
BulkItem rows insert — 200 ke chunk me
   │  (5,000 rows = 25 inserts, 5,000 nahi)
   │
   ▼
ProcessBulkJob::dispatch() → 'default' queue
   │
   ▼
Redirect → /bulks/{id}  (progress page)
```

### Unusable rows upfront kyun drop hote hain

Jo row kaam ki hi nahi (email me `@` nahi, domain invalid) wo **upload ke time** hi hat
jati hai — queue par bheji hi nahi jati.

**Kyun?** Warna wo "failed record" ban kar counters kharab karti, aur user ko lagta ki
verification fail hua — jabki asli baat ye thi ki input hi galat tha. Better: upfront drop
karo aur `total_records` me sirf wahi rows ginno jo actually process hongi.

---

## Flow 2 — Background processing

Ye module ka **core design** hai.

### Problem

Queue worker `--timeout=90` par chalta hai. 5,000 rows ek job me process karne ki koshish
karo to worker beech me **kill** kar dega, aur run hamesha ke liye "processing" par atak
jayega.

### Solution — chunking + self re-dispatch

```
ProcessBulkJob::handle()
   │
   ▼
Bulk exist karta hai aur running hai?
   │  ✗ NAHI → return (delete ho gaya / cancel ho gaya / khatam ho gaya)
   ▼
status pending hai? → processing kar do, started_at set karo
   │
   ▼
Agle N pending items utha lo
   │     verify → 20 items
   │     find   → 4 items      (kyunki har item ek website fetch karta hai)
   ▼
┌─ HAR ITEM PAR ────────────────────────────────────┐
│                                                    │
│  item.status = processing    ← CLAIM karo pehle   │
│       │  (taaki duplicate job dobara na uthaye)   │
│       ▼                                            │
│  verify  →  EmailVerifierService::verify()        │
│             → email_verifications me record       │
│             → item ka result save                 │
│                                                    │
│  find    →  EmailFinderService::findByDomain()    │
│             → best candidate lo                    │
│             → Lead create/update                   │
│             → email_verifications me record       │
│             → item ka result save                 │
│                                                    │
│  ⚠ Exception aaya?                                 │
│       → item failed, message me error             │
│       → baaki items chalte rahenge                │
└────────────────────────────────────────────────────┘
   │
   ▼
syncCounters()  — items se dobara gin kar set karo
   │
   ▼
Abhi bhi pending items hain?
   │
   ├── HAAN → ProcessBulkJob::dispatch($bulk->id)   ← KHUD KO dobara
   │          return
   │
   └── NAHI → "processing" me atke items ko failed mark karo
              (ye us job ke hain jo beech me mar gaya)
              syncCounters() dobara
              status = completed, completed_at = now
```

### Chunk size alag kyun

| Type | Chunk | Per item | Worst case |
|---|---|---|---|
| `verify` | 20 | ~0.5s (DNS, cached) | ~10s |
| `find` | 4 | 15s (website fetch) | 60s |

Dono **85s timeout** ke andar aaram se aa jaate hain. Agar `find` bhi 20 hota to
20 × 15 = 300s — worker kill kar deta.

### Resume-ability

```
Worker restart ho gaya beech me
   │
   ▼
Jo items 'pending' hain  →  agla job utha lega ✅
Jo items 'processing' me atke  →  end me 'failed' mark honge
   │
   ▼
User "Retry Unfinished" dabata hai
   │
   ▼
failed + processing items  →  wapas 'pending'
Bulk status  →  'pending'
ProcessBulkJob dobara dispatch
```

### Counters — increment nahi, derive

```php
private function syncCounters(Bulk $bulk): void
{
    $processed  = $bulk->items()->whereIn('status', ['done','failed'])->count();
    $successful = $bulk->items()->where('status','done')
                        ->whereIn('result_status', ['valid','risky','found'])->count();

    $bulk->update([
        'processed_records'  => $processed,
        'successful_records' => $successful,
        'failed_records'     => $processed - $successful,
    ]);
}
```

**Increment kyun nahi?** Job `tries = 2` par hai. Agar retry hua aur increment hota, to wahi
chunk dobara ginti me aa jata → `processed` `total` se zyada ho jata → progress bar 130%
dikhata. Derive karne se ye **kabhi** nahi ho sakta.

Cost: 2 extra COUNT queries per chunk. Ye sasta hai aur correctness guarantee milti hai.

---

## Flow 3 — Live progress

```
Browser                                    Server
   │                                          │
   │  har 3s (detail) / 5s (list)             │
   │─────  GET /bulks/{id}/status  ──────────▶│
   │                                          │  Bulk::findOrFail()
   │◀──────  JSON  ───────────────────────────│  + breakdown()
   │                                          │
   │  paint(): progress bar, badge,           │
   │           counters, breakdown chips      │
   │                                          │
   │  data.running === false ?                │
   │     → clearInterval()  ⏹ POLLING BAND    │
```

**Polling apne aap band ho jati hai** jab run khatam ho — kyunki completed run ke numbers
kabhi change nahi hote, aur request bhejna pure waste hai.

**Aur bhi optimizations:**

| Situation | Behaviour |
|---|---|
| Tab hidden ho gaya | Polling ruk jati hai; wapas aane par turant ek poll + resume |
| Server 3 baar consecutive fail | Polling band + "Lost contact with the server" message |
| Row AJAX filter se replace ho gaya | `row.isConnected` check → purana timer khud clear |
| Index page par koi run running nahi | **Ek bhi request nahi** jati |

Index page par sirf wahi rows poll hoti hain jinpe `data-bulk-running` attribute hai.

---

## Detail page

```
┌──────────────────────────────────────────────────────────────┐
│  Run name  [Processing]         [Download] [Cancel] [All]    │
├──────────────────────────────────────────────────────────────┤
│  Progress          847 / 5,000 records (17%)                 │
│  ████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░            │
│                                                               │
│  [Total 5000] [Processed 847] [Successful 612] [Failed 235]  │
│                                                               │
│  [Valid 612] [Risky 89] [Invalid 146] [Unknown 0] [Pending 4153]  ← chips
│                                                               │
│  ⟳ Running in the background — updates automatically          │
├──────────────────────────────────────────────────────────────┤
│  Results — search + result filter + table + pagination       │
└──────────────────────────────────────────────────────────────┘
```

**Breakdown chips clickable hain** — click karo to neeche ki table us result se filter ho
jati hai (`?result=valid`).

**Results table** run type ke hisaab se columns badalti hai:

| verify run | find run |
|---|---|
| Email · Result · Confidence · Notes | Domain · **Email found** · Result · Confidence · Notes |

Item ka status bhi dikhta hai: `Queued` (hourglass) · `Running` (spinner) · ya final result badge.

---

## Cancel aur Retry

### Cancel

```php
$bulk->update(['status' => 'cancelled', 'completed_at' => now()]);
```

Bas itna hi. **Job ko kill karne ki zaroorat nahi** — `ProcessBulkJob` har chunk ke shuru me
`isRunning()` check karta hai, to agla chunk apne aap ruk jayega.

Jo items ho chuke hain unke results **rehte hain**.

### Retry

```php
$bulk->items()->whereIn('status', ['failed','processing'])->update([
    'status' => 'pending', 'result_status' => null, 'message' => null,
]);
$bulk->update(['status' => 'pending', 'error' => null, 'completed_at' => null]);
ProcessBulkJob::dispatch($bulk->id);
```

Sirf **adhoore** items dobara chalte hain — jo successful ho chuke wo dobara process nahi hote.

---

## Delete

```php
if ($bulk->file_path && Storage::disk('local')->exists($bulk->file_path)) {
    Storage::disk('local')->delete($bulk->file_path);
}
$bulk->delete();
```

`bulk_items` aur `email_verifications` **foreign key cascade** se apne aap hat jaate hain —
manually delete karne ki zaroorat nahi. Sirf uploaded file manually hatani padti hai
(wo database me nahi hai).

---

## Lead creation ("find" run)

```php
$lead = Lead::where('website', $website)->orWhere('website', $website.'/')->first();

if ($lead) {
    if (empty($lead->email)) {
        $lead->update(['email' => $best['email']]);   // sirf khaali ho to bharo
    }
    return $lead;
}

return Lead::create([
    'company_name' => $item->extra ?: ($result['company'] ?: $result['domain']),
    'website'      => $website,
    'email'        => $best['email'],
    'status'       => Lead::STATUS_NEW,
    'platform_id'  => Platform::where('name','Google')->value('id'),
    'category_id'  => $bulk->category_id,
]);
```

**Company name ka priority order:**
1. CSV ka name column (`$item->extra`) — user ne diya hai, sabse bharosemand
2. Website se scrape kiya hua naam (`og:site_name` ya `<title>`)
3. Domain khud (last resort)

**Existing lead ka email kabhi overwrite nahi hota.**

---

## CSV format

### Verify run
```csv
email,company
jamie@example.com,Example Ltd
sam@acme.com,Acme Inc
```
Data column = 1, Name column = 2

### Find run
```csv
domain,company
example.com,Example Ltd
acme.com,Acme Inc
```

**Ek column bhi chalega** — bas Name column khaali chhod do.
Header nahi hai to "first row is a header" uncheck kar do.

---

## Security & Performance

| Cheez | Kya kiya |
|---|---|
| File upload | `mimes:csv,txt`, max 10 MB, 5,000 row cap |
| Memory | `fgetcsv` stream — poori file load nahi hoti |
| Insert | 200-row chunks |
| Export | `cursor()` — streaming, memory me sab nahi |
| SSRF | `find` run bhi `EmailFinderService` ka `isPubliclyRoutable()` guard use karta hai |
| Job timeout | Chunk size timeout ke hisaab se tuned |
| Duplicate processing | Item pehle `processing` claim hota hai |
| Double counting | Counters derive hote hain |
| Filter input | `FiltersRequests` — `result` whitelist se |

---

## Limitations

1. **Queue worker chalu hona zaroori hai.** Band hai to run `pending` par atka rahega.
   Docker ka `queue` service pehle se `default` consume karta hai.
2. **5,000 row cap** — `VERIFY_BULK_MAX_ROWS` se badal sakte ho, par bade files ke liye
   worker ki memory/time dekhni padegi
3. **Cancel turant nahi hota** — agla chunk boundary par rukta hai (max ~60s)
4. **`find` run slow hai** — har domain ek website fetch hai. 1,000 domains ≈ 4+ ghante
5. Ek waqt me kitne runs chalein iski koi limit nahi — bahut saare ek saath dispatch karoge
   to worker queue lamba ho jayega
