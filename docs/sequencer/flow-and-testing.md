# Sequences — Flow aur Testing Guide

Ye file 2 cheeze batati hai:

1. **Flow** — ek email sequence shuru se end tak kaise chalta hai (kaun kya karta hai, kab karta hai).
2. **Testing** — local Docker par har feature ko step-by-step kaise test karein, aur har step par kya dikhna chahiye.

Technical detail (locks, limits, DB design) ke liye [README.md](README.md) padhein.

> ⚠️ **Testing sirf apne email addresses par karein.** Test leads me apna doosra Gmail / Outlook
> address daalein — kabhi bhi real prospect ko test email mat bhejo.

---

## Part 1 — Flow

### 1.1 Bade picture me

```
 SETUP (ek baar)                      ROZ KA KAAM                      AUTOMATIC (background)
 ───────────────                      ───────────                      ──────────────────────
 Settings > Mail Settings             Leads add / CSV import           Scheduler (har minute)
   SMTP + IMAP account                  (Leads page ya Bulks)            "kiska email due hai?"
        │                                     │                                │
        │                              Sequence banao                          ▼
        │                              + steps (emails)               Queue worker
        │                                     │                          email bhejta hai (SMTP)
        │                              Activate karo                           │
        │                                     │                                ▼
        │                              Leads ko Enroll karo  ─────►   Lead ke inbox me email
        │                                                                      │
        └────────────────────────────────────────────────────────►   Open / Click / Reply / Bounce
                                                                               │
                                                                     Tracking + IMAP check (har 2 min)
                                                                               │
                                                                     Reply/Bounce/Unsubscribe par
                                                                     sequence us lead ke liye RUK jata hai
```

### 1.2 Kaun kya karta hai

| Part | Kahan | Kaam |
|---|---|---|
| **Mail Settings** | Settings > Mail Settings | Kis mailbox se email jayega (SMTP) aur replies kahan padhne hain (IMAP) |
| **Leads** | Leads | Jinko email bhejna hai. Ab first name, job title, custom fields, aur *email status* bhi |
| **Categories** | Settings > Categories | Leads ke groups (list). Ek lead kai categories me ho sakta hai |
| **Email Templates** | Settings > Templates | Reusable subject/body — step banate waqt use kar sakte ho |
| **Sequence** | Sequences > All Sequences | Emails ki chain + schedule (din, time, timezone, daily limit) |
| **Step** | Sequence ke andar | Ek email + "kitna wait karna hai pichhle email ke baad" |
| **Enrollment** | Sequence > Leads | Ek lead ka ek sequence me hona. Har lead ka apna progress |
| **Scheduler** | `scheduler` container | Har minute due enrollments dhundh kar queue me daalta hai. **Khud email nahi bhejta** |
| **Queue worker** | `queue` container | Asli email bhejta hai, IMAP check karta hai, Bulks chalata hai |
| **Email Activity** | Email Activity | Har bheja gaya email (Leads page wale + sequence wale) — opens, clicks, replies |
| **Timeline** | Sequences > Timeline | Har event ki history (enrolled, sent, opened, replied, stopped…) |

### 1.3 Ek lead ki poori journey (example)

Sequence: **Step 1** (wait 0) → **Step 2** (wait 2 din) → **Step 3** (wait 3 din).
Schedule: Mon–Fri, 09:00–17:00, Asia/Kolkata.

```
Mon 10:00  Lead enroll hua ──► enrollment "active", next_action_at = Mon 10:00
Mon 10:01  Scheduler: "due hai" ──► SendSequenceEmailJob queue me
Mon 10:01  Worker: checks ✔ ──► Step 1 bheja ──► Email Activity me row, Lead status "sent"
           next_action_at = Wed 10:01  (2 din baad)
Tue 15:30  Lead ne email khola ──► pixel load ──► "Opened" (open count +1)
Wed 10:02  Step 2 bheja ──► next_action_at = Sat 10:02 ... lekin Sat window me nahi hai
           ──► automatically Mon 09:00 par shift
Thu 11:00  Lead ne REPLY kiya
Thu 11:02  IMAP check (har 2 min) ──► reply match ──► email "Replied", Lead status "replied",
           enrollment "replied" ──► Step 3 KABHI nahi jayega
```

