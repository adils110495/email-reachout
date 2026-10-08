[← Docs index](README.md)

# Security & Performance

---

# Security

## 1. SSRF — sabse important

**Risk:** Finder me domain **user** deta hai, aur server use fetch karta hai. Ye classic
SSRF hai — attacker `http://169.254.169.254/` daal kar cloud metadata (AWS credentials!)
padh sakta hai, ya internal admin panels hit kar sakta hai.

**Do layer ka guard:**

### Layer 1 — `normaliseDomain()` (spelling check)

```php
return preg_match('/^[a-z0-9.\-]+\.[a-z]{2,}$/', $host) ? $host : '';
```

Reject karta hai:
- `localhost` — koi dot nahi
- `127.0.0.1` — TLD digits hai, `[a-z]{2,}` fail
- `192.168.1.1` — same
- `http://user:pass@evil.com` — `parse_url` se host nikalta hai

### Layer 2 — `isPubliclyRoutable()` (actual resolve)

```php
$records = dns_get_record($host, DNS_A | DNS_AAAA);

if ($addresses === []) return false;      // resolve nahi hota = fetch karne ko kuch nahi

foreach ($addresses as $address) {
    $public = filter_var($address, FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    if ($public === false) return false;
}
```

| Flag | Kya block karta hai |
|---|---|
| `NO_PRIV_RANGE` | `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `fc00::/7` |
| `NO_RES_RANGE` | `127.0.0.0/8`, `169.254.0.0/16` (**metadata**), `0.0.0.0/8`, baaki reserved |

> **Layer 2 kyun zaroori hai jab Layer 1 hai?** Kyunki ek **bilkul valid dikhne wala public
> DNS name** bhi internal IP par point kar sakta hai. `internal.mycompany.com` → `10.0.0.5`
> Layer 1 pass kar lega. Sirf spelling dekhna kaafi nahi — **actual resolve** check karna
> padta hai.

Ye guard `findByDomain()` me hai, isliye **Finder aur Bulks (`find` run) dono** protected hain.

---

## 2. Input validation

Har POST par `$request->validate()`:

```php
// Finder
'mode'   => ['required', 'in:domain,person'],
'domain' => ['required', 'string', 'max:255'],
'name'   => ['required_if:mode,person', 'nullable', 'string', 'max:120'],

// Bulks upload
'type'   => ['required', 'in:verify,find'],
'file'   => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
'column' => ['nullable', 'integer', 'min:1', 'max:50'],
```

### Filter params — `FiltersRequests` trait

Filters URL se aate hain jise user edit kar sakta hai. `?status[]=x` bhejne par controller
ko **array** milega jahan string expect tha:

```php
in_array($request->query('status'), ['new','sent'], true)   // array pass kar do → unexpected
"WHERE status = {$status}"                                   // array → "Array to string" error
```

Trait har param normalise karta hai:

```php
$this->strParam($request, 'q');                        // non-scalar → ''
$this->enumParam($request, 'status', ['new','sent']);  // whitelist se bahar → ''
$this->intParam($request, 'category');                 // positive int ya null
$this->perPage($request);                              // sirf 10/25/50/100
```

`enumParam` **whitelist** approach hai — jo list me nahi hai wo silently ignore hota hai,
error nahi.

---

## 3. SQL injection

Sab Eloquent query builder se — **raw interpolation kahin nahi**.

**LIKE wildcards escape hote hain:**

```php
protected function likePattern(string $term): string
{
    return '%'.addcslashes($term, '%_\\').'%';
}
```

Bina iske user `%` search kare to **sab kuch match** ho jayega, aur `_` single-char wildcard
ban jayega. Ye injection to nahi, par galat results deta hai — aur bade table par slow bhi.

`DB::raw()` sirf **fixed strings** ke saath use hua hai (`COUNT(*)`, `DATE(created_at)`) —
kabhi user input ke saath nahi.

---

## 4. XSS

| Jagah | Protection |
|---|---|
| Blade | `{{ }}` auto-escape (`{!! !!}` kahin use nahi hua naye code me) |
| JS | Manual `esc()` helper |

```js
function esc(value) {
    const div = document.createElement('div');
    div.textContent = value === null || value === undefined ? '' : String(value);
    return div.innerHTML;
}
```

Har server value DOM me jane se pehle isse guzarti hai — email addresses, company names,
reasons, sab. Ye important hai kyunki company name **scraped** hota hai (attacker-controlled
website se!).

---

## 5. Mass assignment

Har model me `$fillable` defined hai — `$guarded = []` kahin nahi.

```php
// Bulk
protected $fillable = ['name','type','category_id','original_filename','file_path',
                       'status','total_records','processed_records','successful_records',
                       'failed_records','error','started_at','completed_at'];
