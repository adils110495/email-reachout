[← Docs index](../README.md)

# Module: Finder

**URL:** `/finder`
Email addresses dhundhne ke liye — company website se, ya person ke naam se.

---

## Files

| File | Kaam |
|---|---|
| `app/Http/Controllers/FinderController.php` | Search, save, export, lead table |
| `app/Services/EmailFinderService.php` | **Asli logic** — scrape, patterns, ranking |
| `resources/views/finder/index.blade.php` | Page + search form + result panel JS |
| `resources/views/finder/_results.blade.php` | Lead table partial (AJAX swap hota hai) |

**Routes:**
```php
GET   /finder          → finder.index     page + lead table
POST  /finder/search   → finder.search    live lookup (JSON)
POST  /finder/save     → finder.store     result ko lead banao (JSON)
GET   /finder/export   → finder.export    filtered CSV
```

---

## Page ke do hisse

```
┌─────────────────────────────────────────────────────────┐
│  1. LIVE LOOKUP                                         │
│     [Domain search] [Person search]  ← tabs             │
│     ┌────────────────────────────────────────────┐      │
│     │ domain / name input        [ Find ]        │      │
│     └────────────────────────────────────────────┘      │
│     ┌────────────────────────────────────────────┐      │
│     │  Result panel                              │      │
│     │  loading / error / empty / results         │      │
│     └────────────────────────────────────────────┘      │
├─────────────────────────────────────────────────────────┤
│  2. LEAD DATABASE                                       │
│     search + filters + table + pagination + export      │
└─────────────────────────────────────────────────────────┘
```

Dono independent hain: upar wala **naya** email dhundhta hai, neeche wala **pehle se
maujood** leads dikhata hai.

---

# Hissa 1 — Live Lookup

## Mode A: Domain Search

User `example.com` daalta hai. Poora flow:

```
User: "example.com"
   │
   ▼
① normaliseDomain()
   │  https:// , http:// , www. , path , port sab hata deta hai
   │  Agar email paste kiya ho to @ ke baad ka hissa le leta hai
   │  Regex check: ^[a-z0-9.\-]+\.[a-z]{2,}$
   │  ✗ fail → "That does not look like a valid domain" (422)
   ▼
② isPubliclyRoutable()          ← 🔒 SECURITY
   │  DNS resolve karke dekhta hai domain kis IP par jaata hai
   │  ✗ private (10.x, 172.16.x, 192.168.x)     → REJECT
   │  ✗ loopback (127.0.0.1)                    → REJECT
   │  ✗ link-local (169.254.169.254 = metadata) → REJECT
   │  ✗ resolve hi nahi hota                    → REJECT
   ▼
③ PASS 1 — ScraperService::fetch($entryUrl, 36s)
   │  agar user ne poora page URL paste kiya to WAHI page pehle
   │  phir homepage
   │  phir ye paths try karta hai (best-first):
   │     /contact, /contact-us, /about, /about-us, /team,
   │     /careers, /career, /jobs
   │  jo bhi mile unme se 3 tak collect karta hai
   ▼
④ PASS 2 — link following (sirf agar pass 1 me kuch nahi mila, 24s reserve)
   │  jo HTML mila usme se same-domain career/job/contact links nikalta hai
   │  4 links per round, 2 rounds tak
   │  round 1: /career (listing)  →  koi email nahi
   │  round 2: /career/publisher-outreach-manager/  →  MIL GAYA ✓
   ▼
⑤ EmailExtractorService::extract($html)
   │  mailto: links + plain text se emails nikalta hai
   │  noreply@, example.com, wixpress.com jaise blacklist filter karta hai
   ▼
⑥ Domain filter
   │  Sirf wahi emails rakhta hai jo isi domain ke hain
   │  (site par kisi partner ya plugin vendor ka email bhi ho sakta hai = noise)
   ▼
⑦ EmailVerifierService::verify()  — har email par (max 15)
   │  status + score milta hai
   │  DNS cached hai, to ek hi domain ke 15 emails = 1 lookup
   ▼
⑧ rank()
   │  Order: confirmed pehle > high score > personal (info@ se pehle)
   ▼
JSON response
```

