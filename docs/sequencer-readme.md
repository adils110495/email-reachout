# Sequences — Flow

Ye file sirf ek cheez batati hai: **Sequences (multi-step cold email) ka flow** — kaun sa part kya karta
hai, kab karta hai, aur ek email shuru se end tak kaise chalta hai.

Testing ke steps: [sequencer/flow-and-testing.md](sequencer/flow-and-testing.md) ·
Technical design (locks, limits, DB): [sequencer/README.md](sequencer/README.md)

---

## 1. Naya kya hai, purana kya use hua

Sequences ke liye naye contacts / lists / templates / accounts **nahi** banaye. Pehle se jo modules the,
unhi ko expand kiya:

| Sequence ko chahiye | Kahan se aata hai (existing module) |
|---|---|
| Kisko email bhejna hai | **Leads** (ab first/last name, job title, custom fields, email status bhi) |
| Leads ke groups | **Categories** (ek lead ab kai categories me ho sakta hai) |
| Email ka content | **Email Templates** ya step me seedha likha subject/body |
| Kis mailbox se bhejna hai | **Settings > Mail Settings** (ab kai accounts, SMTP + IMAP, ek default) |
| Bheje gaye emails ki list | **Email Activity** |
| CSV import, bulk enroll / pause / unsubscribe | **Bulks** |
| Open / click tracking | **TrackingController** |
| Reply / bounce check | **`emails:check-replies`** + ReplyCheckerService |

Sirf ye cheeze nayi hain: **Sequence**, **Step**, **Enrollment**, aur **Timeline**.

---

## 2. Bade picture me

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
                                                                     Reply / Bounce / Unsubscribe par
                                                                     us lead ke liye sequence RUK jata hai
```

---

## 3. Har part ka kaam

| Part | Kahan | Kaam |
|---|---|---|
| **Sequence** | Sequences > All Sequences | Emails ki chain + schedule: din, time, timezone, daily limit, tracking on/off |
| **Step** | Sequence ke andar | Ek email (subject + body) + "pichhle email ke kitni der baad bhejna hai" |
| **Enrollment** | Sequence > Leads | Ek lead ka ek sequence me hona; har lead ka apna progress aur next send time |
| **Scheduler** | `scheduler` container | Har minute due enrollments dhundh kar queue me daalta hai. **Khud email nahi bhejta** |
| **Queue worker** | `queue` container | Asli email bhejta hai, IMAP check karta hai, Bulks chalata hai |
| **Email Activity** | Email Activity | Har bheja gaya email — Leads page wale aur sequence wale dono |
| **Timeline** | Sequences > Timeline | Har event ki history: enrolled, sent, opened, clicked, replied, stopped… |

---

## 4. Sequence ka lifecycle

```
  New sequence ──► DRAFT ──Activate──► ACTIVE ◄──Resume── PAUSED
                     │                   │  └────Pause─────►  │
                     │                   ▼                    │
                     └──────────────► ARCHIVED ◄──────────────┘
```

- **Draft**: steps bana sakte ho, koi email nahi jata. Activate tabhi hota hai jab kam se kam 1 active step ho.
- **Active**: due emails jate hain.
- **Paused**: kuch nahi jata; har lead apni jagah par rukta hai. Resume par wahin se chalu.
- **Archived**: band. Naye leads enroll nahi hote.

---

## 5. Lead ko enroll karna

Teen tareeke — teeno ka result same hai:

```
 A) Sequence > Leads > "Enrol category"  ─┐
 B) Leads page > leads tick > "Enroll"    ─┼──► Bulks run (background) ──► har lead ke liye:
 C) API  POST /api/v1/sequences/{id}/enrollments ─┘                          │
                                                                              ▼
                                              Lead ka email hai? email status "active" hai?
                                              Sequence archived to nahi? Steps hain? Mail account hai?
                                                     │ haan                         │ nahi
                                                     ▼                              ▼
                                       enrollment ACTIVE                     SKIPPED (reason ke saath,
                                       next send = abhi + step 1 ka wait      Bulks report me dikhta hai)
                                       (window ke andar shift)
