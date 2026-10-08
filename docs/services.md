[← Docs index](README.md)

# Services

Asli business logic yahan hai — controllers me nahi. Kyun: [architecture.md](architecture.md)

---

## Saari services

| Service | Status | Kaam |
|---|---|---|
| `ScraperService` | **Modify hua** | Website ka HTML laata hai |
| `EmailExtractorService` | Purana | HTML se emails nikalta hai |
| `EmailVerifierService` | **Naya** | Email verify karta hai |
| `EmailFinderService` | **Naya** | Email dhundhta hai (scrape ya patterns) |
| `LeadFinderService` | Purana | SerpAPI se Google search |
| `AIService` | Purana | OpenAI se email likhta hai |
| `EmailSenderService` | Purana | SendEmailJob dispatch karta hai |
| `ImapService` | Purana | Sent folder me copy |

---

## `EmailVerifierService` (naya)

Email verify karta hai — **koi paid API nahi**.

```php
$result = $verifier->verify('jamie@example.com');
// [
//   'email'  => 'jamie@example.com',
//   'domain' => 'example.com',
//   'status' => 'valid',       // valid | risky | invalid | unknown
//   'score'  => 80,            // 0-100
//   'reason' => 'Valid syntax and the domain accepts mail...',
//   'checks' => [ syntax, domain, mx, smtp, catch_all, disposable, role, free, mx_hosts ],
// ]
```

**Public API:**
```php
verify(string $email): array           // ek address
verifyMany(array $emails): array       // list, email se keyed
parseList(string $blob): array         // pasted text → email array
```

**Checks ka order aur logic:** [modules/verifier.md](modules/verifier.md)

**Kaun call karta hai:**
- `VerifierController` — single + list
- `ProcessBulkJob` — bulk verify run
- `EmailFinderService` — har candidate ko score dene ke liye

### Design points

| Cheez | Detail |
|---|---|
| **Short-circuit** | Malformed email par DNS lookup hoti hi nahi |
| **DNS cache** | `verify:dns:{domain}`, 24 ghante — ek company ke 500 emails = 1 lookup |
| **A record fallback** | MX na ho to A/AAAA (RFC 5321 §5.1) |
| **Tri-state checks** | `true` / `false` / `null` (= check hua hi nahi) |
| **SMTP off by default** | Port 25 blocked hota hai — warna sab `unknown` |

### Hardcoded lists

```php
DISPOSABLE_DOMAINS  // ~45 throwaway providers
FREE_DOMAINS        // ~30 consumer providers
ROLE_PREFIXES       // ~46 shared mailbox prefixes (career@, hr@, jobs@ included)
```

Naye disposable providers aate rehte hain — list yahi update karni padegi.

---

## `EmailFinderService` (naya)

Domain ya person ka email dhundhta hai.

```php
$finder->findByDomain('example.com');              // scrape
$finder->findByDomain('example.com', 60.0);        // 60 sec budget ke saath
$finder->findByPerson('Jamie Rivera', 'example.com');  // patterns
$finder->normaliseDomain('https://www.Example.com/about');  // → 'example.com'
$finder->extractCompanyName($html);                // og:site_name ya <title>
```

**Dependencies (constructor se inject hoti hain):**
```php
ScraperService         // HTML laata hai
EmailExtractorService  // emails nikalta hai
EmailVerifierService   // score deta hai
```

Teeno **pehle se maujood ya naye** services hain — duplicate logic nahi likha gaya.

### Budget aur link following

```php
WEB_BUDGET            = 60.0   // Finder web request ka total
FOLLOW_RESERVE_RATIO  = 0.4    // isme se following ke liye reserve (share)
FOLLOW_RESERVE_MIN    = 6.0
FOLLOW_RESERVE_MAX    = 25.0
FOLLOW_PAGES          = 4      // links per round
FOLLOW_DEPTH          = 2      // rounds
FOLLOW_MIN_BUDGET     = 4.0    // isse kam bacha to round shuru mat karo
```

Reserve **fixed seconds nahi, share hai** — 40%, clamp `[6s, 25s]`:

| Caller | Total | Pass 1 | Follow reserve |
|---|---|---|---|
| Finder (web) | 60s | 36s | 24s |
| `ScrapeLeadEmailJob` | 45s | 27s | 18s |
| Bulk find item | 20s | 12s | 8s |