**Company ka naam bhi nikalta hai** — `extractCompanyName()` pehle `og:site_name` meta tag
dekhta hai (sabse reliable), phir `<title>` se tagline hata ke naam leta hai. Ye save karte
waqt lead ka `company_name` ban jata hai.

---

## Mode B: Person Search

User naam + domain daalta hai: "Jamie Rivera" + `example.com`

**Yahan koi website scrape nahi hoti.** Kyunki kisi individual ka email website par
publish hota hi nahi. Iske bajaye common corporate patterns generate hote hain:

| Pattern | Result | Base score |
|---|---|---|
| `first.last` | jamie.rivera@example.com | 70 |
| `first` | jamie@example.com | 65 |
| `flast` | jrivera@example.com | 60 |
| `firstlast` | jamierivera@example.com | 55 |
| `first_last` | jamie_rivera@example.com | 50 |
| `last.first` | rivera.jamie@example.com | 45 |
| `first-last` | jamie-rivera@example.com | 40 |
| `f.last` | j.rivera@example.com | 35 |
| `last` | rivera@example.com | 30 |
| `firstl` | jamier@example.com | 25 |

Har pattern verify hota hai. Score `min(base, verified_score)` hota hai — matlab pattern ki
frequency **aur** domain ki health, dono count hote hain.

**Naam ka cleanup:**
```php
"Renée Müller"  →  asciiFold()  →  "Renee Muller"  →  renee.muller@...
```
`transliterator_transliterate` (intl) try hota hai, na mile to `iconv` fallback.

---

## ⚠️ Honesty — guessed vs confirmed

Ye module ka sabse important design decision hai.

| | Domain search | Person search |
|---|---|---|
| Email kahan se aaya | Website par **publish** tha | **Generate** kiya gaya |
| `guessed` flag | `false` | `true` |
| Max score | 95 | **70 (capped)** |
| UI badge | — | `guessed` badge |

`rank()` me `guessed` **pehla** sort key hai — matlab ek bhi scraped address ho to wo har
guess se upar rahega, chahe guess ka score kitna bhi ho.

**Kyun itna dhyan?** Kyunki bina SMTP probe ke hum sirf ye confirm kar sakte hain ki
*domain* mail leta hai. Ye ki *wo particular mailbox* exist karta hai — wo nahi. Ek guess
ko confirmed dikhana matlab user ka email bounce hoga aur uski sender reputation kharab hogi.

---

## Result panel ke states

Sab client-side JS handle karta hai (`finder/index.blade.php` ke bottom me):

| State | Kab | Kya dikhta hai |
|---|---|---|
| **Loading** | Request in flight | Spinner + "Scanning the website…" / "Working out likely addresses…" |
| **Results** | Emails mile | Ranked list, har row me score meter + badges + Save button |
| **Empty (site reachable)** | Scrape hua par email nahi mila | "No addresses found" + person search suggest |
| **Empty (site unreachable)** | `scraped = false` | "That website could not be reached" |
| **Error** | 422 / 500 | Server ka message |
| **Network error** | fetch reject | "Connection problem" |

> Empty ke **do alag** states kyun? Kyunki user ke liye ye do bilkul alag problem hain —
> "site down hai" (dobara try karo) vs "site par email nahi hai" (dusra tareeka try karo).
> Ek hi generic message dono ke liye useless hota.

---

## Save karna — duplicate se bachna

Save button dabane par `POST /finder/save`:

```
email + domain + company + category
   │
   ▼
normaliseDomain() dobara (server par bharosa, client par nahi)
   │
   ▼
website = "https://" + domain
   │
   ▼
Is website ka lead pehle se hai?
   │
   ├── HAAN  →  Lead ka email khaali hai?
   │             ├── HAAN  →  email bhar do
   │             └── NAHI  →  ⚠️ kuch mat karo (user ka data overwrite nahi karna)
   │             response: created = false
   │
   └── NAHI  →  naya Lead banao (status = new, platform = Google)
                 response: created = true
```

**Kabhi overwrite nahi hota.** Agar lead par pehle se email hai (jo user ne khud daala ho
ya pehle scrape hua ho), Finder use nahi badalta.