```

---

## 6. File upload

| Check | Value |
|---|---|
| MIME | `mimes:csv,txt` |
| Size | 10 MB |
| Rows | 5,000 (config se) |
| Storage | `storage/app/bulk-uploads/` — **public nahi** |
| Filename | Laravel ka hashed name (original alag column me) |

File **kabhi execute nahi hoti** — sirf `fgetcsv` se padhi jati hai.

---

## 7. Data overwrite protection

Har jagah ek hi rule:

```php
if (empty($lead->email)) {
    $lead->update(['email' => $found]);    // sirf khaali ho to
}
// warna kuch mat karo
```

Finder ka save, Bulk ka find run — **koi bhi** user ka manually daala hua email overwrite
nahi karta.

---

## 8. Kya nahi hai

> ⚠️ **Is app me authentication nahi hai.** Koi login, koi user model, koi permissions.
> Ye internal tool assume kiya gaya hai. Public internet par deploy karne se pehle **auth
> layer add karna zaroori hai** — warna koi bhi saara lead data, email templates aur
> credentials-driven features access kar sakta hai.
>
> Layout me `<meta name="robots" content="noindex, nofollow">` hai, par ye sirf search
> engines ko rokta hai — access control **nahi** hai.

---

# Performance

## 1. N+1 queries

### Finder ke verdicts

```php
// ❌ 25 rows = 25 queries
foreach ($leads as $lead) {
    EmailVerification::where('email', $lead->email)->latest()->first();
}

// ✅ 1 query
$results  = $query->paginate($perPage);
$verdicts = EmailVerification::whereIn('email', $results->pluck('email'))
    ->orderBy('id')     // ascending
    ->get()
    ->keyBy('email');   // duplicate key par LAST (= newest) bachta hai
```

Ascending + `keyBy` = per-email "latest" **bina correlated subquery** ke.

### Dashboard ke stats

```php
// ❌ 4 queries
Lead::where('status','new')->count();  // ...×4

// ✅ 1 query
Lead::select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total','status');
```

### Top categories

Ek JOIN + `GROUP BY` me total aur "with email" dono nikal aate hain.

### Eager loading

```php
Lead::with(['platform','category'])    // Finder table — dono columns dikhte hain
Lead::with('category')                 // Dashboard recent — sirf category dikhti hai
```

Jo dikhta nahi, wo load nahi hota.

---

## 2. Memory

| Operation | Technique |
|---|---|
| CSV read | `fgetcsv` — line by line stream |
| CSV export | `cursor()` — ek-ek row, sab memory me nahi |
| Bulk insert | 200-row chunks |
| Bulk process | 4–20 item chunks |

```php
// ❌ 10,000 leads memory me
$this->streamCsv(..., $query->get(), ...);

// ✅ Streaming
$this->streamCsv(..., $query->cursor(), ...);
```

---

## 3. Caching

```php
Cache::remember('verify:dns:'.$domain, 86400, fn() => /* MX + A lookup */);
```

Ek company ke 500 addresses = **1 DNS lookup**, 500 nahi. Bulk runs me ye sabse bada
optimization hai.

---

## 4. Timeouts

| Kahan | Budget | Kyun |
|---|---|---|
| Finder web request | 60s (36 pass-1 + 24 follow reserve) | PHP `max_execution_time` 120s |
| Bulk find item | 20s | 3 items x 20 = 60s < 85s job timeout |
| Background jobs | unbounded | Queue par time nahi ki tension |
| SMTP probe | 8s | Har MX host par |

**`ScraperService` ka purana worst case:**
```
homepage 20s + 5 contact paths × 20s = 120s   ← web request me fatal
```

Ab optional budget hai. `null` = purana behaviour (background jobs unaffected).

---

## 5. Chunking — job timeout se bachna

| Type | Chunk | Per item | Worst case | Timeout |
|---|---|---|---|---|
| verify | 20 | ~0.5s | ~10s | 85s ✅ |
| find | 4 | 15s | 60s | 85s ✅ |

Detail: [queue-jobs.md](queue-jobs.md)

---

## 6. Polling — apne aap band

```js
if (! data.running) window.clearInterval(timer);
if (++failures >= 3) window.clearInterval(timer);
if (! row.isConnected) window.clearInterval(timer);
document.addEventListener('visibilitychange', ...);
```

| Situation | Requests |
|---|---|
| Koi run running nahi | **0** |
| Detail page, run chalu | Har 3s |
| Index, 2 runs chalu | Har 5s × 2 |
| Run khatam | **0** — polling band |
| Tab hidden | **0** — resume par catch-up |

---

## 7. Frontend

| Cheez | Impact |
|---|---|
| AJAX partials | Sirf table swap hota hai, pura page nahi |
| Debounced search | 400ms — ek word = 1 request, 8 nahi |
| In-flight abort | Purana request cancel |
| Koi chart library | Chart pure CSS — 0 KB extra |
| Koi build step | Direct assets, cacheable |

---

## Indexes

| Table | Index | Kyun |
|---|---|---|
| `email_verifications` | `email` | Verdict lookup |
| `email_verifications` | `status` | Filter + dashboard |
| `email_verifications` | `source` | Filter |
| `bulk_items` | `(bulk_id, status)` | "is bulk ke pending items" — job ki main query |
| `bulks` | `status` | Filter + dashboard |

`leads.website` par index **nahi** hai, par duplicate check wahi par hota hai
(`Lead::where('website', ...)`). Leads bahut badh jaayein to yahan index add karna
faydemand hoga.

---

## Optimization checklist (agar slow ho)

1. `laravel.log` me slow query dekho
2. Dashboard slow? → `leads` / `lead_emails` par `created_at` / `sent_at` index
3. Finder table slow? → `leads.company_name` par index
4. Bulk slow? → normal hai agar `find` type hai (har item = website fetch)
5. Sab verify `unknown`? → `VERIFY_SMTP_PROBE=false`
6. Worker peeche? → ek se zyada `queue` container chalao