### 1.4 Worker email bhejne se pehle kya check karta hai

Har email bhejne se pehle (agar koi bhi check fail ho to email **nahi** jata):

| # | Check | Fail hone par |
|---|---|---|
| 1 | Enrollment abhi bhi `active` hai aur time aa gaya hai | kuch nahi hota |
| 2 | Lead ka email status `active` hai (unsubscribed / bounced / invalid / archived nahi) | enrollment stop |
| 3 | Sequence `active` hai (paused / draft / archived nahi) | wait, jagah wahi rehti hai |
| 4 | Mail account active hai | 15 min baad dobara try |
| 5 | Agla step bacha hai | nahi bacha → enrollment `completed` |
| 6 | Ye step pehle hi nahi bheja gaya | **duplicate kabhi nahi jata** |
| 7 | Abhi sending window ke andar hai (din + time + timezone) | next window opening par shift |
| 8 | Per-minute limit (account) | 1 min baad |
| 9 | Daily limit (sequence + account) | kal ki window par shift |

### 1.5 Sequence kab rukta hai

| Event | Kya hota hai | Kiske liye |
|---|---|---|
| **Reply** | enrollment `replied` | sirf us sequence me |
| **Hard bounce** (address exist nahi karta) | lead `bounced`, enrollment `bounced` | lead ke **saare** sequences |
| **Unsubscribe** link click + confirm | lead `unsubscribed` (hamesha ke liye) | lead ke **saare** sequences |
| **Auto-reply / Out of office** | sirf record hota hai | kuch nahi rukta |
| **Soft bounce** (mailbox full, temporary) | error note hota hai | kuch nahi rukta |
| Manual **Pause / Remove** | jaisa chuna | us enrollment / sequence ke liye |
| Saare steps ho gaye | enrollment `completed` | — |

### 1.6 Statuses ek nazar me

| Cheez | Status | Matlab |
|---|---|---|
| Lead → **Status** (purana) | new / sent / failed / replied | outreach progress (Leads table me) |
| Lead → **Email status** (naya) | active / unsubscribed / bounced / invalid / archived | kya is address par email bhej sakte hain? |
| Sequence | draft → active ⇄ paused → archived | draft me kuch nahi jata |
| Enrollment | pending, active, paused, completed, replied, bounced, unsubscribed, removed, failed | ek lead ka progress |
| Email (Email Activity) | Not opened / Opened / Replied (+ Clicks column) | engagement |

### 1.7 Background schedule

| Command | Kab | Kaam |
|---|---|---|
| `sequencer:dispatch-due` | har minute | due enrollments ko queue me daalna |
| `emails:check-replies` | har 2 minute | har IMAP account me naye replies / bounces |
| `sequencer:prune` | roz 03:30 | purane counters / inbound records saaf |

---

## Part 2 — Testing (Manual, step-by-step)

Har test me: **Kya karna hai → Kya dikhna chahiye (✔ Expected)**.
Commands `app` container ke andar chalayein:

```bash
docker compose exec app bash        # WSL me project folder se
```

### Test 0 — Setup check (pehle ye)

```bash
docker compose ps                     # redis, queue, scheduler, app, web, mysql — sab "Up"
docker compose up -d scheduler queue  # agar scheduler / queue nahi chal rahe
php artisan migrate:status | tail -8  # 2026_10_12_* sab "Ran"
php artisan config:show app.url       # http://localhost:8075  (aapka browser wala port)
```

✔ Expected: sab containers Up, migrations Ran, `app.url` = jis port par site khulti hai.

> `APP_URL` galat ho to emails ke andar open / click / unsubscribe links galat port par jayenge.
> `.env` me `APP_URL` badalne ke baad: container ke andar `php artisan config:clear`, phir WSL se `docker compose restart queue`.
> (`php artisan queue:restart` **mat** chalao — is Docker setup me auto-restart band hai, to worker hamesha ke liye ruk jayega.)

---

### Test 1 — Mail account (Settings > Mail Settings)