Button ke states: `Save` → spinner → `Saved` (green) ya `Retry` (red, 2.5s baad wapas normal).

---

# Hissa 2 — Lead Database Table

Neeche wala card poora `leads` table dikhata hai.

## Filters

| Filter | Type | Param |
|---|---|---|
| Search | Debounced server-side (400ms) | `q` — company / website / email |
| Category | Select2 | `category` |
| Platform | Select2 | `platform` |
| Status | Select2 | `status` |
| Email | Select2 | `has_email` = `yes` / `no` |
| Rows per page | 10/25/50/100 | `per_page` |

Sab **server-side** hain aur URL me jaate hain — matlab filtered view ka link share ho sakta
hai, aur back button kaam karta hai.

> **Search server-side kyun hai?** Kyunki `data-live-filter` (jo baaki modules me hai) sirf
> **screen par dikh rahi** rows hide karta hai. Agar match page 7 par hai to page 1 se
> nahi milega. Isliye yahan `data-search-param="q"` use kiya — ye server par query karta hai.
> Detail: [frontend.md](../frontend.md)

## Deliverability column

Har row me us email ka **latest verification verdict** dikhta hai (badge + score meter).

**N+1 se kaise bacha:**

```php
// ❌ Seedha tareeka — 25 rows = 25 queries
foreach ($leads as $lead) {
    $verdict = EmailVerification::where('email', $lead->email)->latest()->first();
}

// ✅ Jo kiya — 1 query
$results = $query->paginate(...);                       // pehle page nikalo
$verdicts = $this->verdictsFor($results->pluck('email')->all());   // phir 1 query
```

`verdictsFor()` ka trick:
```php
EmailVerification::whereIn('email', $emails)
    ->orderBy('id')        // ← ascending
    ->get()
    ->keyBy('email');      // ← duplicate key par LAST wala survive karta hai
```
Ascending order + `keyBy` = har email ka **newest** record bachta hai. Correlated subquery
ki zaroorat nahi.

## Row actions

- **Verify this address** → Verifier par jata hai `?q=email` ke saath (wahan prefill ho jata hai)
- **Open in Leads** → Leads module
- **Visit website** → naya tab

---

## Filter logic duplicate nahi hai

Table aur CSV export **ek hi** query builder use karte hain:

```php
private function filtered(Request $request): Builder
```

`index()` isko paginate karta hai, `export()` isko `cursor()` karta hai.

**Kyun important:** agar dono me alag filter code hota, to export me hamesha wo rows aati
jo user screen par dekh nahi raha — ek classic bug. Ab export **hamesha** wahi rows deta hai
jo table me dikh rahi hain.

Export `cursor()` use karta hai (`get()` nahi) — 10,000 leads bhi memory me load nahi hote.

---

## Security notes

| Risk | Kya kiya |
|---|---|
| **SSRF** | `isPubliclyRoutable()` — private/loopback/link-local IPs reject |
| **Request timeout** | 60s total: 36s pass-1 + 24s link-following reserve (PHP limit 120s) |
| **XSS** | JS me `esc()` helper — har server value escape hoke DOM me jati hai |
| **SQL injection** | Eloquent bindings + `likePattern()` se `%` `_` escape |
| **Bad filter input** | `FiltersRequests` trait — `?status[]=x` crash nahi karega |

### `isPubliclyRoutable()` detail

```php
$records = dns_get_record($host, DNS_A | DNS_AAAA);

foreach ($addresses as $address) {
    $public = filter_var($address, FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    if ($public === false) return false;
}
```

`normaliseDomain()` ka regex bare IPs aur `localhost` pehle hi reject kar deta hai. Lekin ek
**public DNS name** bhi `127.0.0.1` ya `169.254.169.254` par point kar sakta hai — isliye
sirf spelling nahi, **actual resolve** check hota hai.

---

## Deep pages — email jo listing par nahi hoti

Kai sites apna address **sirf ek gehre page** par daalti hain. Real example:

