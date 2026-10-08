[← Docs index](../README.md)

# Module: Verifier

**URL:** `/verifier`
Check karta hai ki email address asli hai aur mail le sakta hai ya nahi.

**Koi paid API use nahi hui** — saare checks khud implement kiye gaye hain.

---

## Files

| File | Kaam |
|---|---|
| `app/Http/Controllers/VerifierController.php` | Verify, history, export, clear |
| `app/Services/EmailVerifierService.php` | **Asli logic** — saare checks |
| `app/Models/EmailVerification.php` | History model + colour helper |
| `resources/views/verifier/index.blade.php` | Page + form + result panel JS |
| `resources/views/verifier/_history.blade.php` | History table partial |

**Routes:**
```php
GET     /verifier              → verifier.index        page + history
POST    /verifier/verify       → verifier.verify       ek address (JSON)
POST    /verifier/verify-many  → verifier.verify-many  list (JSON ya queue)
POST    /verifier/clear        → verifier.clear        history clear
DELETE  /verifier/{id}         → verifier.destroy      ek record
GET     /verifier/export       → verifier.export       CSV
```

---

## Verification kaise hoti hai

Checks **saste se mehnge** order me chalte hain, aur galat email par **turant ruk** jaate hain
(short-circuit). Matlab malformed address par DNS lookup ki cost lagti hi nahi.

```
email
  │
  ▼
① SYNTAX
  │  filter_var + local part ≤64 + total ≤254 + domain me dot + ".." nahi
  │  ✗ FAIL → invalid, score 0, "Malformed email address."  ⏹ STOP
  ▼
② DOMAIN + ③ MX          (ek hi DNS call, 24 ghante cached)
  │  getmxrr() → MX records
  │  na mile   → checkdnsrr() A / AAAA fallback
  │              (RFC 5321: A record wala host bhi mail le sakta hai)
  │  ✗ resolve nahi hua → invalid, score 0, "Domain does not exist."      ⏹ STOP
  │  ✗ koi mail server nahi → invalid, score 10, "accepts no mail"        ⏹ STOP
  ▼
④ DISPOSABLE
  │  ~45 throwaway providers (mailinator, yopmail, 10minutemail...)
  │  subdomain bhi catch hota hai (foo.mailinator.com)
  │  ✗ HAI → risky, score 20, "Disposable / throwaway inbox provider."    ⏹ STOP
  ▼
⑤ ROLE  +  ⑥ FREE       (flag set hote hain, abhi stop nahi)
  │  role: info@, support@, admin@, sales@... (~38 prefixes)
  │  free: gmail, yahoo, outlook... (~30 domains)
  ▼
⑦ SMTP PROBE  (optional — default OFF)
  │  mail server se asli baat: EHLO → MAIL FROM → RCPT TO
  │  ✗ server ne reject kiya (550/551/553/554) → invalid, score 5         ⏹ STOP
  │  ⚠ catch-all detect hua                    → risky, score 55          ⏹ STOP
  │  ? server ne jawab nahi diya               → unknown, score 45        ⏹ STOP
  ▼
FINAL VERDICT
  │  role-based hai?  → risky, score 60
  │  warna            → valid
  │                     score = 95 (SMTP confirmed) ya 80 (sirf domain checks)
  │                     free provider hai to −10
```

---

## Result — 4 statuses

| Status | Score | Matlab | Kya karein |
|---|---|---|---|
| `valid` | 80–95 | Syntax sahi, domain mail leta hai | Bhej sakte ho |
| `risky` | 20–60 | Real hai par risk hai — disposable / role / catch-all | Soch ke bhejo |
| `invalid` | 0–10 | Galat syntax, domain nahi, ya server ne reject kiya | Mat bhejo |
| `unknown` | ~45 | Server ne jawab hi nahi diya | Confirm nahi kar sakte |

**Score kaise banta hai:**

