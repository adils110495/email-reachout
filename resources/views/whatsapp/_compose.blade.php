{{--
    WhatsApp compose modal, shared by GMB Leads, Deals and the WhatsApp page.
    One modal, two modes:

    Single - opened by any .btn-wa-compose (delegated, so rows swapped in by
    ajax-filters.js keep working) carrying:
        data-source  gmb | deal | direct
        data-id      the GMB lead / deal id (none for direct)
        data-name    contact name
        data-phone   number as saved
    With the Cloud API connected it sends an approved template or free text;
    without it, "Open in WhatsApp" (wa.me) is the way to send.

    Bulk - opened by a .btn-wa-bulk inside whatsapp/_bulk-bar (data-mode
    selected | all). The checked rows (.wa-row-check), or every row matching
    the list's filters, get an approved WhatsApp template, an email, or both -
    the channels the bar offers (data-channels). Queued and paced on the
    server (Controllers\Concerns\QueuesBulkSend).

    @param bool $bulkEmail  the page's bulk bar offers email (Deals): renders the
                            email section. Email needs no WhatsApp module, so the
                            modal is then rendered without it too.
--}}
@php
    $waAllowed = (bool) auth()->user()?->can('module', 'whatsapp');
    $bulkEmail = ! empty($bulkEmail);
@endphp
@if($waAllowed || $bulkEmail)
@php
    $waApiReady = $waAllowed && (bool) \App\Models\WhatsappSetting::forActingUser()?->canSend();
    $waBulkMax  = (int) config('services.whatsapp.bulk_max', 250);

    // The email section's templates; the body is made safe before it reaches the editor.
    $emailTemplates = $bulkEmail
        ? \App\Models\EmailTemplate::where('status', 'active')->latest()->get()->map(fn (\App\Models\EmailTemplate $t) => [
            'id'          => $t->id,
            'name'        => $t->name,
            'subject'     => (string) $t->subject,
            'body'        => \App\Support\EmailHtml::render($t->body),
            'attachments' => collect($t->attachments ?? [])->map(fn (array $a) => ['name' => $a['name'] ?? basename($a['path']), 'path' => $a['path']])->values(),
        ])->values()
        : collect();
