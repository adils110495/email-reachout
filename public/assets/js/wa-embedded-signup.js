/**
 * Meta Embedded Signup - "Connect WhatsApp Business App" (coexistence) on
 * Settings > WhatsApp. Reads its settings from #waEmbedded (data-*).
 *
 * Two things arrive from Facebook, in either order:
 *   - a window "message" of type WA_EMBEDDED_SIGNUP with the event
 *     (FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING for coexistence, FINISH,
 *     FINISH_ONLY_WABA, CANCEL, ERROR) and the waba / phone number / business ids
 *   - FB.login's callback with authResponse.code
 * Both are merged, then posted to the server, which exchanges the code
 * (the app secret never reaches the browser).
 */
(function () {
    'use strict';

    const box = document.getElementById('waEmbedded');
    const btn = document.getElementById('waEmbeddedBtn');
    const out = document.getElementById('waEmbeddedResult');
    if (! box || ! btn || box.dataset.ready !== '1') return;

    const EVENTS     = ['FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING', 'FINISH', 'FINISH_ONLY_WABA'];
    const WAIT_STEPS = 20;    // x 500 ms: how long the code waits for the message
    const CSRF       = document.querySelector('meta[name="csrf-token"]').content;

    let session   = null;     // what the message said
    let code      = null;     // what FB.login returned
    let submitted = false;

    function show(kind, html) {
        const colour = { success: 'text-success', error: 'text-danger', warning: 'text-warning', info: 'text-muted' }[kind] || 'text-muted';
        out.className = 'fs-13 mb-3 ' + colour;
        out.innerHTML = html;
    }

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    /** facebook.com or a subdomain of it - not just any origin that ends in those letters. */
    function fromFacebook(origin) {
        try {
            const host = new URL(origin).hostname;
            return host === 'facebook.com' || host.endsWith('.facebook.com');
        } catch (e) {
            return false;
        }
    }

    // ── Facebook JS SDK ──────────────────────────────────────────────────
    window.fbAsyncInit = function () {
        window.FB.init({ appId: box.dataset.appId, autoLogAppEvents: true, xfbml: false, version: box.dataset.version });
        btn.disabled = false;
    };

    const sdk = document.createElement('script');
    sdk.src = 'https://connect.facebook.net/en_US/sdk.js';
    sdk.async = true;
    sdk.defer = true;
    sdk.crossOrigin = 'anonymous';
    sdk.onerror = function () {
        show('error', 'The Facebook SDK could not be loaded - check that nothing (an ad blocker, a firewall) blocks connect.facebook.net.');
    };
    document.body.appendChild(sdk);

    // ── The popup's message ──────────────────────────────────────────────
    window.addEventListener('message', function (event) {
        if (! fromFacebook(event.origin)) return;

        let data;
        try {
            data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
        } catch (e) {
            return;   // Facebook posts other, non-JSON messages too
        }
        if (! data || data.type !== 'WA_EMBEDDED_SIGNUP') return;

        const d = data.data || {};

        if (data.event === 'CANCEL') {
            session = { cancelled: true, step: d.current_step || null };
            return;   // FB.login's callback reports it
        }
        if (data.event === 'ERROR') {
            session = { error: d.error_message || 'Meta reported an error in the signup.' };
            show('error', esc(session.error));
            return;
        }

        session = {
            event:           EVENTS.indexOf(data.event) !== -1 ? data.event : 'UNKNOWN',
            waba_id:         d.waba_id || null,
            phone_number_id: d.phone_number_id || null,
            business_id:     d.business_id || null,
        };

        if (code) submit();
    });

    // ── The button ───────────────────────────────────────────────────────
    btn.addEventListener('click', function () {
        if (! window.FB) return;

        session   = null;
        code      = null;
        submitted = false;
        show('info', 'Follow the steps in the Facebook window, and confirm on your phone when WhatsApp asks.');

        // A plain function: the SDK refuses an async callback.
        window.FB.login(function (response) {
            if (response && response.authResponse && response.authResponse.code) {
                code = response.authResponse.code;

                if (session && session.event) {
                    submit();
                } else {
                    show('info', 'Finishing…');
                    waitForSession(0);   // the message may still be on its way
                }
                return;
            }

            if (session && session.error) {
                show('error', esc(session.error));
            } else {
                show('warning', 'Cancelled' + (session && session.step ? ' at "' + esc(session.step) + '"' : '') + ' - nothing was connected.');
            }
        }, {
            config_id: box.dataset.configId,
            response_type: 'code',
            override_default_response_type: true,
            extras: { setup: {}, featureType: 'whatsapp_business_app_onboarding', sessionInfoVersion: '3' },
        });
    });

    function waitForSession(step) {
        if ((session && session.event) || step >= WAIT_STEPS) {
            submit();   // still nothing: post the code alone - the server finds the account from the token
            return;
        }
        window.setTimeout(function () { waitForSession(step + 1); }, 500);
    }

    // ── To the server ────────────────────────────────────────────────────
    function submit() {
        if (submitted || ! code) return;
        submitted = true;
        btn.disabled = true;
        show('info', 'Connecting your number…');

        const s = session && session.event ? session : {};

        fetch(box.dataset.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                code:            code,
                waba_id:         s.waba_id || null,
                phone_number_id: s.phone_number_id || null,
                business_id:     s.business_id || null,
                event:           s.event || 'UNKNOWN',
            }),
        })
            .then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (data) { return { ok: r.ok && data.ok, data: data }; });
            })
            .then(function (res) {
                const data = res.data || {};

                if (! res.ok) {
                    const message = data.errors ? Object.values(data.errors)[0][0] : data.message;
                    show('error', esc(message || 'The number could not be connected.'));
                    return;
                }

                let html = '<i class="bi bi-check-circle-fill me-1"></i>' + esc(data.message);
                if (data.pin) {
                    html += '<div class="mt-1">Two-step verification PIN of this number: <strong>' + esc(data.pin) + '</strong> - write it down.</div>';
                }
                (data.warnings || []).forEach(function (w) {
                    html += '<div class="text-warning mt-1">' + esc(w) + '</div>';
                });
                html += '<div class="mt-1"><a href="' + esc(window.location.pathname) + '">Refresh the list</a></div>';
                show('success', html);
            })
            .catch(function () {
                show('error', 'Could not reach the server. Please try again.');
            })
            .finally(function () {
                btn.disabled = false;
            });
    }
})();