1. Account par **Edit** (ya **Add Account**).
2. SMTP: host, port 587/TLS (ya 465/SSL), username, password, From address.
3. IMAP: host (jaise `imap.hostinger.com`), 993/SSL, username, password, Inbox folder `INBOX`, Sent folder (jaise `INBOX.Sent`).
4. **Test Connection** click karo.
5. **Save Account**.

✔ Expected:
- Test connection: SMTP ✔ aur IMAP ✔.
- List me account ke aage SMTP "OK", IMAP "OK", aur **Default** badge.
- Agar laal warning "password can't be read" dikhe → password dobara bharo (APP_KEY badla tha).

---

### Test 2 — Test lead banana (Leads)

1. Leads page par category select karo (rows category select karne ke baad hi dikhti hain).
2. **Add Lead**: Company, Website, Email = **aapka apna doosra email**, First name = `Test`, Category = koi test category.
3. Save.

✔ Expected: table me lead dikhe, naam ke neeche "Test …", email status badge **active**.

> 2–3 test leads bana lo (alag-alag apne addresses, jaise Gmail ke `yourname+t1@gmail.com`,
> `yourname+t2@gmail.com` — Gmail `+` wale sab address aapke hi inbox me aate hain).

---

### Test 3 — Sequence banana (Sequences > All Sequences)

1. **New sequence**.
2. Name: `Test Sequence`. Send from: aapka account.
3. Timezone: `Asia/Kolkata`. From `00:00` Until `23:59`. **Saare 7 din** tick karo
   (taaki test kabhi bhi chale). Daily limit: `50`. Track opens ✔, Track link clicks ✔.
4. **Save sequence**.
5. **Add the first email**:
   - Subject: `Hi {{first_name}} — test 1`
   - Body: `Hello {{first_name|there}} from {{company}}. Visit <a href="https://example.com">our site</a>.`
   - Wait: 0 days 0 hours 0 min → **Save step**.
6. **Add step** (step 2): Subject `Follow up {{first_name}}`, Wait: 0 days 0 hours **5 min** → Save.
7. Step par **Preview** click karo.

✔ Expected:
- Sequence status **Draft**, 2 steps dikhein.
- Preview me `{{first_name}}` ki jagah real/sample naam, neeche unsubscribe footer.

---

### Test 4 — Activate + Enroll

1. Sequence page par **Activate** → status **Active**.
2. Enroll — teen me se koi bhi tareeka:
   - **A)** Sequence page → **Leads (0)** → "Enrol leads" box me category chuno → **Enrol category**.
   - **B)** Leads page → category select → test leads tick karo → toolbar me sequence chuno → **Enroll**.
   - **C)** API (Test 15).
3. Bulks page kholo.

✔ Expected:
- Bulks me "Enroll in Test Sequence" run → kuch second me **Completed** (agar Pending hi rahe to `queue` container check karo).
- Sequence > Leads page par leads **active**, "Next send" ≈ abhi.

---

### Test 5 — Pehla email jana

1. 1–2 minute ruko (scheduler har minute chalta hai).
   Jaldi chahiye to: `php artisan sequencer:dispatch-due`
2. Apna inbox dekho.
3. **Email Activity** page dekho.
4. **Sequences > Timeline** dekho.

✔ Expected:
- Inbox me `Hi Test — test 1` (Spam folder bhi check karo).
- Email Activity me row: Activity **Not opened**, Sequence filter me "Test Sequence".
- Lead ka Status **sent**.
- Timeline: *Enrolled* → *Email queued* → *Email sent*.
- Aapke mailbox ke **Sent** folder me bhi copy.

---

### Test 6 — Open aur Click tracking

**Click:** email me "our site" link click karo (isi computer ke browser me).

✔ Expected: browser `localhost:8075/track/click/...` se ho kar `https://example.com` par pahunche.
Email Activity me Clicks = 1, aur email **Opened** bhi ho jata hai (click = open).

