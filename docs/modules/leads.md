[← Docs index](../README.md)

# Module: Leads

**URL:** `/leads`
Keyword se leads dhundhna, AI se email likhwana, send karna, track karna.

> Ye module **pehle se maujood tha**. Naye modules (Finder / Verifier / Bulks) isi ke
> `leads` table par kaam karte hain. Yahan sirf itna documented hai jitna baaki modules
> samajhne ke liye zaroori hai.

---

## Files

| File | Kaam |
|---|---|
| `app/Http/Controllers/LeadController.php` | Poora module (477 lines) |
| `app/Jobs/FindLeadsJob.php` | SerpAPI search, background |
| `app/Jobs/ScrapeLeadEmailJob.php` | Per-lead email scraping |
| `app/Jobs/SendEmailJob.php` | Queued email send |
| `app/Mail/OutreachMail.php` | Mailable |
| `resources/views/leads/index.blade.php` | Page (bada — search + table + modals) |
| `resources/views/leads/_table.blade.php` | Table partial |
| `resources/views/emails/outreach.blade.php` | Email ka HTML template |

---

## ⚠️ URL badla hai

| Pehle | Ab |
|---|---|
| `/` | `/leads` |

Route ka **naam** wahi hai (`leads.index`), sirf path badla. Poore project me links
`route('leads.index')` se bane hain, isliye **kahin kuch break nahi hua**.

`/` ab [Dashboard](dashboard.md) hai.

---

## Flow 1 — Lead discovery

```
User keyword daalta hai: "web design agency London"  + category choose karta hai
   │
   ▼
POST /search  →  LeadController::search()
   │
   ▼
FindLeadsJob::dispatch(keyword, categoryId, country, language)
   │  (request turant return ho jati hai — user spinner par nahi baithta)
   ▼
Redirect: "Searching in the background. New leads will appear here."


─── QUEUE PAR ───────────────────────────────────────────────

FindLeadsJob
   │
   ▼
LeadFinderService::find()
   │  SerpAPI → Google search: "{keyword} contact email"
   │  10 results per page, 25 tak paginate karta hai
   │  Skip: google, facebook, linkedin, wikipedia, amazon... (~30 domains)
   ▼
Har result par:
   │  website duplicate hai? → skip
   │  Lead::create(company_name, website, email = NULL, status = new)
   │  ScrapeLeadEmailJob::dispatch($lead)     ← alag job
   ▼
Log: found / new_leads count


─── HAR LEAD KE LIYE ALAG JOB ────────────────────────────────

ScrapeLeadEmailJob
   │  email pehle se hai? → return
   ▼
ScraperService::fetch($lead->website)
   ▼
EmailExtractorService::extract($html)
   ▼
Mila? → $lead->update(['email' => $emails[0]])
```

**Email scraping alag job me kyun hai?** Kyunki ek slow website baaki 24 leads ko rok deti.
Alag job hone se har lead independently process hota hai, aur ek ka fail hona baaki ko
affect nahi karta.

---

## Flow 2 — Email bhejna

```
User "Compose" dabata hai
   │
   ▼
GET /leads/{id}/compose
   │
   ▼
AIService (OpenAI)
   ├── generateSubjectLine()      → subject
   └── generateOutreachEmail()    → body
   │      (researchCompany() pehle website se context nikalta hai)
   ▼
Modal me subject + body prefill (user edit kar sakta hai) — body CKEditor me
   │  Template dropdown se ready-made template bhi choose kar sakta hai
   │    (placeholders {{client_name}} etc. usi waqt bhar jaate hain)
   │  "Insert" chips se placeholder cursor par daal sakta hai
   │  Attachments add kar sakta hai
   ▼
POST /send-email/{id}       body = editor ka HTML
   │
   ▼
Validate: subject, body (khaali editor nahi), attachments (max 10 MB each)
   │
   ▼
Bache placeholders bharo + body saaf karo
   (EmailPlaceholders::fill + EmailHtml::render — docs/modules/settings.md)
   │
   ├── Lead ka email hai?      ✗ → error
   ├── Pehle se sent hai?      ✗ → error
   ▼
Attachments store: storage/app/lead-attachments/{lead_id}/
   ▼
OutreachMail banao  →  Mail::to()->send()      ← DIRECT, queue nahi
   ▼
ImapService::copyToSentFolder()   ← IMAP Sent folder me copy
   ▼
$lead->update(['status' => 'sent'])
   ▼
LeadEmail::create(subject, body, attachments, status = sent, sent_at)
   │
   └── ✗ Exception → status = failed, LeadEmail status = failed
```

**Direct send kyun, queue nahi?** Kyunki user modal me baith kar wait kar raha hai aur
turant confirmation chahta hai ki email gaya ya nahi. `SendEmailJob` abhi bhi exist karta
hai (bulk/automated sending ke liye), par compose modal direct bhejta hai.