| Situation | Score |
|---|---|
| SMTP confirmed + business domain | 95 |
| SMTP confirmed + free provider | 85 |
| Sirf domain checks + business domain | 80 |
| Sirf domain checks + free provider | 70 |
| Role-based (info@, sales@) | 60 |
| Catch-all domain | 55 |
| SMTP ne jawab nahi diya | 45 |
| Disposable provider | 20 |
| No MX record | 10 |
| Server ne mailbox reject kiya | 5 |
| Malformed / domain hi nahi | 0 |

---

## 🔑 SMTP probe — default OFF kyun hai

**Ye module ka sabse important tradeoff hai.**

Mailbox exist karta hai ya nahi, ye confirm karne ka **sirf ek hi tarika** hai: recipient
ke mail server se asli SMTP conversation karo:

```
→ CONNECT mx.example.com:25
← 220 mx.example.com ESMTP
→ EHLO yourapp.com
← 250 OK
→ MAIL FROM:<verify@yourdomain.com>
← 250 OK
→ RCPT TO:<jamie@example.com>
← 250 OK            ← mailbox exist karta hai ✅
   ya
← 550 No such user  ← exist nahi karta ❌
```

**Problem:** iske liye **outbound port 25** chahiye. AWS, DigitalOcean, Google Cloud, aur
zyadatar consumer ISPs ise **block** karte hain (spam rokne ke liye).

Agar port block ho aur probe ON ho, to **har address** `unknown` aayega — matlab tool
bekaar. Isliye default `false` hai.

```env
VERIFY_SMTP_PROBE=false     # default
VERIFY_SMTP_TIMEOUT=8
VERIFY_SMTP_FROM="${MAIL_FROM_ADDRESS}"
```

Jahan port 25 khula ho (apna VPS, dedicated server), wahan `true` kar do.

**Jab tak OFF hai, UI khud batata hai:**

> ℹ️ **Mailbox probing is off.** Results confirm karte hain ki domain mail le sakta hai,
> lekin ye nahi ki wo individual mailbox exist karta hai.

Ye banner isliye zaroori hai — warna user `valid` dekh kar samajhta ki mailbox confirmed
hai, aur email bounce hone par confused hota.

### Catch-all detection

Agar SMTP probe ON hai aur `RCPT TO` ne `250 OK` diya, to ek **random address** bhi try
hota hai:

```
→ RCPT TO:<no-such-user-a3f9d2@example.com>
← 250 OK          ← ye to exist ho hi nahi sakta!
```

Agar wo bhi accept ho gaya, matlab domain **sab kuch accept** karta hai (catch-all).
Pehla "yes" ka koi matlab nahi tha. Isliye result `risky` hota hai, `valid` nahi.

---

## DNS caching

```php
Cache::remember('verify:dns:'.$domain, 86400, fn() => /* MX + A lookup */);
```

Ek domain ka DNS jawab **24 ghante** reuse hota hai.

**Faayda kitna:** ek hi company ke 500 addresses verify karo →
**1 DNS lookup**, 500 nahi. Bulk runs me ye bahut bada difference hai.

TTL `.env` se badal sakte ho: `VERIFY_CACHE_TTL=86400`

---

## Do input modes

### Single address

```
POST /verifier/verify   { "email": "jamie@example.com" }
   ↓
EmailVerifierService::verify()
   ↓
email_verifications me record (source = 'single', lead_id agar match kare)
   ↓
JSON: status, score, reason, colour, aur 8 checks ka breakdown
```

Result panel me **detailed breakdown** dikhta hai — har check ke saath icon:

| Check | ✅ | ❌ | ➖ |
|---|---|---|---|
| Syntax | Well-formed | Malformed | — |
| Domain resolves | DNS record found | No DNS record | — |
| MX records | mx1.example.com | None published | — |
| Mailbox (SMTP) | Accepted | Rejected | **Not probed** |
| Not catch-all | Specific mailboxes | Accepts everything | **Not probed** |
| Not disposable | Permanent provider | Throwaway | — |
| Not role-based | Individual mailbox | Shared mailbox | — |
| Business domain | Company domain | Free provider | — |