**Open:** Gmail images ko Google ke server se load karta hai, aur Google `localhost` tak nahi pahunch sakta —
isliye local par Gmail me sirf email kholne se "Opened" **nahi** hoga. Local test ke liye:
1. Gmail me email → ⋮ → **Show original**.
2. `/t/o/` wala URL copy karo (jaise `http://localhost:8075/t/o/abc...gif`).
3. Browser me kholo.

✔ Expected: Email Activity me **Opened**, Opens = 1, "Last opened" bhara hua.
(Live server par, public URL ke saath, ye apne aap hota hai.)

---

### Test 7 — Follow-up (step 2) aur window

1. Step 1 ke 5 minute baad wait karo (reply mat karna).

✔ Expected: `Follow up Test` email aaye. Sequence > Leads me "Step" 2, status **completed** (agar 2 hi steps hain).
Timeline me *Sequence completed*.

**Window test:** Ek naya sequence banao jiska window abhi ke time ke **bahar** ho
(jaise abhi 15:00 hai to From 09:00 Until 10:00). Lead enroll karo.

✔ Expected: email **nahi** jata. Sequence > Leads me "Next send" = kal 09:00 (ya next allowed day).

---

### Test 8 — Reply detection (sequence rukna)

1. Naya test lead enroll karo ek sequence me jiske step 2 ka wait **1 din** ho.
2. Step 1 aane ke baad, us email ko apne inbox se **Reply** karo (kuch bhi likh kar).
3. Email Activity → **Check Replies** (ya 2 minute wait — scheduler khud check karta hai).

✔ Expected:
- Button par "Checking…" spinner, phir "1 new reply found." — page reload nahi hota.
- Row ki Activity **Replied** + Replied time.
- Lead Status **replied**, enrollment **replied**, step 2 **kabhi nahi** jayega.
- Timeline: *Reply received* → *Sequence stopped*.

> **Out-of-office test:** Gmail me vacation responder on karke reply aane do → *Replied* **nahi** hona chahiye.

---

### Test 9 — Unsubscribe

1. Test email ke neeche **Unsubscribe** link click karo.
2. Confirmation page par **Yes, unsubscribe me** button dabao.

✔ Expected:
- Page: "You are unsubscribed".
- Lead ka email status **unsubscribed**, uske saare enrollments **unsubscribed** (stop).
- Leads page se us lead ko compose karke email bhejne ki koshish → error, email nahi jata.
- Lead edit karke status wapas "active" karna → **nahi hota** (unsubscribe permanent hai).

> Sirf link kholne (GET) se unsubscribe **nahi** hota — button dabana zaroori hai
> (email scanners links khol dete hain, isliye).

---

### Test 10 — Bounce

1. Ek lead banao jiska email **exist nahi karta**, jaise `this-does-not-exist-12345@gmail.com`.
2. Use enroll karo → step 1 jayega → kuch minute me mail server se "Delivery failed" email aapke inbox me aayega.
3. **Check Replies** click karo.

✔ Expected: Email **bounced**, lead email status **bounced**, uske saare enrollments stop.
Ye lead ab kisi sequence me enroll nahi hoga (skip reason `lead_bounced`).

---

### Test 11 — Pause / Resume / Remove / Retry

| Kya karo | ✔ Expected |
|---|---|
| Sequence par **Pause** | koi email nahi jata; leads apni jagah par rehte hain |
| **Resume** | jahan ruka tha wahin se chalu (purana time nikal gaya ho to turant) |
| Sequence > Leads → kisi lead par Pause / Resume / Remove | sirf us lead par asar |
| Failed enrollment par **Retry** | dobara try hota hai |
| Leads page → tick → bulk unsubscribe | Bulks me run, leads unsubscribed |

---

### Test 12 — Daily limit

1. Sequence ki Daily limit = `1`, 3 leads enroll karo.

✔ Expected: aaj sirf **1** email jaye. Baaki ka "Next send" = kal window opening.
(Dubara test se pehle limit wapas badha do.)

---

### Test 13 — CSV import (Bulks)

1. Ek CSV banao:
   ```
   first_name,last_name,email,company,website,plan
   Test,One,yourname+c1@gmail.com,Acme,acme.com,Pro
   Test,Two,not-an-email,Beta,,
   Test,Three,yourname+c1@gmail.com,Dup,,
   ```
