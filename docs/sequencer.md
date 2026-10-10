You are an autonomous senior Laravel architect and developer.

Build this entire project from start to finish without asking me for confirmation between phases.

IMPORTANT:
- This is a NEW standalone project.
- It has NO relationship with Posticy.
- Do not use or reference Posticy code, database, architecture, branding, or business logic.
- Work directly on the current project directory.
- Do not stop after a phase.
- Do not ask "Should I continue?"
- Do not ask "Do you want me to proceed?"
- Do not ask for approval between phases.
- Do not wait for confirmation.
- Automatically continue through every phase until the complete application is implemented, tested, and documented.
- If you encounter a reasonable implementation decision, make the best engineering decision yourself.
- Only stop if there is a genuine blocker that cannot be resolved from the available project/environment.
- If something is missing, first inspect the project/environment and try to solve it yourself.
- Never leave TODO placeholders for core functionality.
- Do not create fake/demo implementations for production functionality.

# PROJECT

Build a production-ready:

MAIL SEQUENCER / EMAIL OUTREACH AUTOMATION PLATFORM

using:

- PHP 8.3+
- Laravel
- MySQL 8+
- Redis
- Laravel Queue
- Laravel Scheduler
- Blade + Tailwind or Bootstrap
- SMTP
- IMAP
- REST API
- Nginx-compatible production architecture

Use the latest stable Laravel version compatible with the installed PHP version unless the existing project already has a Laravel version.

---

# AUTONOMOUS EXECUTION RULE

Execute the following phases sequentially:

PHASE 1
Architecture

PHASE 2
Project foundation

PHASE 3
Authentication and users

PHASE 4
Email accounts

PHASE 5
Contacts and lists

PHASE 6
Email templates

PHASE 7
Sequences and sequence steps

PHASE 8
Sequence enrollment

PHASE 9
Queue system

PHASE 10
Scheduler

PHASE 11
Email sending

PHASE 12
Duplicate protection and concurrency

PHASE 13
Sending limits and throttling

PHASE 14
Open tracking

PHASE 15
Click tracking

PHASE 16
Unsubscribe

PHASE 17
IMAP reply detection

PHASE 18
Bounce detection

PHASE 19
Automatic sequence stopping

PHASE 20
Pause/resume

PHASE 21
Dashboard

PHASE 22
Analytics

PHASE 23
Bulk operations

PHASE 24
REST API

PHASE 25
Security hardening

PHASE 26
Automated testing

PHASE 27
Production configuration

PHASE 28
Documentation

After finishing one phase, automatically continue to the next phase.

Do NOT ask for confirmation.

---

# PHASE 1 — ARCHITECTURE

First inspect the environment and determine:

- PHP version
- Laravel version
- MySQL availability
- Redis availability
- Node/NPM availability
- Composer
- Existing project structure

Then design:

- Database architecture
- ERD
- Models
- Relationships
- Services
- Jobs
- Events
- Listeners
- Scheduler
- Queue architecture
- Mail provider architecture
- IMAP architecture
- Tracking architecture
- Security architecture

Do not over-engineer.

Use a clean modular Laravel architecture.

After designing it, immediately implement it.

---

# PHASE 2 — PROJECT FOUNDATION

Configure:

- Laravel
- Environment
- Database
- Redis
- Queue
- Cache
- Sessions
- Mail
- Logging

Create the required configuration.

Do not expose secrets.

Use `.env.example`.

---

# PHASE 3 — AUTHENTICATION

Implement:

- Registration
- Login
- Logout
- Password reset
- User profile
- User settings
- Timezone

Users must only access their own data.

Use Laravel's standard authentication/security practices.

---

# PHASE 4 — EMAIL ACCOUNTS

Create an email account management system.

Users can add:

- Account name
- Provider
- From name
- From email
- SMTP host
- SMTP port
- SMTP username
- SMTP password
- Encryption
- IMAP host
- IMAP port
- IMAP username
- IMAP password
- IMAP encryption

Encrypt credentials.

Never store passwords in plain text.

Create an abstraction:

EmailProviderInterface

Implement:

SmtpEmailProvider

Design the system so later providers can be added:

- Gmail
- Microsoft
- Amazon SES
- Mailgun
- SendGrid

without changing sequence logic.

Add:

Test Connection

for SMTP and IMAP.

---

# PHASE 5 — CONTACTS

Create contacts.

Fields:

id
user_id
first_name
last_name
email
company
website
phone
country
job_title
status
custom_fields
created_at
updated_at

Statuses:

active
unsubscribed
bounced
invalid
archived

