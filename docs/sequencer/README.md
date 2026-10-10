# Mail Sequencer

Multi-step cold-email sequences with per-account SMTP sending, open/click tracking,
one-click unsubscribe, and IMAP reply/bounce detection that stops a sequence automatically.

The sequencer is built **on top of the app's existing modules**. It does not keep a parallel set of
contacts, lists, templates or accounts. The only new concepts are sequences, steps and enrollments.

| Sequencer concept | Existing module it uses | Table |
|---|---|---|
| Contact | **Leads** (gained first/last name, job title, phone, country, custom fields, an email status and an unsubscribe token) | `leads` |
| List | **Categories** (a lead can now be in several; `leads.category_id` stays the primary one) | `categories` + `category_lead` |
| Template | **Email Templates** (same `{{variables}}` everywhere) | `email_templates` |
| Sending account | **Settings > Mail Settings** (now several accounts, each with SMTP + IMAP, limits and health; one is the default) | `mail_settings` |
| Email log | **Email Activity** (sequence sends and Leads compose sends are the same rows) | `lead_emails` |
| CSV import, bulk enroll/pause/resume/remove/unsubscribe | **Bulks** (new types next to verify/find) | `bulks` + `bulk_items` |
| Open/click tracking | **TrackingController** | `lead_emails`, `tracked_links` |
| Reply/bounce detection | **ReplyCheckerService**, `emails:check-replies` | `inbound_messages` |
| Queue | the existing **`queue`** worker on Redis | queues `emails`, `default` |

Data is **team-shared**: every signed-in user sees and manages the same leads, sequences and accounts,
as the rest of the app already worked.

| Doc | What it covers |
|---|---|
| this file | architecture, data model, queue/scheduler design, duplicate protection, decisions |
| [api.md](api.md) | REST API reference |
| [deployment.md](deployment.md) | production setup: Nginx, PHP-FPM, MySQL, Redis, Supervisor, cron, env vars |
| [flow-and-testing.md](flow-and-testing.md) | end-to-end flow and a step-by-step manual test plan (Hinglish) |

---

## 1. Architecture at a glance

```
Browser / API client
   │  Blade UI: /outreach/sequences, /leads, /bulks, /settings/mail, /email-activity, /
   │  REST: /api/v1/*  (Sanctum tokens)
   ▼
Controllers ──► Form Requests (validation)
   │
   ▼
Services
   ├── app/Sequencer/Services
   │     ├── EnrollmentService        every enrollment state change (enroll/pause/resume/stop/complete)
   │     ├── SequenceEmailProcessor   sends ONE step of ONE enrollment, safely (the core)
   │     ├── SendingWindowService     days/hours/timezone maths
   │     ├── DailyLimitService        atomic per-day quotas (MySQL)
   │     ├── SendRateLimiter          per-minute speed (Redis INCR)
   │     ├── EmailComposer            render + link rewrite + unsubscribe footer + pixel + headers
   │     ├── TemplateRendererService  the ONLY place {{variables}} are replaced (sequences and Leads compose)
   │     ├── TrackingService / UnsubscribeService / ActivityRecorder
   │     └── SequenceService / StepService / AnalyticsService
   └── app/Services (existing, expanded)
         ├── MailConfigService        per-account SMTP transport, connection tests, default account
         ├── ImapService              Sent-folder copy, connection test, read-only UID-cursor fetch
         ├── ReplyCheckerService      reply + bounce matching for every sent email
         └── BulkImportService        CSV draft → preview/mapping → resumable import into leads

   EmailProviderInterface ── SmtpEmailProvider (Symfony Mailer, per-account credentials)
       (registry: EmailProviderManager - add Gmail/Microsoft/SES/... without touching sequences)

Events ─► Listeners: ActivitySubscriber (timeline), StopEnrollmentOnReply,
                     HandleHardBounce, StopEnrollmentsOnUnsubscribe
```

### Scheduler → queue → SMTP flow

```
scheduler (schedule:work in Docker, or cron schedule:run)
  ├─ sequencer:dispatch-due   every minute     finds due enrollments, takes a lease,
  │                                            pushes SendSequenceEmailJob → Redis queue "emails"
  ├─ emails:check-replies     every 2 minutes  pushes CheckIncomingRepliesJob per IMAP account → "default"
  └─ sequencer:prune          daily 03:30

queue worker  (queue:work redis --queue=emails,default)
  ├─ emails  : SendSequenceEmailJob → SequenceEmailProcessor → SMTP     (+ the existing Leads SendEmailJob)
  └─ default : CheckIncomingRepliesJob → ReplyCheckerService → IMAP
               ProcessBulkJob (verify / find / import / enroll / pause / resume / remove / unsubscribe)
```