2. Bulks → type **Import** → file + category chuno → upload.
3. Preview page: columns ka mapping check karo (`plan` → custom field) → **Import N rows**.

✔ Expected:
- Upload ke baad **Draft** + preview: 1 valid, 1 invalid, 1 duplicate. Tab tak koi lead nahi bana.
- Confirm ke baad run **Completed**: imported 1, invalid 1, duplicate 1.
- **Export** se har row ka result (Invalid email address / Duplicate email in file).
- Lead me `{{custom.plan}}` = Pro.
- Wahi file dobara import → duplicate lead **nahi** bante.

---

### Test 14 — Dashboard & Analytics

✔ Expected:
- Dashboard: Sequences cards (Emails Scheduled, Unsubscribes…) + opens/clicks/replies chart.
- Sequence → **Analytics**: sent, open rate, reply rate, per-step numbers.

---

### Test 15 — REST API (optional)

```bash
# login → token
curl -s -X POST http://localhost:8075/api/v1/auth/login -H 'Accept: application/json' \
  -d login=YOUR_USERNAME -d password=YOUR_PASSWORD

TOKEN=...   # upar wale response ka "token"

curl -s http://localhost:8075/api/v1/contacts -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
curl -s -X POST http://localhost:8075/api/v1/sequences/1/enrollments -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{"contact_ids":[1]}'
```

✔ Expected: contacts = aapke leads; enroll par `"outcome":"enrolled"`. Token ke bina `401`.
Poori API: [api.md](api.md).

---

### Test 16 — Automated tests (code check)

```bash
php artisan test                                   # ~140 tests, ~4 min, alag DB (cold_email_test) par
php artisan test --filter=SendPipeline             # sirf ek file
```

✔ Expected: sab **PASS**. Ye real data ko touch nahi karte (sirf `cold_email_test` database).

---

## Part 3 — Kuch nahi ho raha? (Troubleshooting)

| Problem | Check karo |
|---|---|
| Email ja hi nahi raha | `docker compose ps` — **scheduler** aur **queue** Up hain? Sequence **Active** hai? Abhi window ke andar hai? Enrollment active hai? |
| Bulks run "Pending" par atka | `queue` container chal raha hai? `docker compose logs --tail 50 queue` |
| Enrollment "failed" | Sequence > Leads me error; Mail Settings → Test Connection; phir **Retry** |
| "Skipped" enroll hone par | reason dekho: `lead_unsubscribed`, `lead_bounced`, `lead_has_no_email`, `duplicate_email_in_sequence` (doosra lead isi email ke saath pehle se is sequence me hai), `sequence_has_no_steps`, `no_mail_account` |
| Email jaane band ho gaye | `docker compose ps` — `ai_client_finder_queue` "Exited" hai? → `docker compose up -d queue` |
| Reply detect nahi hua | Mail Settings me IMAP "OK"? Reply **usi mailbox** me aaya jo account me set hai? **Check Replies** dabao |
| Open count 0 | Local par normal hai (Test 6 dekho). Gmail ka image proxy localhost nahi khol sakta |
| Click/unsubscribe link galat port | `.env` → `APP_URL`, phir `php artisan config:clear` aur `docker compose restart queue` |
| Code badla, worker purana chal raha | `docker compose restart queue` (`php artisan queue:restart` nahi — worker ruk jayega) |
| Mail Settings par "password can't be read" | APP_KEY badla tha → password dobara daalo. **APP_KEY kabhi mat badlo** |

### Useful commands

```bash
php artisan sequencer:dispatch-due          # scheduler ka kaam abhi (wait na karna ho to)
php artisan emails:check-replies --sync     # IMAP abhi check karo, result print
php artisan queue:failed                    # crash hue jobs
docker compose logs -f queue                # worker live
docker compose logs -f scheduler            # scheduler live
tail -f storage/logs/laravel.log            # app errors
```

### Testing ke baad safai

- Test sequence ko **Archive** karo (ya Delete).
- Test leads delete karo, ya unhe "archived" email status de do.
- Daily limit / window wapas normal karo.