Add:

- Create
- Edit
- Delete
- Search
- Filter
- Pagination
- Bulk actions

Prevent duplicate contacts per user where appropriate.

---

# PHASE 6 — CONTACT LISTS

Create lists.

Example:

Potential Clients
Publishers
Agencies
SaaS Companies

Support:

- Create list
- Rename
- Delete
- Add contacts
- Remove contacts
- Bulk add

A contact can belong to multiple lists.

---

# PHASE 7 — CSV IMPORT

Implement CSV import.

Example:

first_name,last_name,email,company,website

Features:

- Upload CSV
- Preview
- Validate
- Detect duplicate emails
- Detect invalid emails
- Show import statistics
- Confirm import
- Queue large imports
- Import result report

Do not process large CSV files synchronously.

---

# PHASE 8 — EMAIL TEMPLATES

Create reusable templates.

Fields:

id
user_id
name
subject
body
created_at
updated_at

Support:

{{first_name}}
{{last_name}}
{{email}}
{{company}}
{{website}}
{{country}}
{{job_title}}
{{sender_name}}
{{sender_email}}

Create:

TemplateRendererService

All variable replacement must go through this service.

---

# PHASE 9 — SEQUENCES

Create sequences.

Fields:

id
user_id
name
description
status
timezone
sending_start_time
sending_end_time
sending_days
daily_limit
created_at
updated_at

Statuses:

draft
active
paused
archived

---

# PHASE 10 — SEQUENCE STEPS

Each sequence has multiple steps.

Fields:

id
sequence_id
step_number
subject
body
delay_minutes
delay_hours
delay_days
status
created_at
updated_at

Allow:

- Add
- Edit
- Delete
- Reorder
- Duplicate
- Preview

Example:

Step 1:
0 minutes

Step 2:
2 days

Step 3:
3 days

Step 4:
4 days

---

# PHASE 11 — ENROLLMENT

Create:

sequence_enrollments

Fields:

id
sequence_id
contact_id
email_account_id
current_step
status
started_at
next_action_at
completed_at
stopped_at
stop_reason
created_at
updated_at

Statuses:

pending
active
paused
completed
replied
bounced
unsubscribed
removed
failed

Allow:

- Add contact
- Add list
- Bulk enroll
- Remove
- Pause
- Resume

---

# PHASE 12 — QUEUE

Every email must be sent through Laravel Queue.

Never send sequence emails directly from HTTP requests.

Create appropriate jobs, for example:

SendSequenceEmailJob

The job must:

1. Load enrollment
2. Lock enrollment where necessary
3. Verify sequence is active
4. Verify enrollment is active
5. Verify contact is active
6. Check unsubscribe
7. Check bounce
8. Check current step
9. Check daily limit
10. Check sending window
11. Render template
12. Send email
13. Create email log
14. Update enrollment
15. Calculate next_action_at

---

# PHASE 13 — SCHEDULER

Laravel Scheduler must run every minute.

The scheduler finds:

active enrollments

where:

next_action_at <= now()

Then dispatches queue jobs.

The scheduler must NOT send emails itself.

Flow:

Cron
↓
Laravel Scheduler
↓
Find due enrollments
↓
Dispatch jobs
↓
Redis
↓
Queue workers
↓
Send email

Configure:

routes/console.php

or the correct scheduler location for the Laravel version.

Provide production cron configuration.

---

# PHASE 14 — DUPLICATE EMAIL PROTECTION

This is critical.

Prevent duplicate emails caused by:

- Multiple workers
- Queue retries
- Scheduler overlap
- HTTP double-click
- Application restart
- Worker crash
- Race conditions

Use:

- DB transactions
- Row locking
- Unique constraints
- Idempotency
- Laravel unique jobs where appropriate

A sequence step must have a reliable idempotency mechanism.

Example concept:

One enrollment + one step + one execution = one send attempt.

Design this properly.

---

# PHASE 15 — SENDING WINDOWS

Users can configure:

Monday-Friday
09:00-17:00

If an email becomes due outside the window:

move it to the next allowed time.

Respect:

user timezone
sequence timezone
sending days
sending hours

Do not send outside configured hours.

---

# PHASE 16 — DAILY LIMIT

Allow:

100 emails/day

or configurable value.

Daily limit must be enforced on backend.

Multiple queue workers must not bypass the limit.

Use Redis/database atomic operations or another reliable mechanism.

---

# PHASE 17 — RATE LIMITING

Support configurable sending speed.

Example:

10 emails/minute

Do not allow concurrent workers to bypass rate limits.