The scheduler never sends mail, and HTTP requests never send sequence mail.

---

## 2. Data model

```
leads ──────────────┬─< category_lead >─ categories      many-to-many; leads.category_id = primary
  contact_status     ├─< sequence_enrollments >─ sequences ─< sequence_steps ─ (optional) email_templates
  unsubscribe_token  │        UNIQUE(sequence_id, lead_id)     created_by → users, mail_setting_id
                     └─< lead_emails  UNIQUE(enrollment_id, sequence_step_id)  ← idempotency key
                              │  sequence_id, sequence_step_id, enrollment_id, mail_setting_id
                              └─< tracked_links  (random token → stored URL)
mail_settings        SMTP + IMAP (encrypted), limits, health, IMAP cursor, is_default
inbound_messages     every IMAP message seen; UNIQUE per mailbox UID and per Message-ID
activity_events      timeline (queued, sent, opened, clicked, reply, bounce, stopped…)
daily_send_counters  UNIQUE(scope, scope_id, day) - atomic quotas
bulks / bulk_items   existing; new types import/enroll/pause/resume/remove/unsubscribe, status draft, options json
users                + timezone, settings; personal_access_tokens (Sanctum); password_reset_tokens
```

Migrations: `database/migrations/2026_10_12_00000{1..6}_*.php` and the Sanctum migration. They
**expand** the existing tables and migrate existing data:

- The legacy separate `smtp` and `imap` rows in `mail_settings` are merged into one default account
  (passwords stay encrypted; `folder` stays the Sent folder).
- Every lead gets a random unsubscribe token; `category_id` is copied into `category_lead`.
- `lead_emails.to_email` is backfilled from the lead.

Every migration has a working `down()`.

### Two lead statuses

| Column | Meaning | Values |
|---|---|---|
| `leads.status` | outreach progress (existing, shown on the Leads page) | new, sent, failed, replied |
| `leads.contact_status` | may we email this address? | active, unsubscribed, bounced, invalid, archived |

`contact_status` is system-managed and never mass-assignable. Unsubscribed can never be changed back,
from the UI, the API or a re-import.

### Other statuses

| Entity | Values |
|---|---|
| Sequence | draft, active, paused, archived |
| Step | active, inactive (skipped) |
| Enrollment | pending, active, paused, completed, replied, bounced, unsubscribed, removed, failed |
| `lead_emails.status` (delivery) | queued, sending, sent, failed, bounced |
| Engagement | derived from `first_opened_at`, `clicked_at`, `replied_at`, `bounced_at` (`LeadEmail::engagement()`) |

`stop_reason`: `replied`, `email_bounced`, `unsubscribed`, `contact_removed`, `contact_inactive`,
`manually_stopped`, `send_failed`, `delivery_uncertain`.

---

## 3. Sending one step: `SequenceEmailProcessor`

**Phase A** runs in one DB transaction with the enrollment row locked (`SELECT … FOR UPDATE`). It re-reads
everything and never trusts the queued job:

1. The enrollment exists, is `active`, and `next_action_at <= now`.
2. The lead still exists and its `contact_status` is `active`. Otherwise the enrollment stops with
   `unsubscribed`, `email_bounced`, `contact_removed` or `contact_inactive`.
3. The sequence is `active`. If it is paused, draft or archived, nothing is sent and the enrollment keeps its place.
4. The mail setting exists and is active. An inactive account defers the send by 15 minutes; a missing account fails the enrollment.
5. The next active step after `current_step` is found. If there is none, the enrollment completes.
6. Idempotency: if a `lead_emails` row already exists for this (enrollment, step):
   - **sent/bounced**: progress is repaired and nothing is sent.
   - **sending** less than 15 minutes old: another worker owns it, so skip.
   - **sending** older than that: stop with `delivery_uncertain` and do not re-send (see §4).