> ➖ (grey dash) **important hai** — ye "check hua hi nahi" dikhata hai, "fail hua" se alag.
> Code me ye `null` hai, `false` nahi. Agar dono ko ek jaisa dikhate to user samajhta ki
> mailbox check fail hua, jabki wo check chala hi nahi.

### Multiple addresses (paste)

```
POST /verifier/verify-many   { "emails": "a@x.com\nb@y.com, c@z.com" }
   ↓
parseList()  — newline / comma / semicolon / space se split, duplicate hata do
   ↓
Kitne hain?
   ├── 0            →  422 "No email addresses found in that list."
   ├── 1–10         →  turant verify, JSON me results wapas
   ├── 11–500       →  Bulk run ban jata hai  →  queue  →  progress page par redirect
   └── 500+         →  422 "CSV ke through Bulks module use karo"
```

**10 par cutoff kyun?** Har address ek DNS round trip (aur SMTP ON ho to ek SMTP round trip)
le sakta hai. 50 addresses inline karne par request timeout ho jayegi. Isliye lambi list
automatically [Bulks](bulks.md) ko chali jati hai — wahan progress bhi dikhta hai aur
user ko wait nahi karna padta.

**Bulks ka reuse:** `queueBulk()` wahi `Bulk` + `BulkItem` + `ProcessBulkJob` pipeline use
karta hai. Alag "batch verify" system nahi banaya — ek hi code path hai progress, results
aur export ke liye.

---

## History table

Har verification record hota hai — single, bulk, aur finder se aaya hua bhi.

**Filters:** search (`q`: email/domain), result (`status`), source (`single`/`bulk`/`finder`),
rows per page. Sab server-side, sab URL me.

**Signals column** — sirf jo signals fire hue wahi dikhte hain:

| Badge | Kab |
|---|---|
| `MX` (green) / `No MX` (red) | Hamesha |
| `Disposable` | Throwaway provider |
| `Role` | Shared mailbox |
| `Free` | Consumer provider |
| `Catch-all` | Domain sab accept karta hai |

Clean address par sirf `MX` dikhta hai — "no, no, no" ki line nahi.

**Row actions:** View lead (agar `lead_id` hai) · Open bulk run (agar `bulk_id` hai) · Delete

**Clear history** — active status filter respect karta hai. Matlab sirf `invalid` filter
laga kar Clear dabao to sirf invalid records hatenge.

---

## Prefill trick

Finder ki table se "Verify this address" click karo → `/verifier?q=jamie@example.com`

Wahan `$filters['q']` **do jagah** use hota hai:
1. History search box me (filtered history dikhti hai)
2. **Verify input box me** (address pehle se bhara hua)

Matlab user ko copy-paste nahi karna padta — bas Verify dabana hai.

---

## Security

| Risk | Kya kiya |
|---|---|
| Validation | `email` field par `string\|max:254` — `email` rule **nahi** |
| SQL injection | `likePattern()` se `%` `_` escape |
| XSS | JS me `esc()` helper |
| Bad filter input | `FiltersRequests` — `enumParam` whitelist se |

> **`email` validation rule kyun nahi lagaya?** Kyunki malformed address ke baare me poochna
> **bilkul valid use case** hai — verifier ka kaam hi yahi batana hai ki "ye galat hai".
> Agar Laravel ka `email` rule lagate to user ko "invalid email" validation error milta,
> aur wo kabhi jaan hi nahi pata ki hamara verifier bhi yahi kehta. Isliye string lete hain
> aur verifier khud `invalid` return karta hai — proper reason ke saath.

---

## Limitations

1. **SMTP OFF hai to "valid" = domain mail leta hai**, mailbox confirmed nahi
2. **Disposable list hardcoded hai** (~45 providers) — naye providers ke liye
   `EmailVerifierService::DISPOSABLE_DOMAINS` update karna padega
3. **Greylisting** — kuch servers pehli baar `450` dete hain; ye `unknown` aata hai
4. **Role detection heuristic hai** — `sales@` role hai, par `salesforce@` nahi
   (bare word par match hota hai, isliye `sales-uk@` bhi catch hota hai)
5. Catch-all detection ke liye SMTP probe zaroori hai