Use Redis locking/throttling where appropriate.

---

# PHASE 18 — EMAIL LOGS

Create:

email_logs

Track:

user_id
sequence_id
sequence_step_id
contact_id
email_account_id
message_id
from_email
to_email
subject
status
sent_at
opened_at
clicked_at
replied_at
bounced_at
error_message
metadata

Statuses:

queued
sending
sent
failed
bounced
opened
clicked
replied

---

# PHASE 19 — MESSAGE ID

Generate a unique Message-ID for every outgoing email.

Store it in:

email_logs

Use it for reply matching.

---

# PHASE 20 — OPEN TRACKING

Implement optional tracking pixel.

Example:

/track/open/{secure-token}

Store:

opened_at

Do not expose database IDs.

Use secure random tracking tokens.

Prevent obvious token enumeration.

---

# PHASE 21 — CLICK TRACKING

Rewrite trackable links to:

/track/click/{secure-token}

Record:

clicked_at
destination_url

Then redirect.

Do not break:

- mailto:
- tel:
- unsubscribe
- existing tracking URLs

---

# PHASE 22 — UNSUBSCRIBE

Every sequence email must support unsubscribe.

Create secure unsubscribe tokens.

Endpoint:

/unsubscribe/{token}

When user unsubscribes:

contact.status = unsubscribed

Stop all active enrollments for that contact.

No future email may be sent.

---

# PHASE 23 — IMAP REPLY DETECTION

Implement real IMAP reply detection.

Create:

ReplyDetectionService

It should:

1. Connect to configured IMAP account
2. Find new incoming messages
3. Identify sender
4. Match sender with contact
5. Check In-Reply-To
6. Check References
7. Check Message-ID
8. Match email log
9. Mark email as replied
10. Stop active enrollment

Architecture:

Scheduler
↓
CheckIncomingRepliesJob
↓
ReplyDetectionService
↓
IMAP
↓
Match Reply
↓
Update EmailLog
↓
Update Enrollment
↓
Stop Sequence

Do not repeatedly process the same incoming email.

Store a unique identifier for processed inbound messages.

---

# PHASE 24 — BOUNCE DETECTION

Implement bounce detection.

Support the architecture for:

- SMTP bounce
- IMAP bounce

When bounce detected:

contact.status = bounced

active enrollment:

status = bounced

stop_reason = email_bounced

No future sequence email.

---

# PHASE 25 — AUTOMATIC STOP CONDITIONS

A sequence must stop automatically when:

Recipient replies
OR
Recipient bounces
OR
Recipient unsubscribes
OR
Contact is removed
OR
Enrollment is manually stopped

Every SendSequenceEmailJob must re-check these conditions immediately before sending.

Never trust stale queue data.

---

# PHASE 26 — PAUSE / RESUME

Support:

Pause sequence
Resume sequence

Pause enrollment
Resume enrollment

When paused:

No email is sent.

When resumed:

Continue from current step.

Do not restart from step 1.

---

# PHASE 27 — COMPLETION

When the final sequence step is successfully processed:

enrollment.status = completed

completed_at = now()

No further job should be scheduled.

---

# PHASE 28 — DASHBOARD

Create a modern dashboard.

Show:

Contacts
Sequences
Emails sent
Emails scheduled
Replies
Bounces
Unsubscribes

Charts:

Sent
Opened
Clicked
Replied
Bounced

---

# PHASE 29 — SEQUENCE ANALYTICS

Show:

Total enrolled
Active
Completed
Replied
Bounced
Unsubscribed
Removed

Calculate:

Open Rate
Click Rate
Reply Rate
Bounce Rate

Avoid division by zero.

---

# PHASE 30 — ACTIVITY TIMELINE

Show:

Email queued
Email sent
Email opened
Link clicked
Reply received
Bounce received
Sequence stopped

Example:

08 Oct
Email sent

08 Oct
Opened

09 Oct
Clicked

10 Oct
Reply received

10 Oct
Sequence stopped

---

# PHASE 31 — BULK OPERATIONS

Support:

Bulk import
Bulk enroll
Bulk remove
Bulk pause
Bulk resume
Bulk unsubscribe

Large operations must use queues.

---

# PHASE 32 — REST API

Create API endpoints for:

Authentication
Contacts
Lists
Sequences
Sequence steps
Enrollment
Pause
Resume
Email logs

Use Laravel API authentication.

Document endpoints.

---

# PHASE 33 — SECURITY

Implement:

