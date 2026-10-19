You are working in my existing web app at leads.sabright.com, which already uses the WhatsApp Cloud API.
Stack: [e.g. Laravel 10 + MySQL / Node.js Express + MySQL — fill in]
Meta App ID: 1671673877821445
Graph API version: v23.0 (keep it in one config constant)

GOAL
Add Meta "Embedded Signup" with WhatsApp Business App Coexistence, so I can connect my existing
WhatsApp Business app number to Cloud API WITHOUT deleting the app. Both the app and the API must
work on the same number. Before writing code, read the existing WhatsApp integration (config, send
message code, webhook handler, DB tables) and reuse it. Do not break current sending/receiving.

1. CONFIG / ENV
- Add env vars: META_APP_ID, META_APP_SECRET, META_ES_CONFIG_ID, META_GRAPH_VERSION,
  WA_WEBHOOK_VERIFY_TOKEN. Never hardcode secrets or expose the app secret to the frontend.

2. DATABASE
- Create a migration for a table `wa_accounts`: id, waba_id, phone_number_id, display_phone_number,
  business_id (nullable), access_token (encrypted at rest), token_type, is_coexistence (bool),
  onboarding_event, history_sync_requested_at, contacts_sync_requested_at, status, timestamps.
- Unique index on phone_number_id. If an existing settings table already stores the token/phone ID,
  migrate the send-message code to read from wa_accounts (with fallback to the old values).

3. FRONTEND (admin settings page, auth-protected)
- Load the Facebook JS SDK (https://connect.facebook.net/en_US/sdk.js), FB.init with appId,
  version from config.
- Add a window "message" listener: accept only origins ending in facebook.com, JSON.parse safely,
  and handle type === "WA_EMBEDDED_SIGNUP". Capture event (expect
  FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING for coexistence, also handle FINISH, CANCEL, ERROR)
  and data.waba_id, data.phone_number_id, data.business_id. Store temporarily in JS.
- "Connect WhatsApp Business App" button calls FB.login with:
  { config_id: META_ES_CONFIG_ID, response_type: 'code', override_default_response_type: true,
    extras: { setup: {}, featureType: 'whatsapp_business_app_onboarding', sessionInfoVersion: '3' } }
- In the FB.login callback, when authResponse.code exists, POST { code, waba_id, phone_number_id,
  business_id, event } to /api/wa/embedded-signup (with CSRF token). The message event may arrive
  slightly before or after the callback — wait/merge both before posting.
- Show clear success/error/cancel states in the UI.

4. BACKEND ROUTE POST /api/wa/embedded-signup (auth + CSRF protected)
a) Exchange code for a business token:
   GET https://graph.facebook.com/{ver}/oauth/access_token?client_id=..&client_secret=..&code=..
b) Fetch phone details: GET /{phone_number_id}?fields=display_phone_number,verified_name
c) Subscribe app to the WABA: POST /{waba_id}/subscribed_apps
d) For coexistence (event FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING): do NOT call
   /{phone_number_id}/register. Instead trigger sync:
   POST /{phone_number_id}/smb_app_data  body {"messaging_product":"whatsapp","sync_type":"smb_app_state_sync"}
   then POST same endpoint with "sync_type":"history". This must happen within 24h of onboarding.
   For a normal (non-coexistence) number, call POST /{phone_number_id}/register with a 6-digit pin
   instead.
e) Save/update the row in wa_accounts (encrypt token). Return JSON. Log every Graph API error with
   its full error body, never log the token.

5. WEBHOOKS (extend my existing webhook endpoint)
- Keep GET verification (hub.mode / hub.verify_token / hub.challenge).
- Validate X-Hub-Signature-256 with META_APP_SECRET on POST.
- Route by change field:
  - messages: existing inbound handling (keep as is)
  - smb_message_echoes: messages I send from the WhatsApp Business app on my phone -> store as
    outgoing messages in the same conversation thread so the inbox shows both
  - history: past chats (up to ~6 months) arriving in chunks -> upsert into messages/contacts,
    idempotent by message id; handle the case where history sharing is declined
  - smb_app_state_sync: contacts add/update/remove -> upsert into contacts/leads table
- Respond 200 fast; process heavy work in a queue/job if the stack supports it.

6. NOTES TO RESPECT
- The number must be on the WhatsApp Business app (not personal WhatsApp) and the app updated.
- Group chats, calls, status, catalog do not sync to the API — ignore them.
- Make all handlers idempotent (webhooks can repeat).

7. DELIVERABLES
- Code changes, migration, routes, and a README section listing the manual steps I must do in the
  Meta dashboard: Facebook Login for Business > Settings (Client OAuth login, Web OAuth login,
  Enforce HTTPS, Embedded browser OAuth login, Strict mode ON, add leads.sabright.com to allowed
  domains & JS SDK domains), Configurations > Create from template "WhatsApp Embedded Signup
  Configuration With 60 Expiration Token" to get config_id, and Webhooks subscriptions for
  messages, smb_message_echoes, history, smb_app_state_sync.
- A short test checklist: button opens popup -> "connect existing WhatsApp Business app" screen
  appears -> confirm on phone -> row saved -> send API message -> it shows in the phone app ->
  send from phone app -> echo webhook stored.

Ask me before changing anything outside the WhatsApp module.