```

Ek lead ek sequence me sirf **ek baar** ho sakta hai. Dobara enroll karna duplicate nahi banata.

Ek **email address** bhi ek sequence me sirf ek baar jata hai: agar Leads me do rows ka email same hai,
to doosra lead **Skipped** hota hai (reason: "duplicate email in sequence") — warna ek hi inbox ko har
step do baar milta.

Bulks page par har row me lead ka naam + email dikhta hai (naam par click = us lead ki Timeline), aur run
khatam hote hi results table apne aap update ho jati hai.

---

## 6. Ek email kaise jata hai

```
 Har minute:  Scheduler ──► "next send <= abhi" wale active enrollments
                              │  (har ek par 5 min ka lease, taaki dobara pick na ho)
                              ▼
                       SendSequenceEmailJob ──► Redis queue "emails"
                              │
                              ▼
 Queue worker ──► enrollment row LOCK karta hai, phir sab dobara check karta hai (neeche table)
                              │
                              ▼
                  Email Activity me row banti hai (status "sending")
                  Subject/body me {{variables}} bharte hain, links tracking wale bante hain,
                  unsubscribe footer + headers lagte hain
                              │
                              ▼
                  SMTP se bheja ──► status "sent", copy mailbox ke Sent folder me
                              │
                              ▼
                  Lead status "sent" ──► agla step ka time set (ya enrollment "completed")
```

### Bhejne se pehle ke checks

Koi bhi check fail ho to email **nahi** jata:

| # | Check | Fail hone par |
|---|---|---|
| 1 | Enrollment abhi bhi `active` hai aur time aa gaya hai | kuch nahi hota |
| 2 | Lead ka email status `active` hai (unsubscribed / bounced / invalid / archived nahi) | enrollment stop |
| 3 | Sequence `active` hai | wait; lead apni jagah par rehta hai |
| 4 | Mail account active hai | 15 min baad dobara try |
| 5 | Agla step bacha hai | nahi bacha → enrollment `completed` |
| 6 | Ye step is lead ko pehle hi nahi gaya | **duplicate kabhi nahi jata** |
| 7 | Abhi sending window ke andar hai (din + time + sequence ka timezone) | agli window opening par shift |
| 8 | Per-minute limit (mail account) | 1 min baad |
| 9 | Daily limit (sequence + mail account) | kal ki window par shift |

### Bhejte waqt error aaye to

| Error | Kya hota hai |
|---|---|
| Temporary (timeout, 4xx, network) | 30s → 2m → 10m → 30m baad dobara; 5 baar fail → enrollment `failed` (Retry kar sakte ho) |
| Address exist nahi karta (550/551/553) | email `bounced`, lead `bounced`, lead ke saare sequences stop |
| Doosra permanent error (5xx) | email `failed`, enrollment `failed` |
| Worker beech me crash | step "uncertain" mark hota hai — **dobara apne aap nahi bhejta** (duplicate se bachne ke liye) |

---

## 7. Ek lead ki poori journey (example)

Sequence: **Step 1** (wait 0) → **Step 2** (wait 2 din) → **Step 3** (wait 3 din).
Schedule: Mon–Fri, 09:00–17:00, Asia/Kolkata.

```
Mon 10:00  Lead enroll hua ──► enrollment "active", next send = Mon 10:00
Mon 10:01  Scheduler: "due hai" ──► job queue me
Mon 10:01  Worker: checks ✔ ──► Step 1 bheja ──► Email Activity me row, Lead status "sent"
           next send = Wed 10:01  (2 din baad)
Tue 15:30  Lead ne email khola ──► pixel load ──► "Opened"
Wed 10:02  Step 2 bheja ──► next send = Sat 10:02 ... Sat window me nahi
           ──► apne aap Mon 09:00 par shift
Thu 11:00  Lead ne REPLY kiya
Thu 11:02  IMAP check ──► reply match ──► email "Replied", Lead status "replied",
           enrollment "replied" ──► Step 3 KABHI nahi jayega
```

---

## 8. Tracking ka flow

```
 Open:   email me chhupi 1x1 image  ──► /t/o/{token}.gif  ──► "Opened", open count +1
 Click:  email ke links            ──► /track/click/{token} ──► click count +1 ──► asli website par redirect
                                                                (click = open bhi)
```

- Links ka asli URL server par save hota hai, isliye koi bhi is redirect ko misuse nahi kar sakta.
- Sequence settings me "Track opens" / "Track link clicks" band kar sakte ho.
- Open tracking approximate hai: kai email apps images block karte hain, aur Apple Mail apne aap khol deta hai.

---

## 9. Reply aur bounce ka flow (IMAP)

```
 Har 2 min (ya Email Activity > "Check Replies" button):
   emails:check-replies ──► har IMAP wale Mail account ke liye ek job
        │
        ▼
   Inbox me SIRF naye messages padhta hai (pichhli baar jahan ruka tha wahan se),
   read-only — kuch "read" mark nahi hota
        │
        ▼
   Har message:
     ├── Bounce hai? (mailer-daemon, "Delivery failed", delivery report)
     │      ├── hard (address hi nahi hai)  ──► email "bounced", lead "bounced", saare sequences stop
     │      └── soft (mailbox full, temporary) ──► sirf note, kuch nahi rukta
     ├── Auto-reply / Out of office? ──► sirf record, kuch nahi rukta
     └── Reply hai? (hamare email ka Message-ID match, ya lead ke address se aaya)
            ──► email "Replied", lead "replied", us sequence ka enrollment "replied" (stop)