Authentication
Authorization
CSRF
Validation
Rate limiting
Encrypted email credentials
Secure tracking tokens
Secure unsubscribe tokens
Mass assignment protection
XSS protection
SQL injection protection

Never expose SMTP/IMAP passwords.

---

# PHASE 34 — TESTING

Create comprehensive automated tests.

Test:

Authentication
Contacts
CSV import
Lists
Templates
Sequences
Steps
Enrollment
Immediate email
Delayed email
Queue
Scheduler
Daily limits
Rate limits
Duplicate prevention
Concurrent execution
Open tracking
Click tracking
Unsubscribe
Reply detection
Bounce detection
Pause
Resume
Completion

Use realistic test scenarios.

---

# PHASE 35 — FAILURE RECOVERY

Implement proper recovery for:

SMTP failure
IMAP failure
Redis failure
Database temporary failure
Queue failure
Network failure

Use retries where appropriate.

Do not retry permanent email failures indefinitely.

Use exponential backoff where appropriate.

Log failures clearly.

---

# PHASE 36 — PRODUCTION

Prepare:

Nginx
PHP-FPM
MySQL
Redis
Supervisor
Cron

Create deployment documentation.

Supervisor should run queue workers.

Cron should execute Laravel scheduler.

Document:

Environment variables
Queue configuration
Redis
Database
SMTP
IMAP
Scheduler
Workers

---

# PHASE 37 — FINAL AUDIT

Before declaring the project complete, inspect the entire implementation.

Check:

- No missing migrations
- No missing relationships
- No broken routes
- No undefined classes
- No missing imports
- No duplicate logic
- No insecure credentials
- No duplicate email possibility
- Queue works
- Scheduler works
- Reply detection works
- Unsubscribe works
- Bounce handling works
- Tracking works
- Tests pass

Run appropriate commands such as:

composer install

php artisan migrate

php artisan optimize

php artisan route:list

php artisan config:clear

php artisan cache:clear

php artisan test

and project-specific checks.

Do NOT run destructive commands such as:

php artisan migrate:fresh
php artisan db:wipe

unless absolutely required and explicitly safe for this new project.

---

# ERROR HANDLING RULE

If a command fails:

1. Read the complete error.
2. Determine the cause.
3. Fix it.
4. Run the command again.
5. Continue automatically.

Do not stop just because a test or migration fails.

Do not ask me to fix normal development errors.

---

# DECISION RULE

If multiple reasonable approaches exist:

Choose the simplest production-safe approach.

Do not ask me which option I prefer.

Document the decision afterward.

---

# UI RULE

Build a clean professional SaaS-style interface.

Use:

- Responsive layout
- Sidebar
- Top navigation
- Cards
- Tables
- Filters
- Search
- Pagination
- Status badges
- Modals/forms where useful
- Empty states
- Loading states
- Error states

Keep UI consistent across the application.

---

# CODE QUALITY

Follow:

- SOLID principles
- Laravel conventions
- PSR standards
- Service-oriented business logic
- Form Requests
- Policies
- Events/Listeners where appropriate
- Jobs for asynchronous work
- Transactions for critical operations

Avoid:

- Massive controllers
- Duplicate business logic
- Raw SQL unless justified
- Hardcoded credentials
- Hardcoded URLs
- Hardcoded timezone
- Hardcoded email limits

---

# AUTONOMOUS MODE — FINAL RULE

This instruction is extremely important:

DO NOT ASK FOR CONFIRMATION BETWEEN PHASES.

After Phase 1:

→ automatically start Phase 2.

After Phase 2:

→ automatically start Phase 3.

Continue automatically through every phase.

If a phase encounters an error:

→ diagnose
→ fix
→ test
→ continue.

If an implementation decision is required:

→ make the best production-safe decision
→ document it
→ continue.

Do not say:

"Would you like me to continue?"

"Should I proceed?"

"Please confirm."

"Ready for the next phase?"

Never wait for my response.

The only time you may stop is when there is a genuine external blocker that cannot be solved autonomously, such as missing credentials that are absolutely required to test a real external service.

Even then, implement everything else possible first.

---

# FINAL DELIVERABLE

When all phases are complete, provide one final report containing:

1. Project architecture
2. Database schema
3. Major features
4. Queue architecture
5. Scheduler architecture
6. Email architecture
7. IMAP architecture
8. Tracking architecture
9. Security implementation
10. Files created
11. Files modified
12. Commands to run locally
13. Production deployment steps
14. Environment variables required
15. Test results
16. Known limitations
17. Future extension points

Do not stop before reaching the final audit unless an unavoidable external blocker exists.

START NOW.