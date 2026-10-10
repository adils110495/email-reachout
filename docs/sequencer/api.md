# REST API (v1)

Base URL: `{APP_URL}/api/v1`. All responses are JSON. Send `Accept: application/json`.

The API uses generic names for the app's own records:

| API resource | App module / table |
|---|---|
| contacts | Leads (`leads`) |
| lists | Categories (`categories`, membership in `category_lead`) |
| email-logs | Email Activity (`lead_emails`) |
| email-accounts | Settings > Mail Settings (`mail_settings`) |

Data is shared by the whole team: any authenticated user can read and change any record.

## Authentication

Laravel Sanctum bearer tokens, valid for 30 days.

```http
POST /api/v1/auth/login
{"login": "username or email", "password": "…", "device_name": "crm-sync"}

200 {"token": "1|abc…", "token_type": "Bearer", "expires_at": "…", "user": {"id":1,"name":"…","username":"…","email":"…","timezone":"Asia/Kolkata"}}
422 {"message": "These credentials do not match our records.", "errors": {"login": […]}}
```

Use the token on every other call:

```
Authorization: Bearer 1|abc…
```

| Method | Path | |
|---|---|---|
| GET | `/auth/me` | current user |
| POST | `/auth/logout` | revokes the token used |

Rate limits: login 5/min per login+IP and 10/min per IP; everything else 120/min per user. Over the limit returns `429`.

Errors: `401` unauthenticated, `404` not found,
`422` validation (`{"message", "errors": {field: [..]}}`).

Lists are paginated: `?page=2&per_page=50` (max 100). The response has `data`, `links` and `meta`.

## Contacts

| Method | Path | Notes |
|---|---|---|
| GET | `/contacts?q=&status=&list_id=` | search name/email/company |
| POST | `/contacts` | `email` required; one lead per address |
| GET | `/contacts/{id}` | includes `lists` |
| PUT/PATCH | `/contacts/{id}` | PATCH accepts any subset; `status` may be changed (never out of `unsubscribed`) |
| DELETE | `/contacts/{id}` | deletes the lead, as the Leads page does (its enrollments and emails go with it) |

Fields: `first_name, last_name, email, company, website, linkedin, phone, country, job_title, category_id, custom_fields{}`.
Read-only: `status` (email status: active/unsubscribed/bounced/invalid/archived), `outreach_status`
(the Leads page status: new/sent/failed/replied), `emails`, `unsubscribed_at, bounced_at`.

```json
POST /contacts
{"email": "ann@acme.com", "first_name": "Ann", "company": "Acme", "custom_fields": {"plan": "pro"}}
```

## Lists

| Method | Path | |
|---|---|---|
| GET/POST | `/lists` | `name` unique, `description` |
| GET/PUT/PATCH/DELETE | `/lists/{id}` | deleting keeps the contacts |
| GET | `/lists/{id}/contacts` | members |
| POST | `/lists/{id}/contacts` | `{"contact_ids":[1,2]}` returns `{"added": n}` |
| DELETE | `/lists/{id}/contacts` | `{"contact_ids":[1]}` returns `{"removed": n}` |

## Sequences

| Method | Path | |
|---|---|---|
| GET | `/sequences?status=` | |
| POST | `/sequences` | created as `draft` |
| GET | `/sequences/{id}` | includes `steps` |
| PUT/PATCH/DELETE | `/sequences/{id}` | |
| POST | `/sequences/{id}/activate` | 422 if there are no active steps |
| POST | `/sequences/{id}/pause` · `/resume` · `/archive` | idempotent |
| GET | `/sequences/{id}/analytics` | enrollments by status, email counts, open/click/reply/bounce rates |

```json
POST /sequences
{"name":"Q4 agencies","timezone":"Europe/London","sending_start_time":"09:00","sending_end_time":"17:00",
 "sending_days":[1,2,3,4,5],"daily_limit":100,"mail_setting_id":3,"track_opens":true,"track_clicks":true}
```

`mail_setting_id` is optional; without it, enrollments use the default Mail Settings account.