| Page | HTTP | Emails |
|---|---|---|
| `posticy.com/` | 200 | 0 |
| `posticy.com/contact` | 200 | 0 |
| `posticy.com/about` | 200 | 0 |
| `posticy.com/career` (listing) | 200 | **0** |
| `posticy.com/career/publisher-outreach-and-verification-manager/` | 200 | `career@posticy.com` ✓ |

Sirf paths guess karne se ye kabhi nahi milta — listing page respond to karta hai, par
usme address hai hi nahi. Isliye do cheezein hain:

**1. User ka poora URL respect hota hai.** Agar aap
`https://posticy.com/career/publisher-outreach-manager/` paste karte ho, wahi page pehle
padha jata hai. Aapne wo URL bina wajah ke paste nahi kiya.

> Host phir bhi validated domain se banta hai, raw input se nahi — taaki koi crafted input
> SSRF check paas karke kisi dusre host par fetch na kara de. Sirf **path** carry hota hai.

**2. Site ke apne links follow hote hain.** Pass 1 me kuch na mile to jo HTML mil chuka hai
usme se same-domain links nikale jaate hain jinke path me `career`, `job`, `contact`,
`about`, `team`, `hiring` jaisa kuch ho. 4 links per round, **2 round** tak.

> **2 round kyun?** Kyunki listing khud ek hop hai. Round 1 `/career` tak pahuchta hai
> (wahan kuch nahi), round 2 us page ke job postings tak — jahan email hai.

`wp-json`, `oembed`, `/feed`, `/tag/`, `/categories/`, aur asset files (`.pdf`, `.jpg`…)
filter ho jaate hain, aur jo page pehle fetch ho chuka wo dobara nahi hota.

### Budget

| Phase | Budget |
|---|---|
| Pass 1 (entry page + homepage + guessed paths) | 36s |
| Link following (reserve) | 24s |
| **Total** | **60s** |

Ye numbers ek asli slow site (posticy.com) par measure karke rakhe gaye hain:

```
homepage + 8 guessed paths   ~20.6s
careers listing                ~2.7s   (pass 1 me hi aa gaya)
2 job posting pages            ~5.9s   ← email yahan mila
                             ────────
                              ~29s     (60s budget me aaram se)
```

> **Reserve alag kyun rakha?** Bina reserve ke slow site par guessed paths pura budget kha
> jaate the aur following kabhi chalti hi nahi — jabki wahi case following ko sabse zyada
> chahiye tha, kyunki guesses ne kuch diya hi nahi. Pass 1 succeed kar jaye to reserve
> use hi nahi hota, to fast path par koi cost nahi.

Reserve **fixed seconds nahi, share hai** (40%, clamp `[6s, 25s]`):

| Caller | Total | Pass 1 | Follow reserve |
|---|---|---|---|
| Finder (web) | 60s | 36s | 24s |
| `ScrapeLeadEmailJob` | 45s | 27s | 18s |
| Bulk find item | 20s | 12s | 8s |

> Fixed 12s reserve rakhte to bulk item ke 20s budget ka aadha se zyada hissa reserve me
> chala jata — aur jo pass usually email dhundhta hai, wahi bhookha reh jata.

**Ceiling kya hai:** PHP `max_execution_time = 120s` (`docker/php/php.ini`), Apache
`ProxyTimeout 300s` (`docker/apache/000-default.conf`). To 60s dono ke andar hai.

**UI kya dikhata hai:** 60 second ka spinner dead lagta hai, isliye domain search me
progressive hints aate hain — 7s par "Checking the contact and careers pages…", 20s par
"Following links on the pages found so far. This can take up to a minute."

---

## Limitations

1. **JS-rendered sites** se email nahi milega — scraper HTML padhta hai, JS run nahi karta
2. **Bot-blocking sites** (Cloudflare challenge) block kar dengi
3. **Person search guesses hain** — SMTP probe on kiye bina confirm nahi ho sakte
4. Max **15 candidates** per domain verify hote hain (baaki ignore)
5. **Bahut slow site** par 60s bhi kam pad sakta hai - us case me us page ka **poora URL
   paste karna** sabse reliable hai
6. Email 3 se zyada level gehra ho to nahi milega — following 2 round tak hi hai