7. The sending window (sequence timezone, days, hours) is checked. If closed, `next_action_at` moves to the next opening.
8. The per-minute rate is checked (Redis INCR). If over, the send is deferred to the next minute.
9. The daily limits (sequence day in the sequence's timezone, account day in `app.timezone`) are reserved atomically. If full, the send is deferred to the next day's window.
10. The `lead_emails` row is inserted (status `sending`). `UNIQUE(enrollment_id, sequence_step_id)` makes a second insert impossible.
11. The email is rendered and stored on the row.

**Phase B** is the SMTP call, with no DB locks held. Afterwards a copy is appended to the account's IMAP
Sent folder (best effort), as the Leads compose window already did.

**Phase C** marks the row `sent` immediately (a single UPDATE, retried), moves the lead from new/failed
to `sent`, then advances the enrollment: `current_step = N`, and `next_action_at = now + next step's delay`
pushed into the window, or `completed`.

Failures:

| Error | Classification | Result |
|---|---|---|
| 4xx, timeout, network, auth (530/534/535) | transient | row back to `queued`; retried with backoff 30s → 2m → 10m → 30m; after 5 tries the enrollment is `failed` |
| 550/551/553 at RCPT | hard bounce | row `bounced`, lead `bounced`, all of the lead's enrollments stop |
| other 5xx | permanent | row `failed`, enrollment `failed`, never retried |
| template render error | permanent | as above |

Quota slots are returned whenever nothing left the server.

## 4. Duplicate protection (defence in depth)

| Threat | Guard |
|---|---|
| Scheduler overlap / runs every minute | `withoutOverlapping`, plus an atomic **lease** (`dispatch_lease_until`) taken with a conditional UPDATE before dispatch |
| Same job queued twice | `SendSequenceEmailJob` is `ShouldBeUnique` per enrollment (Redis lock) |
| Multiple workers | `SELECT … FOR UPDATE` on the enrollment row serialises them |
| Queue retries / worker restarts | the processor re-checks the row: an already `sent` step is never re-sent |
| HTTP double-click | `UNIQUE(sequence_id, lead_id)` on enrollments; activate/pause/resume and import confirm are idempotent |
| Race at insert time | `UNIQUE(enrollment_id, sequence_step_id)` on `lead_emails`: **one enrollment + one step = one send attempt**, enforced by MySQL |
| Worker dies after SMTP accepted, before "sent" is recorded | a row stuck in `sending` for 15+ minutes is **not** re-sent: it becomes `failed / delivery_uncertain` and a person can choose **Retry** |

The deliberate trade-off is **at-most-once** delivery per step: a rare crash costs one follow-up, never a duplicate.

`tests/Feature/Sequencer/ConcurrencyTest.php` checks this with real OS processes, each with its own
MySQL connection: 8 workers racing for one enrollment send exactly one email; 10 workers walking 60
enrollments under a daily limit of 20 send exactly 20; the per-minute rate holds across processes.

## 5. Limits

- **Daily limit per sequence** (`sequences.daily_limit`, required) and an optional **daily cap per account**
  (`mail_settings.daily_limit`). These live in `daily_send_counters` and are updated with
  `UPDATE … SET count = count + 1 WHERE count < limit`, so they are exact under any number of workers and
  survive a Redis flush.
- **Rate per minute per account** (`mail_settings.rate_limit_per_minute`, default 10) uses a Redis counter per
  account per clock minute. The scheduler also dispatches at most `rate` jobs per account per run.
- If Redis is down, the rate check fails closed: the send is deferred 60 seconds, never sent unthrottled.

## 6. Sending windows

`SendingWindowService::nextAllowed()` uses the sequence's timezone (or its creator's), the allowed ISO
weekdays and a start/end time with an exclusive end. It handles DST: 09:00 local stays 09:00 local.
Anything due outside the window, including a job that sat in a backed-up queue, moves to the next
opening and is not sent late.

## 7. Tracking & unsubscribe

- **Open:** `GET /t/o/{token}.gif` (also the legacy `/track/open/{token}`) returns a 1×1 GIF with `no-store`.
  Unknown tokens get the same GIF and database ids are never exposed. Works for sequence and Leads compose emails.
- **Click:** every `http(s)` link in a sequence email is rewritten to `/track/click/{token}`. The destination is
  stored server-side, so the endpoint is not an open redirect; unknown tokens get 404. `mailto:`, `tel:`,
  `#anchors` and unsubscribe links are never rewritten. A click also counts as an open.