```

- Ye **har** bheje gaye email par kaam karta hai — sequence wale aur Leads page se bheje gaye dono.
- Ek message kabhi do baar process nahi hota.
- IMAP fail ho to error Mail Settings par dikhta hai, aur agli baar dobara try hota hai.

---

## 10. Unsubscribe ka flow

```
 Har sequence email me: footer link + "List-Unsubscribe" header (Gmail ka Unsubscribe button)
        │
        ▼
 Link khola (GET) ──► sirf confirmation page (kuch nahi badalta — email scanners bhi links kholte hain)
        │
        ▼
 "Yes, unsubscribe me" (POST) ──► lead email status "unsubscribed" ──► lead ke SAARE sequences stop
```

- Unsubscribe **permanent** hai: UI, API ya CSV re-import se wapas "active" nahi hota.
- Leads page se bhi unsubscribed / bounced lead ko email nahi bheja ja sakta.

---

## 11. CSV import ka flow (Bulks)

```
 Bulks > Import > CSV upload ──► DRAFT (abhi koi lead nahi bana)
        │
        ▼
 Preview: kitne valid / invalid / duplicate + column mapping (email, first_name, company, custom…)
        │
        ▼
 "Import N rows" ──► PENDING ──► worker 1000-1000 rows ke tukdon me process karta hai
        │
        ▼
 Har row: imported / updated / duplicate / invalid ──► leads table + chuni hui category
        │
        ▼
 COMPLETED ──► Export se har row ka result download
```

- Same address ka lead dobara nahi banta.
- "Update existing" sirf khaali na hone wale cells se purana data update karta hai; unsubscribe status kabhi nahi badalta.

---

## 12. Sequence kab rukta hai — summary

| Event | Kya hota hai | Kiske liye |
|---|---|---|
| **Reply** | enrollment `replied` | sirf us sequence me |
| **Hard bounce** | lead `bounced`, enrollment `bounced` | lead ke **saare** sequences |
| **Unsubscribe** | lead `unsubscribed` (hamesha ke liye) | lead ke **saare** sequences |
| **Auto-reply / Out of office** | sirf record | kuch nahi rukta |
| **Soft bounce** | error note | kuch nahi rukta |
| Manual **Pause / Remove** | jaisa chuna | us enrollment / sequence ke liye |
| Saare steps ho gaye | enrollment `completed` | — |

---

## 13. Statuses ek nazar me

| Cheez | Status | Matlab |
|---|---|---|
| Lead → **Status** (purana) | new / sent / failed / replied | outreach progress (Leads table me) |
| Lead → **Email status** (naya) | active / unsubscribed / bounced / invalid / archived | kya is address par email bhej sakte hain? |
| Sequence | draft / active / paused / archived | upar section 4 |
| Enrollment | pending, active, paused, completed, replied, bounced, unsubscribed, removed, failed | ek lead ka progress |
| Email (Email Activity) | Not opened / Opened / Replied (+ Clicks) | engagement |

---

## 14. Timezone — sab kuch IST

Poora system **India Standard Time (Asia/Kolkata)** par chalta hai (`.env` me `APP_TIMEZONE=Asia/Kolkata`):

- Har page par dikhne wala time (Email Activity, Timeline, Bulks, Dashboard…) IST me.
- Naye users aur naye sequences ka default timezone IST. Sending window (jaise 09:00–17:00) IST ke hisaab se.
- Scheduler ke times (jaise roz 03:30 wala cleanup) IST me.
- Database bhi IST session me chalta hai, isliye purane records (jo UTC ke time likhe gaye the) bhi sahi IST time dikhate hain — koi data badla nahi gaya.

phpMyAdmin apna alag session use karta hai, isliye wahan raw times UTC (5:30 peeche) dikh sakte hain — app me sab IST hi hai.

---

## 15. Background me kya chalta hai

| Command | Kab | Kaam |
|---|---|---|
| `sequencer:dispatch-due` | har minute | due enrollments ko queue me daalna |
| `emails:check-replies` | har 2 minute | har IMAP account me naye replies / bounces |
| `sequencer:prune` | roz 03:30 | purane counters / inbound records saaf |

Ye tabhi chalte hain jab Docker me **`scheduler`** aur **`queue`** dono containers Up hon:

```bash
docker compose up -d scheduler queue
```