> **Reserve alag kyun?** Bina iske slow site par guessed paths pura budget kha jaate the
> aur following kabhi chalti hi nahi — jabki wahi case use sabse zyada chahiye tha.
> Pass 1 succeed kar jaye to reserve use hi nahi hota.
>
> **Share kyun, fixed nahi?** Fixed 12s rakhte to bulk item ke 20s budget ka aadha se
> zyada reserve me chala jata, aur jo pass usually email dhundhta hai wahi bhookha reh jata.

**Ceiling:** PHP `max_execution_time = 120s` (`docker/php/php.ini`), Apache
`ProxyTimeout 300s` (`docker/apache/000-default.conf`).

### Link following — deep pages

Pass 1 me kuch na mile to jo HTML mil chuka hai usme se same-domain links follow hote hain:

```php
$links = $this->scraper->discoverLinks($html, $url, 4);
$html .= $this->scraper->fetchUrls($links, $left);
```

`discoverLinks()` pehle se fetch kiye gaye URLs skip karta hai, isliye har round apne aap
ek level gehra jata hai. 2 round — kyunki listing khud ek hop hai
(`/career` → `/career/some-job/`).

Detail aur real example: [modules/finder.md](modules/finder.md#deep-pages--email-jo-listing-par-nahi-hoti)

### Ranking

```php
usort($candidates, fn($a, $b) =>
    [$a['guessed'], -$a['score'], $a['type'] === 'generic']
<=> [$b['guessed'], -$b['score'], $b['type'] === 'generic']);
```

Priority: **confirmed pehle** → **high score** → **personal before generic (info@)**

`guessed` pehla key hai — matlab ek bhi scraped address ho to wo har guess se upar rahega.

### `isPubliclyRoutable()` — SSRF guard

```php
$records = dns_get_record($host, DNS_A | DNS_AAAA);
// har IP par:
filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
```

Private (10.x, 172.16.x, 192.168.x), loopback (127.x), aur link-local
(169.254.169.254 = cloud metadata) sab reject.

**Kyun zaroori:** Finder me domain **user** deta hai aur server use fetch karta hai.
`normaliseDomain()` ka regex bare IP aur `localhost` reject kar deta hai, par ek public DNS
name bhi internal IP par point kar sakta hai — isliye **actual resolve** check hota hai.

---

## `ScraperService` (modify hua)

```php
$scraper->fetch($url);                                   // unbounded, 3 extra pages
$scraper->fetch($url, 18.0);                             // 18 sec budget
$scraper->fetch($url, budgetSeconds: 12.0, maxExtraPages: 0);  // sirf homepage
```

**Kya karta hai:** agar URL me path hai to **wo page pehle**, phir homepage, phir ye paths
best-first try karta hai:

| Priority | Path | Kyun |
|---|---|---|
| 1–2 | `/contact`, `/contact-us` | Business jis address par reach hona chahta hai |
| 3–5 | `/about`, `/about-us`, `/team` | Aksar team ke individual emails |
| 6–8 | `/careers`, `/career`, `/jobs` | `careers@` / `hr@` / `jobs@` — kai companies public me sirf yahi daalti hain |

Jo bhi respond karein unme se **3 tak** collect karta hai (`maxExtraPages`).

> **Pehle sirf 1 extra page aata tha** — loop pehle successful page par `break` kar deta
> tha. Matlab jis site par `/contact` maujood hai, wahan `/careers` **kabhi check hi
> nahi hota tha**. Isliye ab counter hai, `break` nahi.

**Naye public methods:**

```php
discoverLinks(string $html, string $baseUrl, int $limit = 3): array  // same-domain career/contact links
fetchUrls(array $urls, ?float $budgetSeconds = null): string         // shared budget me kai URLs
```

`fetched[]` har run me track hota hai, to ek page do jagah se linked ho to bhi ek hi baar
download hota hai.

### Har caller ka budget

Ek page fail hone par 20 sec tak le sakta hai, isliye har caller ka apna budget hai —
worst case uske timeout ke andar rehna chahiye:

| Caller | Budget | Extra pages | Uski limit |
|---|---|---|---|
| `EmailFinderService` (Finder web request) | 60s (36 pass-1 + 24 reserve) | 3 + link following | PHP 120s |
| `ProcessBulkJob` (find item) | 20s | 3 + link following | 85s job (3 x 20 = 60s) |
| `ScrapeLeadEmailJob` | 45s | 3 | 60s job |
| `LeadController::scrapeContact` | 12s | **0** | PHP 120s |
| `FindLeadsCommand` (CLI) | 45s | 3 | — |

> `scrapeContact` sirf `og:site_name` / `<title>` padhta hai — dono homepage par hain.
> Isliye `maxExtraPages: 0`; contact/careers pages fetch karna wahan pura waste tha.

### Budget parameter kyun add kiya

Purana worst case:

```
homepage timeout        20s
/contact timeout        20s
/contact-us timeout     20s
/about timeout          20s
/about-us timeout       20s
/team timeout           20s
                    ─────────
                       120s   ← web request me ye fatal hai
```

Background job ke liye theek tha, par Finder me user saamne baitha hai.

**Ab:**
```php
if ($left !== null && $left < 1.0) break;   // 1 sec se kam bacha? aur page mat try karo
$options['timeout'] = max(1.0, $timeout);   // per-request cap bhi
```

`null` default = **purana behaviour bilkul same**. `FindLeadsJob` / `ScrapeLeadEmailJob`
me kuch nahi badla.

---

## `EmailExtractorService` (purana)

HTML se emails nikalta hai:

1. `mailto:` links (sabse reliable)
2. `data-email` / `data-cfemail` attributes
3. Plain text regex (`strip_tags` ke baad)

**Filter karta hai:**
- Blacklisted domains: `example.com`, `wixpress.com`, `squarespace.com`, `sentry.io`, `w3.org`...
- Prefixes: `noreply`, `no-reply`, `donotreply`, `bounce`

Return: unique emails, mailto wale pehle.

---

## `LeadFinderService` (purana)

SerpAPI se Google search.

```php
$leadFinder->find('web design agency London', 'us', 'en');
// [['url' => '...', 'title' => '...'], ...]  max 25
```

- Query: `"{keyword} contact email"`
- 10 results per page, 25 tak paginate
- Skip list: google, facebook, linkedin, wikipedia, amazon, reddit... (~30 domains)
- API key: `config('services.serpapi.key')`

---

## `AIService` (purana)

OpenAI se personalised email.

```php
$ai->generateSubjectLine($lead, $senderCompany);
$ai->generateOutreachEmail($lead, $senderName, $senderCompany);
```

`researchCompany()` internally lead ki website se context nikalta hai taaki email
personalised ho.

Config: `services.openai.key`, `services.openai.model` (default `gpt-3.5-turbo`)

---

## `ImapService` (purana)

Bheja hua email IMAP `Sent` folder me copy karta hai — taaki aapke mail client me bhi dikhe.

```php
$imap->copyToSentFolder(to, subject, htmlBody, fromName, fromEmail, attachments);
```

Config: `IMAP_HOST`, `IMAP_USERNAME`, `IMAP_PASSWORD`, `IMAP_FOLDER`...

---

## `EmailSenderService` (purana)

```php
$sender->dispatch($lead);      // ek lead ke liye SendEmailJob
$sender->dispatchAll(): int;   // saare eligible leads
```

> Compose modal ise **use nahi karta** — wo direct `Mail::send()` karta hai taaki user ko
> turant confirmation mile. Ye service automated/bulk sending ke liye hai.

---

## Service dependency graph

```
FinderController ──┐
ProcessBulkJob ────┼──▶ EmailFinderService
                   │         │
                   │         ├──▶ ScraperService ──────▶ (Guzzle)
                   │         ├──▶ EmailExtractorService
                   │         └──▶ EmailVerifierService ─▶ (DNS / SMTP)
                   │                    ▲
VerifierController ┴────────────────────┘

LeadController ────┬──▶ AIService ──────▶ (OpenAI)
                   ├──▶ ScraperService
                   ├──▶ EmailExtractorService
                   ├──▶ EmailSenderService ──▶ SendEmailJob
                   └──▶ ImapService ────────▶ (IMAP)

FindLeadsJob ─────────▶ LeadFinderService ──▶ (SerpAPI)
```

Saari services **concrete classes** hain (koi interface nahi), aur Laravel container
constructor injection se automatically resolve kar leta hai — koi manual binding
`AppServiceProvider` me register karne ki zaroorat nahi.