`sending_days` uses ISO weekdays: 1 = Monday … 7 = Sunday.

## Steps

| Method | Path | |
|---|---|---|
| GET | `/sequences/{id}/steps` | ordered by `step_number` |
| POST | `/sequences/{id}/steps` | appended; `template_id` fills a blank subject/body |
| POST | `/sequences/{id}/steps/reorder` | `{"order":[stepId,…]}` - all ids; 422 while contacts are mid-sequence |
| GET/PUT/PATCH/DELETE | `/steps/{id}` | |
| POST | `/steps/{id}/duplicate` | inserted right after the original |

Fields: `subject, body` (plain text or HTML, with `{{variables}}`), `delay_days` (0-365),
`delay_hours` (0-23), `delay_minutes` (0-59), `status` (`active`/`inactive`).

The delay is measured from the previous step (step 1: from enrollment).

Variables: `{{first_name}} {{last_name}} {{email}} {{company}} {{website}} {{country}} {{job_title}}
{{sender_name}} {{sender_email}} {{custom.<key>}} {{unsubscribe_url}}`. A fallback is written as `{{first_name|there}}`.

## Enrollments

| Method | Path | |
|---|---|---|
| GET | `/sequences/{id}/enrollments?status=` | includes `contact` |
| POST | `/sequences/{id}/enrollments` | see below |
| GET | `/enrollments/{id}` | |
| POST | `/enrollments/{id}/pause` · `/resume` | resume continues from the current step |
| DELETE | `/enrollments/{id}` | removes the contact from the sequence (`removed`) |

```json
POST /sequences/7/enrollments
{"contact_ids":[1,2,3], "list_id": null, "mail_setting_id": 3}
```

(`lead_ids` / `category_id` are accepted as aliases of `contact_ids` / `list_id`.)

- With up to 50 contact ids and no list, the request is processed inline and returns `201`:
  `{"results":[{"contact_id":1,"outcome":"enrolled","reason":null,"enrollment_id":10},{"contact_id":2,"outcome":"skipped","reason":"lead_unsubscribed",…}]}`
- With more ids, or with `list_id`, the work is queued as a Bulk (visible on the Bulks page) and returns
  `202`: `{"bulk_id":4,"total":1200,"status":"pending"}`

`outcome` is one of `enrolled`, `reactivated`, `already_enrolled`, `skipped`.

Skip reasons: `lead_has_no_email`, `lead_<email status>`, `duplicate_email_in_sequence` (another lead with the
same address is already in this sequence - one address receives a sequence once), `sequence_has_no_steps`,
`sequence_archived`, `no_mail_account`.

## Email logs

| Method | Path | |
|---|---|---|
| GET | `/email-logs?status=&sequence_id=&contact_id=` | |
| GET | `/email-logs/{id}` | |

Fields: `sequence_id, sequence_step_id, enrollment_id, contact_id, email_account_id, message_id,
from_email, to_email, subject, status, delivery_status, attempts, sent_at, opened_at, open_count,
clicked_at, click_count, replied_at, bounced_at, error_message`.

`status` is the engagement level (queued, sending, sent, failed, opened, clicked, replied, bounced);
`delivery_status` is the raw delivery state (queued, sending, sent, failed, bounced). One-off emails sent
from the Leads page appear here too, with `sequence_id: null`.

The rendered body and tracking token are never exposed.

## Email accounts (read-only)

`GET /email-accounts`, `GET /email-accounts/{id}`. These return `id, name, provider, from_name,
from_email, daily_limit, rate_limit_per_minute, is_active, is_default, has_imap, smtp_ok, imap_ok`.
Credentials are never returned. Accounts are managed in **Settings > Mail Settings**.

## Example session

```bash
TOKEN=$(curl -s -X POST $URL/api/v1/auth/login -H 'Accept: application/json' \
  -d login=alice -d password=secret | jq -r .token)
curl -s $URL/api/v1/contacts -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  -d email=ann@acme.com -d first_name=Ann
curl -s -X POST $URL/api/v1/sequences/7/enrollments -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{"contact_ids":[1]}'
```