- **Unsubscribe:** every sequence email gets a footer link (unless the author placed `{{unsubscribe_url}}`)
  plus `List-Unsubscribe` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click` headers.
  `GET /unsubscribe/{token}` only shows a confirmation page, because link scanners prefetch links.
  `POST` unsubscribes, and RFC 8058 one-click clients POST directly (CSRF-exempt; the 48-char token is the credential).
  Unsubscribing sets the lead `unsubscribed` and stops every open enrollment. The Leads compose window refuses
  to email unsubscribed, bounced or invalid leads too.
- Tracking can be turned off per sequence (`track_opens`, `track_clicks`).

## 8. Reply & bounce detection (IMAP)

`emails:check-replies` (existing command, expanded) → `CheckIncomingRepliesJob` per active IMAP account →
`ReplyCheckerService::pollAccount()`:

1. It reads new messages since the stored UID cursor (`imap_last_uid` plus `imap_uid_validity`). The first poll
   only looks back `SEQUENCER_IMAP_LOOKBACK_DAYS`. At most 200 messages per run; the mailbox is opened
   read-only, so nothing is marked as seen. TLS certificates are validated unless `IMAP_VALIDATE_CERT=false`.
2. Each message is classified:
   - **Bounce** if it comes from MAILER-DAEMON/postmaster, has a `multipart/report` delivery-status, an empty
     return path, or a bounce subject. The original email is found by the Message-ID inside the DSN, or else
     by `Final-Recipient`. It is **hard** for 5.x.x statuses except mailbox-full, too-big and policy (5.7.x),
     and **soft** for 4.x.x or `Action: delayed`. A soft bounce only records the error.
   - **Reply** if `In-Reply-To` or `References` matches a `lead_emails.message_id` sent from that account.
     Otherwise, the sender is matched to a lead that was emailed in the last 60 days.
   - **Auto-reply** (`Auto-Submitted`, `X-Autoreply`, `Precedence`, out-of-office subjects) is recorded and ignored.
3. Each message is recorded in `inbound_messages`, unique by (account, folder, UIDVALIDITY, UID) and by
   (account, Message-ID). A message is never processed twice, even after a cursor reset.
4. Effects: reply → `replied_at` on the email, lead status `replied`, enrollment `replied`.
   Hard bounce → email `bounced`, lead `contact_status=bounced`, every open enrollment of that lead stops.

This covers **every** sent email, both sequence steps and one-off emails from the Leads page.

A failed poll records `imap_ok=false` and `imap_last_error` on the account (shown on Mail Settings) and
retries on the next cycle.

`RealMailServerIntegrationTest` covers this end to end against a real SMTP/IMAP server (GreenMail).

## 9. Security

- Authentication is the existing session login (username or email), plus password reset by email,
  profile, timezone and settings. The API uses **Sanctum** bearer tokens that expire after 30 days.
- Authorization: the app is team-shared, so every signed-in user can manage all leads, sequences and
  accounts. Every page and API route requires authentication; only tracking and unsubscribe are public,
  and those use unguessable random tokens.
- SMTP/IMAP passwords use Laravel's `encrypted` cast (APP_KEY). They are hidden from serialization and API
  resources, never rendered (blank on edit means keep), and scrubbed from error messages.
- `PublicHost` blocks private, loopback and link-local SMTP/IMAP hosts, which prevents SSRF through
  "Test connection". It is off in local/testing; set `SEQUENCER_ALLOW_PRIVATE_HOSTS` to change it.
- `$fillable` is explicit everywhere. `contact_status`, `unsubscribe_token` and similar columns are never
  mass-assigned (tested).
- XSS: Blade escapes everything. Template values are HTML-escaped when inserted into HTML bodies.
  Previews render in `<iframe sandbox srcdoc>`. Subjects are stripped of CR/LF to prevent header injection.
- SQL: Eloquent and the query builder only. LIKE wildcards in search are escaped.
- Rate limits: API 120/min per user, API login 5/min per login+IP, tracking 600/min per IP,
  unsubscribe 30/min, password reset 5/min.
- CSV: extension and MIME are checked, uploads are stored privately (`storage/app/bulk-uploads`), and
  spreadsheet formula injection in every CSV export is neutralised.
- CSRF is on for all web forms except the RFC 8058 one-click unsubscribe POST.

## 10. Decisions log

| Decision | Why |
|---|---|
| Expand existing modules instead of new tables for contacts, lists, templates, accounts and logs | One source of truth: a lead imported, emailed or unsubscribed anywhere is the same lead everywhere |
| Team-shared data, no per-user scoping | Matches how the rest of the app works |
| `leads.status` and `leads.contact_status` kept separate | The Leads page's outreach progress keeps its meaning; deliverability state is a separate, system-managed fact |
| Sequence jobs on the existing Redis queue (`emails`, `default`), `after_commit=true` | One worker service; jobs dispatched inside transactions only run after commit |
| MySQL holds the daily quota; Redis holds only the per-minute rate and locks | A cache flush must never reset a day's usage |
| At-most-once per step | A duplicate cold email is worse than a missed follow-up; "Retry" lets a person decide |
| Deferrals update `next_action_at` instead of `release()`-ing jobs | Window, limits and retries all become data |
| A reply stops only that enrollment; a bounce or unsubscribe stops all of the lead's enrollments | A reply is about one conversation; a bounce or opt-out is about the address |
| CSV import is a Bulk with a draft → preview → confirm flow, processed in resumable slices | Reuses the Bulks page; no job outlives the queue `retry_after`, whatever the file size |
| ext-imap for IMAP (existing `ImapService`) | Already used and bundled in the Docker image. On PHP 8.4+, install PECL imap |
| One timezone for everything: India Standard Time (`APP_TIMEZONE=Asia/Kolkata`) | `now()`, the scheduler, every date in the UI, user and sequence defaults, and the MySQL session (`+05:30`) all follow it. Every date column is a `TIMESTAMP` (an absolute instant), so rows written under the old UTC setting read back correctly with no data change. Two safety nets keep a time from another zone from being stored or compared shifted: `Date::useCallable` (every Laravel date is converted to the app zone) and `App\Database\MySqlConnection` (every bound date is). `tests/Feature/Sequencer/TimezoneTest.php` covers it |

## 11. Running it locally (Docker)

```bash
docker compose up -d redis queue scheduler       # Redis, the queue worker and the scheduler
docker exec ai_client_finder_app php artisan migrate
docker compose restart queue                     # after code or .env changes
# (not `php artisan queue:restart`: restart is disabled in docker-compose.yml, so that would
#  stop the worker for good and sequence emails would silently stop going out)
# open http://localhost:${APP_PORT}/outreach/sequences
```

Tests use a separate schema, `cold_email_test`, and refuse to run against anything else:

```bash
docker exec ai_client_finder_mysql mysql -uroot -p -e "CREATE DATABASE IF NOT EXISTS cold_email_test; GRANT ALL ON cold_email_test.* TO 'sail'@'%';"
docker exec ai_client_finder_app php artisan test
# optional real mail server test:
docker run -d --rm --name sequencer_test_greenmail --network cold-email-reach-out_app_network \
  -e GREENMAIL_OPTS="-Dgreenmail.setup.test.smtp -Dgreenmail.setup.test.imap -Dgreenmail.hostname=0.0.0.0 -Dgreenmail.auth.disabled" greenmail/standalone:2.0.1
docker exec -e SEQUENCER_TEST_MAIL_HOST=sequencer_test_greenmail ai_client_finder_app php artisan test --filter=RealMailServer
```

Useful commands:

```bash
php artisan sequencer:dispatch-due                  # what the scheduler runs every minute
php artisan emails:check-replies --sync             # poll IMAP inline and print results
php artisan emails:check-replies --account=3
php artisan queue:work redis --queue=emails,default
php artisan queue:failed                            # infrastructure failures (business failures never throw)
```

## 12. Known limitations / extension points

- Only the SMTP provider is implemented. Gmail/Microsoft (OAuth), SES, Mailgun and SendGrid plug in via
  `EmailProviderManager::extend()`. API providers can report bounces through webhooks by firing `BounceDetected`.
- One sending account per enrollment; there is no inbox rotation yet.
- The sending window uses the sequence's timezone, not each lead's.
- Open tracking is approximate (image blocking, Apple Mail Privacy Protection prefetch).
- A crash between SMTP acceptance and the "sent" UPDATE yields `delivery_uncertain` (by design, see §4).
- No per-user data separation (team-shared by choice); adding it means scoping queries by `created_by`.
- A/B variants, conditional branches and per-lead timezones are not implemented.
