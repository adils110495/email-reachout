# Documentation — AI Client Finder

Cold email outreach tool. Ye docs folder pura system explain karta hai — **kya banaya gaya
hai, kyun banaya gaya hai, aur har cheez kaam kaise karti hai.**

---

## Kahan se shuru karein

| Agar aap... | Ye padhein |
|---|---|
| Naye ho, pehli baar dekh rahe ho | Ye page (neeche full flow), phir [Architecture](architecture.md) |
| Kisi ek module ka kaam samajhna hai | [Modules](#modules) section se seedha us module par |
| Database schema chahiye | [Database](database.md) |
| Background job debug kar rahe ho | [Queue & Jobs](queue-jobs.md) |
| Naya list screen bana rahe ho | [Frontend](frontend.md) |
| Deploy / `.env` set kar rahe ho | [Configuration](configuration.md) |

---

## Index

### Core
| File | Kya hai |
|---|---|
| [architecture.md](architecture.md) | Tech stack, code layers, project conventions |
| [database.md](database.md) | Saare tables, columns, relations, migrations |
| [services.md](services.md) | Service classes — asli business logic kahan hai |
| [queue-jobs.md](queue-jobs.md) | Background jobs, queue worker, chunking |
| [frontend.md](frontend.md) | Blade layout, `ajax-filters.js`, CSS components |
| [configuration.md](configuration.md) | `.env`, `config/`, sidebar, routes |
| [security-and-performance.md](security-and-performance.md) | SSRF guard, timeouts, N+1, validation |

### Modules
| Module | URL | File |
|---|---|---|
| Dashboard | `/` | [modules/dashboard.md](modules/dashboard.md) |
| Finder | `/finder` | [modules/finder.md](modules/finder.md) |
| Verifier | `/verifier` | [modules/verifier.md](modules/verifier.md) |
| Bulks | `/bulks` | [modules/bulks.md](modules/bulks.md) |
| Leads | `/leads` | [modules/leads.md](modules/leads.md) |
| Settings | `/settings/*` | [modules/settings.md](modules/settings.md) |

---

## System kya karta hai

Business dhundo → unka contact email nikalo → check karo email sahi hai ya nahi →
personalised email bhejo → response track karo.

```
Keyword / Domain  ─→  Leads dhundo  ─→  Email address nikalo  ─→  Email verify karo
                                                                         │
                                                                         ▼
                                               AI se email likho  ─→  Send  ─→  Track
```

---

## Pura flow — ek nazar me

```
                        ┌──────────────────────────────────┐
                        │           DASHBOARD              │
                        │  sabka live summary ek jagah     │
                        └──────────────┬───────────────────┘
                                       │
        ┌──────────────────┬───────────┴──────────┬─────────────────────┐
        ▼                  ▼                      ▼                     ▼
   ┌─────────┐       ┌──────────┐          ┌───────────┐         ┌───────────┐
   │ FINDER  │       │ VERIFIER │          │   BULKS   │         │   LEADS   │
   │         │       │          │          │           │         │           │
   │ domain  │       │ single   │          │ CSV       │         │ keyword   │
   │ /person │       │ /list    │          │ upload    │         │ search    │
   └────┬────┘       └────┬─────┘          └─────┬─────┘         └─────┬─────┘
        │                 │                      │                     │
        ▼                 ▼                      ▼                     ▼
  EmailFinder       EmailVerifier         ProcessBulkJob          FindLeadsJob
   Service            Service              (queue, chunks)      ScrapeLeadEmailJob
        │                 │                      │                     │
        └────────┬────────┴──────────┬───────────┘                     │
                 ▼                   ▼                                 ▼
         ┌──────────────┐   ┌────────────────────┐              ┌────────────┐
         │    leads     │   │email_verifications │              │   leads    │
         └──────┬───────┘   └────────────────────┘              └─────┬──────┘
                │                                                     │
                └───────────────────────┬─────────────────────────────┘
                                        ▼
                            AIService → OutreachMail → SMTP
                                        ↓
                            ImapService (Sent folder copy)
                                        ↓
                                  lead_emails
```

---

## Module ek dusre se kaise jude hain

Ye 4 naye module alag-alag pages nahi hain — **ek hi data par kaam karte hain**:

```
FINDER  ──── email dhundhta hai ────→  leads table
   │                                      ▲
   │  har candidate verify karta hai      │  Bulk "find" run bhi
   ▼                                      │  yahi leads banata hai
VERIFIER ─── result likhta hai ───→  email_verifications
   │                                      │
   │  10 se zyada address?                │  Finder table har row ka
   ▼                                      │  latest verdict yahan se padhta hai
BULKS ──── queue par chalata hai ─────────┘
   │
   └──── "find" type run  ────→  leads table (naye leads)

DASHBOARD ──── in sabko padh kar summary dikhata hai
```

**Concrete examples:**

- Verifier me 50 address paste karo → automatically **Bulks** run ban jata hai
- Bulks me domains ka CSV daalo → **Finder** ka logic chalta hai → **Leads** ban jaate hain
- Finder ki table me har email ke aage jo badge dikhta hai → wo **Verifier** ka result hai
- Dashboard ke saare numbers → in teeno ke tables se aate hain

---

## Setup

```bash
make migrate     # 3 naye tables: bulks, bulk_items, email_verifications
make up          # containers
```

Details: [configuration.md](configuration.md)

> ⚠️ Bulk runs ke liye **queue worker chalu hona zaroori hai**. Docker ka `queue` service
> pehle se `--queue=emails,default` consume karta hai, isliye extra setup nahi chahiye.

---

## Naya kya hai vs pehle se kya tha

| | Module / File |
|---|---|
| **Naya** | Dashboard, Finder, Verifier, Bulks |
| **Naya** | `EmailVerifierService`, `EmailFinderService`, `ProcessBulkJob` |
| **Naya** | `bulks`, `bulk_items`, `email_verifications` tables |
| **Naya** | `FiltersRequests` trait |
| **Pehle se tha** | Leads, Settings (Templates / Platforms / Categories / Addresses) |
| **Pehle se tha** | `AIService`, `ScraperService`, `EmailExtractorService`, `ImapService` |
| **Modify hua** | `ScraperService` (time budget), `ajax-filters.js` (pagination + server search) |

Naye modules ne purane ka behaviour nahi toda — `ScraperService` ka naya parameter optional
hai, aur `ajax-filters.js` ke additions purane contracts ke upar hain.