@endphp
{{-- data-bs-focus="false": the email editor's link box lives outside the modal, and the modal's focus trap would not let it be typed in. --}}
<div class="modal fade" id="whatsappModal" tabindex="-1" aria-hidden="true" data-bs-focus="false"
     data-api-ready="{{ $waApiReady ? '1' : '0' }}"
     data-bulk-max="{{ $waBulkMax }}"
     data-send-url="{{ route('whatsapp.send') }}"
     data-open-url="{{ route('whatsapp.open-chat') }}"
     data-templates-url="{{ route('whatsapp.templates') }}">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <form id="whatsappForm" novalidate>
                <input type="hidden" name="source" id="wa_source" value="direct">
                <input type="hidden" name="id" id="wa_record_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-whatsapp me-2 text-success" id="wa_title_icon"></i><span id="wa_title">Send WhatsApp</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    {{-- Single: one contact --}}
                    <div class="row g-3 mb-3" id="wa_single">
                        <div class="col-md-6">
                            <label class="form-label" for="wa_name">Name</label>
                            <input type="text" name="name" id="wa_name" class="form-control" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="wa_phone">WhatsApp number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" id="wa_phone" class="form-control" maxlength="50"
                                   placeholder="+91 98765 43210" autocomplete="off">
                            <div class="form-text">With the country code, or set a default one in WhatsApp Settings.</div>
                        </div>
                    </div>

                    {{-- Bulk: who it goes to --}}
                    <div class="alert alert-light d-flex align-items-start gap-2 mb-3 d-none" id="wa_bulk">
                        <i class="bi bi-people-fill text-success mt-1"></i>
                        <div id="wa_bulk_summary"></div>
                    </div>

                    {{-- Bulk: which channels - only when the list offers more than one --}}
                    <div class="mb-3 d-none" id="wa_channels">
                        <span class="form-label d-block">Send by</span>
                        <div class="btn-group" role="group" aria-label="Channels">
                            <input type="checkbox" class="btn-check wa-channel" id="wa_channel_whatsapp" value="whatsapp" autocomplete="off">
                            <label class="btn btn-outline-success btn-sm" for="wa_channel_whatsapp">
                                <i class="bi bi-whatsapp me-1"></i>WhatsApp
                            </label>
                            <input type="checkbox" class="btn-check wa-channel" id="wa_channel_email" value="email" autocomplete="off">
                            <label class="btn btn-outline-primary btn-sm" for="wa_channel_email">
                                <i class="bi bi-envelope me-1"></i>Email
                            </label>
                        </div>
                        <div class="form-text">Tick both to reach every contact on WhatsApp and by email. Each channel goes to the contacts that have its number or address.</div>
                    </div>

                    {{-- WhatsApp: all of single mode, and bulk's WhatsApp channel --}}
                    <div id="wa_whatsapp_section">
                        <h6 class="bulk-section-title d-none" data-bulk-heading><i class="bi bi-whatsapp me-1 text-success"></i>WhatsApp</h6>

                        @if($waApiReady)
                            <div class="btn-group mb-3" role="group" aria-label="Message type" id="wa_modes">
                                <input type="radio" class="btn-check" name="mode" id="wa_mode_template" value="template" checked>
                                <label class="btn btn-outline-success btn-sm" for="wa_mode_template">
                                    <i class="bi bi-file-earmark-text me-1"></i>Template
                                </label>
                                <input type="radio" class="btn-check" name="mode" id="wa_mode_text" value="text">
                                <label class="btn btn-outline-success btn-sm" for="wa_mode_text">
                                    <i class="bi bi-chat-left-text me-1"></i>Text message
                                </label>
                            </div>

                            {{-- Template: approved templates of the sending account, body variables as inputs --}}
                            <div id="wa_template_pane">
                                <label class="form-label" for="wa_template">Approved template</label>
                                <div class="input-group">
                                    <select id="wa_template" class="form-select"></select>
                                    <button type="button" class="btn btn-light" id="wa_template_refresh" title="Reload templates from Meta" aria-label="Reload templates">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                                <div class="form-text" id="wa_template_status">The first message to a contact must be a template Meta has approved.</div>
                                <div class="row g-2 mt-1" id="wa_params"></div>
                                <div class="form-text d-none" id="wa_bulk_hint">
                                    Write <code>&#123;&#123;name&#125;&#125;</code> in a value to put in each contact's own name.
                                </div>
                                <div class="wa-preview mt-3 d-none" id="wa_preview"></div>
                            </div>

                            {{-- Bulk: pacing --}}
                            <div class="row g-3 mt-1 d-none" id="wa_bulk_options">
                                <div class="col-md-6">
                                    <label class="form-label" for="wa_interval">Gap between messages</label>
                                    <select id="wa_interval" class="form-select">
                                        @foreach(\App\Services\WhatsappBulkService::INTERVALS as $seconds)
                                            <option value="{{ $seconds }}" @selected($seconds === \App\Services\WhatsappBulkService::DEFAULT_INTERVAL)>{{ $seconds }} seconds</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">A burst of identical messages gets a number reported - spacing them protects its quality rating.</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-text mt-md-4 pt-md-2">
                                        Meta lets a new number start 250 conversations a day, and raises the limit while its quality stays high.
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="alert alert-info fs-13 mb-3" id="wa_no_api">
                                <i class="bi bi-info-circle me-1"></i>
                                The WhatsApp Cloud API is not connected, so the chat opens in your own WhatsApp (app or web) with the message typed in.
                                <a href="{{ route('whatsapp-settings.index') }}">Connect the API</a> to send from here and see delivered, read and replied.
                            </div>
                            <div class="alert alert-warning fs-13 mb-3 d-none" id="wa_no_api_bulk">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Bulk sending goes through the WhatsApp Cloud API, which is not connected.
                                <a href="{{ route('whatsapp-settings.index') }}">Connect it in WhatsApp Settings</a> to send to many contacts at once.
                            </div>
                        @endif

                        {{-- Free text (single only) --}}
                        <div id="wa_text_pane" class="{{ $waApiReady ? 'd-none' : '' }}">
                            <label class="form-label" for="wa_body">Message</label>
                            <textarea name="body" id="wa_body" class="form-control" rows="6" maxlength="4096"></textarea>
                            @if($waApiReady)
                                <div class="form-text">Free text is only delivered if this contact wrote to you in the last 24 hours. For a first message use a template.</div>
                            @endif
                        </div>
                    </div>

                    {{-- Bulk: email (Deals) - one subject and body, the placeholders filled in per email --}}
                    @if($bulkEmail)
                        @include('partials._email-editor')
                        <div class="d-none" id="wa_email_section" data-placeholder-scope>
                            <h6 class="bulk-section-title d-none" data-bulk-heading><i class="bi bi-envelope me-1 text-primary"></i>Email</h6>
                            <div class="row g-3">
                                <div class="col-md-7">
                                    <label class="form-label" for="wa_email_template">Email template</label>
                                    <select id="wa_email_template" class="form-select">
                                        <option value="">— None, write it below —</option>
                                        @foreach($emailTemplates as $t)
                                            <option value="{{ $t['id'] }}">{{ $t['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" for="wa_email_interval">Gap between emails</label>
                                    <select id="wa_email_interval" class="form-select">
                                        @foreach(\App\Services\EmailBulkService::INTERVALS as $seconds)
                                            <option value="{{ $seconds }}" @selected($seconds === \App\Services\EmailBulkService::DEFAULT_INTERVAL)>{{ $seconds }} seconds</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="wa_email_subject">Subject <span class="text-danger">*</span></label>
                                    <input type="text" id="wa_email_subject" class="form-control" maxlength="255" autocomplete="off" data-placeholder-field>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                        <label class="form-label mb-0" for="wa_email_body">Message <span class="text-danger">*</span></label>
                                        @include('partials._placeholder-chips')
                                    </div>
                                    <div class="email-editor email-editor-sm">
                                        <textarea id="wa_email_body" class="form-control" rows="7" data-email-editor data-placeholder-field
                                                  placeholder="Write the email…"></textarea>
                                    </div>
                                    <div class="form-text">
                                        <code>&#123;&#123;client_name&#125;&#125;</code> becomes each contact's name;
                                        <code>&#123;&#123;sender_name&#125;&#125;</code> and <code>&#123;&#123;sender_company&#125;&#125;</code> become yours.
                                        Sent from the mailbox in Mail Settings, with open and reply tracking.
                                    </div>
                                </div>
                                <div class="col-12 d-none" id="wa_email_attachments_row">
                                    <span class="form-label d-block">Attachments from the template</span>
                                    <div class="d-flex flex-wrap gap-2" id="wa_email_attachments"></div>
                                </div>
                            </div>
                        </div>
                        <script type="application/json" id="wa_email_templates">@json($emailTemplates)</script>
                    @endif

                    {{-- Bulk: for every channel --}}
                    <div class="mt-3 d-none" id="wa_bulk_shared">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="wa_skip_contacted" checked>
                            <label class="form-check-label" for="wa_skip_contacted">Skip contacts already <span id="wa_skip_label">messaged on WhatsApp</span></label>
                        </div>
                        <div class="form-text">At most {{ $waBulkMax }} contacts per bulk send.</div>
                    </div>

                    <div class="text-danger fs-13 mt-3 d-none" id="wa_error" role="alert"></div>
                </div>

                <div class="modal-footer justify-content-between">
                    <div>
                        <button type="button" class="btn btn-light" id="wa_open_chat">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Open in WhatsApp
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        @if($waApiReady || $bulkEmail)
                            <button type="submit" class="btn btn-success" id="wa_send">
                                <span class="spinner-border spinner-border-sm d-none me-1" id="wa_send_spinner"></span>
                                <i class="bi bi-send-fill me-1" id="wa_send_icon"></i><span id="wa_send_label">Send</span>
                            </button>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
{{-- verbatim: the script matches template placeholders, which Blade would otherwise read as echoes. --}}
@verbatim
<script>
(function () {
    'use strict';

    const modalEl = document.getElementById('whatsappModal');
    if (! modalEl) return;

    const API_READY   = modalEl.dataset.apiReady === '1';
    const BULK_MAX    = Number(modalEl.dataset.bulkMax || 250);
    const CSRF        = document.querySelector('meta[name="csrf-token"]').content;
    const PLACEHOLDER = /\{\{\s*([A-Za-z0-9_]+)\s*\}\}/g;
    const NAME_TOKEN  = /\{\{\s*name\s*\}\}|\{name\}/gi; // {name} is the older spelling

    const form = document.getElementById('whatsappForm');
    const el = {
        source:      document.getElementById('wa_source'),
        recordId:    document.getElementById('wa_record_id'),
        title:       document.getElementById('wa_title'),
        titleIcon:   document.getElementById('wa_title_icon'),
        single:      document.getElementById('wa_single'),
        name:        document.getElementById('wa_name'),
        phone:       document.getElementById('wa_phone'),
        bulk:        document.getElementById('wa_bulk'),
        bulkSummary: document.getElementById('wa_bulk_summary'),
        modes:       document.getElementById('wa_modes'),
        body:        document.getElementById('wa_body'),
        textPane:    document.getElementById('wa_text_pane'),
        tplPane:     document.getElementById('wa_template_pane'),
        template:    document.getElementById('wa_template'),
        tplState:    document.getElementById('wa_template_status'),
        refresh:     document.getElementById('wa_template_refresh'),
        params:      document.getElementById('wa_params'),
        bulkHint:    document.getElementById('wa_bulk_hint'),
        preview:     document.getElementById('wa_preview'),
        bulkOptions: document.getElementById('wa_bulk_options'),
        interval:    document.getElementById('wa_interval'),
        channels:    document.getElementById('wa_channels'),
        channelWa:   document.getElementById('wa_channel_whatsapp'),
        channelMail: document.getElementById('wa_channel_email'),
        waSection:   document.getElementById('wa_whatsapp_section'),
        mailSection: document.getElementById('wa_email_section'),
        mailTpl:     document.getElementById('wa_email_template'),
        mailGap:     document.getElementById('wa_email_interval'),
        mailSubject: document.getElementById('wa_email_subject'),
        mailBody:    document.getElementById('wa_email_body'),
        mailAttRow:  document.getElementById('wa_email_attachments_row'),
        mailAtts:    document.getElementById('wa_email_attachments'),
        bulkShared:  document.getElementById('wa_bulk_shared'),
        skip:        document.getElementById('wa_skip_contacted'),
        skipLabel:   document.getElementById('wa_skip_label'),
        noApi:       document.getElementById('wa_no_api'),
        noApiBulk:   document.getElementById('wa_no_api_bulk'),
        error:       document.getElementById('wa_error'),
        openChat:    document.getElementById('wa_open_chat'),
        send:        document.getElementById('wa_send'),
        sendLabel:   document.getElementById('wa_send_label'),
        spinner:     document.getElementById('wa_send_spinner'),
        sendIcon:    document.getElementById('wa_send_icon'),
    };

    let templates = [];
    let loadSeq   = 0;    // a slow response for an earlier contact must not overwrite the current list
    let bulk      = null; // set while the modal is in bulk mode: { url, channels, all, ids, label, sample, total }

    // Email section (Deals): the account's email templates, and the attachments
    // of the chosen one still kept for this send.
    const mailTemplates = (function () {
        const node = document.getElementById('wa_email_templates');
        try { return node ? JSON.parse(node.textContent) : []; } catch (e) { return []; }
    })();
    let mailAttachments = [];

    // ── Helpers ───────────────────────────────────────────────────────────

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);
        return div.innerHTML;
    }

    function wrap(name) {
        return '{' + '{' + name + '}' + '}';
    }

    function toggle(node, show) {
        if (node) node.classList.toggle('d-none', ! show);
    }

    function mode() {
        const checked = form.querySelector('[name=mode]:checked');
        return checked ? checked.value : 'text';
    }

    function setMode(value) {
        const radio = form.querySelector('[name=mode][value="' + value + '"]');
        if (radio) radio.checked = true;
        toggle(el.tplPane, value === 'template');
        toggle(el.textPane, value === 'text' && ! bulk); // bulk sends templates only
    }

    function showError(message) {
        el.error.textContent = message;
        el.error.classList.remove('d-none');
    }

    function hideError() {
        el.error.classList.add('d-none');
        el.error.textContent = '';
    }

    function firstError(data, fallback) {
        if (data && data.errors) return Object.values(data.errors)[0][0];
        return (data && data.message) || fallback;
    }

    /** @param links  optional [{ url, label }] - e.g. a bulk send's progress pages */
    function toast(message, links) {
        const box = document.getElementById('notifToasts');
        if (! box) return;
        links = links || [];
        const t = document.createElement('div');
        t.className = 'toast align-items-center';
        t.setAttribute('role', 'status');
        t.innerHTML = '<div class="d-flex"><div class="toast-body"><i class="bi bi-check-circle-fill text-success me-2"></i>'
            + esc(message)
            + (links.length
                ? '<div class="mt-1 d-flex flex-wrap gap-3">' + links.map(function (l) {
                    return '<a class="fs-13" href="' + esc(l.url) + '">' + esc(l.label) + '</a>';
                }).join('') + '</div>'
                : '')
            + '</div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
        box.appendChild(t);
        t.addEventListener('hidden.bs.toast', function () { t.remove(); });
        new bootstrap.Toast(t, { delay: links.length ? 12000 : 6000 }).show();
    }

    function reloadList() {
        const root = document.querySelector('[data-ajax-root]');
        if (root) root.dispatchEvent(new CustomEvent('ajax-filters:reload'));
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (data) {
                return { ok: r.ok && data.ok !== false, data: data };
            });
        });
    }

    function setBusy(on) {
        if (! el.send) return;
        el.send.disabled = on;
        el.spinner.classList.toggle('d-none', ! on);
        el.sendIcon.classList.toggle('d-none', on);
    }

    // ── Row selection (GMB Leads / Deals) ─────────────────────────────────

    function checkedRows() {
        return Array.prototype.slice.call(document.querySelectorAll('.wa-row-check:checked'));
    }

    function updateSelection() {
        const count = checkedRows().length;
        const boxes = document.querySelectorAll('.wa-row-check:not(:disabled)');

        document.querySelectorAll('[data-wa-selected-count]').forEach(function (n) { n.textContent = count; });
        document.querySelectorAll('.btn-wa-bulk[data-mode="selected"]').forEach(function (b) { toggle(b, count > 0); });

        const all = document.querySelector('.wa-check-all');
        if (all) {
            all.checked       = boxes.length > 0 && count === boxes.length;
            all.indeterminate = count > 0 && count < boxes.length;
        }
    }

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('wa-check-all')) {
            document.querySelectorAll('.wa-row-check:not(:disabled)').forEach(function (cb) { cb.checked = e.target.checked; });
            updateSelection();
        } else if (e.target.classList.contains('wa-row-check')) {
            updateSelection();
        }
    });

    // A filter / page change swaps the rows - and their checkboxes - out. The
    // list is ajax-filters.js's region, or the one a bar names (Leads).
    const listRegions = [document.querySelector('[data-ajax-root] .ajax-region')];
    document.querySelectorAll('[data-wa-bulk][data-rows-region]').forEach(function (bar) {
        listRegions.push(document.querySelector(bar.dataset.rowsRegion));
    });
    listRegions.forEach(function (region) {
        if (region) new MutationObserver(updateSelection).observe(region, { childList: true });
    });

    // For a page that ticks rows from its own script (the Leads "select all").
    window.BulkSend = { refresh: updateSelection };

    // ── Templates ─────────────────────────────────────────────────────────

    function currentTemplate() {
        return el.template && el.template.value !== '' ? templates[Number(el.template.value)] : null;
    }

    function paramValues() {
        const values = {};
        if (el.params) {
            el.params.querySelectorAll('.wa-param').forEach(function (input) {
                values[input.dataset.param] = input.value.trim();
            });
        }
        return values;
    }

    /** The template with the variables filled in - what the contact will read. */
    function previewText() {
        const t = currentTemplate();
        if (! t) return '';
        const values = paramValues();
        const fill = function (text) {
            return text.replace(PLACEHOLDER, function (match, key) {
                const value = values[key] || '';
                // In bulk the preview shows the first contact's name for {name}.
                return (bulk ? value.replace(NAME_TOKEN, bulk.sample || 'Contact name') : value) || match;
            });
        };
        return (t.header ? fill(t.header) + '\n\n' : '') + fill(t.body);
    }

    function renderPreview() {
        if (! el.preview) return;
        const text = previewText();
        el.preview.textContent = text;
        toggle(el.preview, text !== '');
    }

    function renderParams() {
        if (! el.params) return;

        const t = currentTemplate();
        el.params.innerHTML = '';

        if (t) {
            t.params.forEach(function (param, i) {
                // The first variable is almost always the contact's name.
                const isName  = i === 0 || /name/i.test(param);
                const prefill = isName ? (bulk ? '{{name}}' : el.name.value.trim()) : '';
                const col = document.createElement('div');
                col.className = 'col-md-6';
                col.innerHTML = '<label class="form-label fs-13 mb-1">' + esc(wrap(param)) + '</label>'
                    + '<input type="text" class="form-control form-control-sm wa-param" maxlength="1024">';
                const input = col.querySelector('input');
                input.dataset.param = param;
                input.value = prefill;
                el.params.appendChild(col);
            });
        }

        toggle(el.bulkHint, !! bulk && !! t && t.params.length > 0);
        renderPreview();
    }

    function renderTemplateOptions() {
        el.template.innerHTML = '<option value="">— Select a template —</option>' + templates.map(function (t, i) {
            const label = t.name + ' (' + t.language + ')' + (t.unsupported ? ' — not supported here: ' + t.unsupported : '');
            return '<option value="' + i + '"' + (t.unsupported ? ' disabled' : '') + '>' + esc(label) + '</option>';
        }).join('');
        el.template.disabled = false;

        el.tplState.classList.remove('text-danger');
        el.tplState.textContent = templates.length
            ? 'The first message to a contact must be a template Meta has approved.'
            : 'No approved templates yet. Create one in WhatsApp Manager - Meta must approve it before it can be sent.';
        renderParams();
    }

    function loadTemplates(refresh) {
        if (! el.template) return;

        const seq = ++loadSeq;
        templates = [];
        el.template.innerHTML = '<option value="">Loading templates…</option>';
        el.template.disabled = true;
        renderParams();

        const query = new URLSearchParams({ source: el.source.value, id: el.recordId.value });
        if (refresh) query.set('refresh', '1');

        fetch(modalEl.dataset.templatesUrl + '?' + query.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
            .then(function (res) {
                if (seq !== loadSeq) return;
                if (! res.ok || ! res.data.ok) throw new Error(firstError(res.data, 'Could not load templates.'));
                templates = res.data.templates || [];
                renderTemplateOptions();
            })
            .catch(function (err) {
                if (seq !== loadSeq) return;
                templates = [];
                el.template.innerHTML = '<option value="">No templates</option>';
                el.tplState.classList.add('text-danger');
                el.tplState.textContent = err.message || 'Could not load templates.';
            });
    }

    // ── Channels (bulk) ───────────────────────────────────────────────────

    function has(list, channel) {
        return list.indexOf(channel) !== -1;
    }

    /** The channels this bulk send goes out on: the ticked ones, or the list's only one. */
    function selectedChannels() {
        if (! bulk) return ['whatsapp'];
        if (bulk.channels.length < 2) return bulk.channels.slice();

        return bulk.channels.filter(function (channel) {
            const box = channel === 'email' ? el.channelMail : el.channelWa;
            return box && box.checked;
        });
    }

    /** Show the section of every ticked channel, and word the shared options for them. */
    function applyChannels() {
        const channels = selectedChannels();
        const wa       = has(channels, 'whatsapp');
        const email    = !! bulk && has(channels, 'email');

        toggle(el.waSection, wa);
        toggle(el.mailSection, email);
        if (el.skipLabel) {
            el.skipLabel.textContent = wa && email ? 'reached on that channel' : (email ? 'emailed' : 'messaged on WhatsApp');
        }
    }

    // ── Email (bulk, Deals) ───────────────────────────────────────────────

    function renderMailAttachments() {
        if (! el.mailAtts) return;

        el.mailAtts.innerHTML = '';
        mailAttachments.forEach(function (att, i) {
            const chip = document.createElement('span');
            chip.className = 'bulk-attachment';
            chip.innerHTML = '<i class="bi bi-paperclip"></i><span></span>'
                + '<button type="button" class="btn-close" aria-label="Leave this attachment out"></button>';
            chip.querySelector('span').textContent = att.name;
            chip.querySelector('button').addEventListener('click', function () {
                mailAttachments.splice(i, 1);
                renderMailAttachments();
            });
            el.mailAtts.appendChild(chip);
        });
        toggle(el.mailAttRow, mailAttachments.length > 0);
    }

    /** Fill subject, body and attachments from the chosen email template. */
    function applyMailTemplate() {
        const t = mailTemplates.find(function (tpl) { return String(tpl.id) === el.mailTpl.value; });

        // "None" keeps what was typed; only the template's attachments go.
        if (t) {
            el.mailSubject.value = t.subject || '';
            EmailEditor.setData(el.mailBody, t.body || '');
        }
        mailAttachments = t ? (t.attachments || []).slice() : [];
        renderMailAttachments();
    }

    /** The email half of a bulk send, or null after reporting what is missing. */
    function mailPayload() {
        const subject = el.mailSubject.value.trim();

        if (! subject) {
            showError('Write the email subject.');
            return null;
        }
        if (EmailEditor.isEmpty(el.mailBody)) {
            showError('Write the email.');
            return null;
        }

        return { subject: subject, body: EmailEditor.getData(el.mailBody) };
    }

    // ── Open ──────────────────────────────────────────────────────────────

    /** Show the parts of the modal that belong to the current mode. */
    function layout() {
        const isBulk    = !! bulk;
        const channels  = isBulk ? bulk.channels : ['whatsapp'];
        const multi     = channels.length > 1;
        const withEmail = has(channels, 'email');

        const mailOnly  = withEmail && ! multi;

        el.title.textContent = isBulk ? (mailOnly ? 'Bulk Email' : (withEmail ? 'Bulk Send' : 'Bulk WhatsApp')) : 'Send WhatsApp';
        el.titleIcon.className = 'bi me-2 ' + (mailOnly ? 'bi-envelope text-primary' : (withEmail ? 'bi-send text-primary' : 'bi-whatsapp text-success'));
        toggle(el.single, ! isBulk);
        toggle(el.bulk, isBulk);
        toggle(el.channels, isBulk && multi);
        toggle(el.modes, ! isBulk);
        toggle(el.bulkOptions, isBulk);
        toggle(el.bulkShared, isBulk);
        toggle(el.openChat, ! isBulk);
        toggle(el.noApi, ! isBulk);
        toggle(el.noApiBulk, isBulk);
        // Without the Cloud API a single message goes out through "Open in WhatsApp".
        toggle(el.send, isBulk || API_READY);
        modalEl.querySelectorAll('[data-bulk-heading]').forEach(function (h) { toggle(h, isBulk && multi); });
        if (el.sendLabel) el.sendLabel.textContent = isBulk ? 'Queue messages' : 'Send';

        if (isBulk && multi) {
            // WhatsApp starts ticked only when it can actually send.
            el.channelWa.checked   = API_READY;
            el.channelMail.checked = true;
        }
        applyChannels();

        if (API_READY && has(channels, 'whatsapp')) {
            setMode('template');
            loadTemplates(false);
        } else {
            setMode('text');
        }
    }

    function reset() {
        form.reset();
        hideError();
        templates       = [];
        mailAttachments = [];
        renderMailAttachments();
        // form.reset() clears the textarea, not the editor on top of it.
        if (el.mailBody) EmailEditor.setData(el.mailBody, '');
    }

    // Single - delegated: rows are replaced wholesale by ajax-filters.js on every filter change.
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('.btn-wa-compose');
        if (! trigger) return;
        e.preventDefault();

        const d = trigger.dataset;
        bulk = null;
        reset();

        el.source.value   = d.source || 'direct';
        el.recordId.value = d.id || '';
        el.name.value     = d.name || '';
        el.name.readOnly  = el.source.value !== 'direct'; // a record's name comes from the record
        el.phone.value    = d.phone || '';
        el.body.value     = d.name ? 'Hi ' + d.name + ',\n\n' : '';

        layout();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    // Bulk - the selected rows, or every row matching the list's filters.
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('.btn-wa-bulk');
        if (! trigger) return;
        e.preventDefault();

        const bar  = trigger.closest('[data-wa-bulk]');
        const all  = trigger.dataset.mode === 'all';
        const rows = checkedRows();
        if (! bar || (! all && ! rows.length)) return;

        const totalNode = document.querySelector(bar.dataset.totalFrom || '[data-ajax-root] .ajax-content[data-total]');
        // Templates are loaded for the account the messages go out from - the
        // owner of these rows - so pass one of them along.
        const sampleRow = rows[0] || document.querySelector('.wa-row-check:not(:disabled)');

        bulk = {
            url:      bar.dataset.url,
            // What the list's rows can be sent, as far as this user may: whatsapp, email.
            channels: (bar.dataset.channels || 'whatsapp').split(',').filter(Boolean),
            label:    bar.dataset.label || 'contacts',
            all:      all,
            ids:      all ? [] : rows.map(function (r) { return r.value; }),
            sample:   sampleRow ? sampleRow.dataset.name : '',
            total:    all ? Number(totalNode ? totalNode.dataset.total : 0) : rows.length,
        };
        reset();

        const reachableBy = bulk.channels.length > 1 ? 'a phone number or email'
            : (bulk.channels[0] === 'email' ? 'an email address' : 'a phone number');

        el.source.value   = sampleRow ? bar.dataset.source : 'direct';
        el.recordId.value = sampleRow ? sampleRow.value : '';
        // The total counts rows that cannot be reached too; the server only
        // counts the ones it can, so a large total is a heads-up, not an error.
        el.bulkSummary.innerHTML = (all
            ? 'To <strong>all ' + esc(bulk.total.toLocaleString()) + ' ' + esc(bulk.label) + '</strong> matching the current filters. '
                + 'Those without ' + reachableBy + ' are left out.'
            : 'To the <strong>' + esc(bulk.ids.length) + ' selected ' + esc(bulk.label) + '</strong>.')
            + (bulk.total > BULK_MAX
                ? '<div class="text-danger fs-13 mt-1">One bulk send can have at most ' + esc(BULK_MAX) + ' contacts - narrow the filters if more than that have ' + reachableBy + '.</div>'
                : '');

        layout();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    form.addEventListener('change', function (e) {
        if (e.target.name === 'mode') {
            hideError();
            setMode(e.target.value);
        } else if (e.target === el.template) {
            renderParams();
        } else if (e.target.classList.contains('wa-channel')) {
            hideError();
            applyChannels();
        } else if (e.target === el.mailTpl) {
            applyMailTemplate();
        }
    });

    // The template pane only exists when the Cloud API is connected.
    if (el.params) el.params.addEventListener('input', renderPreview);
    if (el.refresh) el.refresh.addEventListener('click', function () { loadTemplates(true); });

    // ── Send ──────────────────────────────────────────────────────────────

    /** The chosen template and its values, or null after reporting what is missing. */
    function templatePayload() {
        const t = currentTemplate();
        if (! t) {
            showError('Choose an approved WhatsApp template.');
            return null;
        }

        const values = paramValues();
        if (t.params.some(function (p) { return ! values[p]; })) {
            showError('Fill in every template variable.');
            return null;
        }

        return { template: t, values: values };
    }

    function appendTemplate(body, payload) {
        body.append('template_name', payload.template.name);
        body.append('template_language', payload.template.language);
        payload.template.params.forEach(function (p) { body.append('params[' + p + ']', payload.values[p]); });
    }

    function sendSingle() {
        if (! el.phone.value.trim()) return showError('Enter the WhatsApp number.');

        const body = new FormData();
        body.append('source', el.source.value);
        body.append('id', el.recordId.value);
        body.append('name', el.name.value.trim());
        body.append('phone', el.phone.value.trim());
        body.append('mode', mode());

        if (mode() === 'template') {
            const payload = templatePayload();
            if (! payload) return;
            appendTemplate(body, payload);
            body.append('template_body', previewText());
        } else {
            if (! el.body.value.trim()) return showError('Write a message.');
            body.append('body', el.body.value);
        }

        setBusy(true);
        post(modalEl.dataset.sendUrl, body)
            .then(function (res) {
                if (! res.ok) return showError(firstError(res.data, 'The message was not sent.'));
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                toast(res.data.message || 'WhatsApp message sent.');
                reloadList();
            })
            .catch(function () { showError('Could not reach the server. Please try again.'); })
            .finally(function () { setBusy(false); });
    }

    function sendBulk() {
        const channels = selectedChannels();
        if (! channels.length) return showError('Choose how to send: WhatsApp, email or both.');

        // The list endpoint reads its filters from the query string - the same
        // ones the list on screen was built from. The body carries only the
        // send itself, so no field can be mistaken for a filter.
        const body = new FormData();
        if (bulk.all) {
            body.append('all', '1');
        } else {
            bulk.ids.forEach(function (id) { body.append('ids[]', id); });
        }
        channels.forEach(function (channel) { body.append('channels[]', channel); });

        if (has(channels, 'whatsapp')) {
            if (! API_READY) return showError('WhatsApp goes out through the Cloud API, which is not connected. Untick WhatsApp, or connect it in WhatsApp Settings.');

            const payload = templatePayload();
            if (! payload) return;
            appendTemplate(body, payload);
            body.append('template_header', payload.template.header || '');
            body.append('template_body', payload.template.body || '');
            body.append('interval', el.interval.value);
        }

        if (has(channels, 'email')) {
            const mail = mailPayload();
            if (! mail) return;
            body.append('email_template_id', el.mailTpl.value);
            mailAttachments.forEach(function (att) { body.append('email_attachments[]', att.path); });
            body.append('email_subject', mail.subject);
            body.append('email_body', mail.body);
            body.append('email_interval', el.mailGap.value);
        }

        body.append('skip_contacted', el.skip.checked ? '1' : '0');

        setBusy(true);
        post(bulk.url + (bulk.all ? window.location.search : ''), body)
            .then(function (res) {
                if (! res.ok) return showError(firstError(res.data, 'Nothing was queued.'));
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                toast(res.data.message || 'Queued.', res.data.links);
                document.querySelectorAll('.wa-row-check:checked').forEach(function (cb) { cb.checked = false; });
                updateSelection();
                reloadList();
                // A list with its own loader (Leads) refreshes on this.
                document.dispatchEvent(new CustomEvent('bulk-send:queued'));
            })
            .catch(function () { showError('Could not reach the server. Please try again.'); })
            .finally(function () { setBusy(false); });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideError();

        // Bulk can be email only; a single message needs the Cloud API.
        if (bulk) {
            sendBulk();
        } else if (API_READY) {
            sendSingle();
        }
    });

    // ── Open in WhatsApp (wa.me, single only) ─────────────────────────────

    el.openChat.addEventListener('click', function () {
        hideError();
        if (! el.phone.value.trim()) return showError('Enter the WhatsApp number.');

        const text = mode() === 'template' ? previewText() : el.body.value;

        // Opened inside the click so the browser does not block it as a pop-up;
        // pointed at wa.me once the server has normalised and logged the number.
        const win = window.open('', '_blank');
        if (win) win.opener = null;

        const body = new FormData();
        body.append('source', el.source.value);
        body.append('id', el.recordId.value);
        body.append('name', el.name.value.trim());
        body.append('phone', el.phone.value.trim());
        body.append('body', text);

        post(modalEl.dataset.openUrl, body)
            .then(function (res) {
                if (! res.ok) {
                    if (win) win.close();
                    return showError(firstError(res.data, 'Could not open the chat.'));
                }
                if (win) {
                    win.location.href = res.data.url;
                } else {
                    window.location.href = res.data.url;
                }
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                reloadList();
            })
            .catch(function () {
                if (win) win.close();
                showError('Could not reach the server. Please try again.');
            });
    });
})();
</script>
@endverbatim
@endpush
@endif