---

## Table

| Feature | Detail |
|---|---|
| Filters | Status, Category, Platform (Select2, URL-driven) — `LeadController::filters()` / `filtered()` |
| **Default status** | **New** — bina `status` ke list sirf New leads (jinhe abhi email nahi gaya). `status=all` = sab. Galat value = New |
| Live search | Client-side (`data-live-filter`) |
| Sorting | Client-side column sort |
| Bulk actions | Select all → bulk delete / bulk status change · **Bulk Email** (neeche) |
| Mail activity | Status badge ke neeche latest email ka haal: ⏳ Queued · ✉ Not opened · 📬 Opened 3× · ↩ Replied · ⚠ Last email failed (hover = reason). `Lead::latestEmail()`, eager-loaded — ek query |
| Pagination | 10/25/50/100 |
| Export | CSV |
| Row actions | View · Edit · Compose · Mark sent · Delete. Bulk email queue me ho to **Send Email** nahi dikhta (do email chale jaate) |

> **Category filter zaroori hai** — bina category choose kiye table khaali dikhti hai
> ("Select a category to view leads"). Ye deliberate hai — bina filter ke hazaaron leads
> load karna slow hota.

### Sidebar — status ke links

Sidebar me **Leads** ab group hai: **New Leads** (default) · Sent · Failed · Replied · All Leads
(`config/navigation.php`, `query` + `default` keys — [configuration.md](../configuration.md)).

- Leads page par hote hue ye links **page reload nahi karte** — sirf status badalte hain, category / platform wahi rehte hain (`data-leads-status`, `leads/index.blade.php`). Ctrl/Cmd-click = naya tab, normal link.
- Status filter dropdown, URL aur sidebar ka highlight teeno saath chalte hain (`leads:loaded` event, back button bhi).
- Kisi aur page se link kholo to category nahi hoti → "Select a category" — category chuno, status wahi rahega.

### Bulk Email

Card header me **Email N selected** (checkbox wali rows) aur **Bulk Email** (current filters ki
saari leads). Wahi modal aur engine jo Deals ka Bulk Send hai — sirf Email channel
([whatsapp.md](whatsapp.md) › Bulk send, `Concerns\QueuesBulkSend`, `EmailBulkService`).

| Cheez | Kaise |
|---|---|
| Endpoint | `POST /leads/bulk-email` (`leads.bulk-email`, `throttle:10,1`) → `LeadController::bulkEmail()` |
| Kisko | Lead ka **main** email (`email` column). Bina email wali leads skip |
| "All matching" | `filtered()` — jo list me dikh raha hai: default **New**, us category / platform ki. **Category zaroori** — bina category list khaali hai, to bulk bhi mana karta hai |
| Skip already emailed | Default on — `sent` / `replied` leads aur jinhe pehle email gaya, skip |
| Editor / placeholders | CKEditor + `{{client_name}}` = lead ka company name |
| Lead status | Job email bhejte hi lead **Sent** (`SendBulkEmailJob::updateLead()`); fail ho aur lead New / Failed thi to **Failed**. `replied` kabhi nahi badalta |
| Progress / Stop | Toast ka **Email progress** → Email Activity `?batch=`. List reload hoti hai, rows par "Queued" dikhta hai |
| Log | `lead_emails` me `lead_id` + `to_email` ke saath — Email Activity, open tracking, reply check sab normal email jaisa |

---

## Naye modules se connection

```
FINDER ─── "Save" button ────────────→ leads table
   │                                       ▲
   │  Finder ki table me har lead ka       │
   │  verification badge dikhta hai        │
   ▼                                       │
VERIFIER ─── lead_id link ───→ email_verifications
                                           │
BULKS ("find" run) ────────────────────────┘
       naye leads banata hai
```

| Naya module | Leads ke saath kya karta hai |
|---|---|
| **Finder** | Save button se lead banata/update karta hai; table me leads dikhata hai |
| **Verifier** | Verify karte waqt `lead_id` match karke link karta hai |
| **Bulks** (`find`) | Naye leads banata hai (category ke saath) |
| **Dashboard** | Leads ke stats aur pipeline dikhata hai |

**Sab jagah ek hi rule:** existing lead ka email **kabhi overwrite nahi hota**. Sirf khaali
ho to bhara jata hai.

---

## Lead statuses

| Status | Kab set hota hai |
|---|---|
| `new` | Lead banate waqt (default) |
| `sent` | Email successfully gaya (compose ya Bulk Email), ya user ne manually "Mark sent" kiya |
| `failed` | Email bhejte waqt exception aaya (Bulk Email: sirf New / Failed lead par) |
| `replied` | **Manually set hota hai** — koi automatic reply detection nahi hai |

> Dashboard ka "reply rate" `replied` leads ÷ sent emails hai. Kyunki `replied` manual hai,
> ye tabhi accurate hoga jab user status update kare.
