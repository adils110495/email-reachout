@extends('layouts.app')

@section('title', 'WhatsApp Settings — Settings')
@section('page-title', 'WhatsApp Settings')

@section('breadcrumb')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">WhatsApp</li>
@endsection

@section('content')

<div class="row">
    {{-- Connection --}}
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header py-3 d-block">
                <h4 class="card-title"><i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp Cloud API</h4>
                <p class="mb-0 fs-13">
                    Meta's official API: send from this app and see delivered, read and replied.
                    Without it, messages still go out with <em>Open in WhatsApp</em> from your own phone or WhatsApp Web.
                </p>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('whatsapp-settings.update') }}" id="waSettingsForm"
                      data-test-url="{{ route('whatsapp-settings.test') }}">
                    @csrf @method('PUT')

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="wa_cc">Default country code</label>
                            <div class="input-group">
                                <span class="input-group-text">+</span>
                                <input type="text" name="default_country_code" id="wa_cc" inputmode="numeric"
                                       class="form-control @error('default_country_code') is-invalid @enderror"
                                       value="{{ old('default_country_code', $setting->default_country_code ?? '') }}" placeholder="91" maxlength="5">
                            </div>
                            <div class="form-text">For numbers saved without one.</div>
                            @error('default_country_code')<div class="text-danger fs-13">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label" for="wa_phone_id">Phone Number ID</label>
                            <input type="text" name="phone_number_id" id="wa_phone_id" inputmode="numeric" autocomplete="off"
                                   class="form-control @error('phone_number_id') is-invalid @enderror"
                                   value="{{ old('phone_number_id', $setting->phone_number_id ?? '') }}" placeholder="e.g. 106540352242922">
                            <div class="form-text">Meta &rsaquo; your app &rsaquo; WhatsApp &rsaquo; API Setup.</div>
                            @error('phone_number_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" for="wa_waba_id">WhatsApp Business Account ID</label>
                            <input type="text" name="business_account_id" id="wa_waba_id" inputmode="numeric" autocomplete="off"
                                   class="form-control @error('business_account_id') is-invalid @enderror"
                                   value="{{ old('business_account_id', $setting->business_account_id ?? '') }}" placeholder="e.g. 102290129340398">
                            <div class="form-text">Same page. Needed to list your approved message templates.</div>
                            @error('business_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" for="wa_token">Access Token</label>
                            <input type="password" name="access_token" id="wa_token" class="form-control" autocomplete="new-password"
                                   placeholder="{{ $setting?->getRawOriginal('access_token') ? '•••••••• (leave blank to keep current)' : 'Permanent System User token' }}">
                            <div class="form-text">
                                Use a permanent System User token (permissions <code>whatsapp_business_messaging</code> and
                                <code>whatsapp_business_management</code>) - the temporary token on API Setup expires in 24 hours.
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" for="wa_secret">App Secret</label>
                            <input type="password" name="app_secret" id="wa_secret" class="form-control" autocomplete="new-password"
                                   placeholder="{{ $setting?->getRawOriginal('app_secret') ? '•••••••• (leave blank to keep current)' : 'Needed for delivery / read / reply tracking' }}">
                            <div class="form-text">Meta &rsaquo; your app &rsaquo; App settings &rsaquo; Basic. Every webhook call is checked against it.</div>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" class="form-check-input" name="is_active" value="1" id="wa_active"
                               {{ old('is_active', $setting->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="wa_active">Active (send through the Cloud API)</label>
                    </div>

                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save</button>
                        <button type="button" class="btn btn-light" id="waTestBtn"><i class="bi bi-plug me-1"></i>Test Connection</button>
                        <span class="fs-13" id="waTestResult"></span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Webhook + setup steps. h-auto: the theme stretches every .card to its
         column's height, which with two stacked cards pushes the second one
         out of the column and over the footer. --}}
    <div class="col-xl-5">
        <div class="card h-auto">
            <div class="card-header py-3 d-block">
                <h4 class="card-title"><i class="bi bi-broadcast me-2 text-primary"></i>Webhook</h4>
                <p class="mb-0 fs-13">Meta calls this URL for delivered / read / failed and for replies.</p>
            </div>
            <div class="card-body">
                {{-- Where the app can be reached, and the token Meta must send back --}}
                <form method="POST" action="{{ route('whatsapp-settings.webhook') }}" class="mb-3">
                    @csrf @method('PUT')
                    <label class="form-label" for="waPublicUrl">Public URL of this app</label>
                    <input type="url" name="public_url" id="waPublicUrl" autocomplete="off"
                           class="form-control @error('public_url') is-invalid @enderror"
                           value="{{ old('public_url', $setting->public_url) }}" placeholder="https://app.example.com" maxlength="140">
                    @error('public_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text mb-3">
                        Your live domain - or an <strong>ngrok / cloudflared</strong> tunnel while testing on localhost.
                        Blank: the address this page is open on.
                    </div>

                    <label class="form-label" for="waVerifyToken">Verify token</label>
                    <div class="input-group has-validation">
                        <input type="text" name="verify_token" id="waVerifyToken" autocomplete="off"
                               class="form-control @error('verify_token') is-invalid @enderror"
                               value="{{ old('verify_token', $setting->verify_token) }}" minlength="8" maxlength="64">
                        <button type="button" class="btn btn-light btn-copy" data-copy="#waVerifyToken" title="Copy" aria-label="Copy verify token"><i class="bi bi-clipboard"></i></button>
                        @error('verify_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-text mb-3">Any secret text (8+ characters). Meta must be given exactly the same one.</div>

                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Save webhook</button>
                </form>

                <label class="form-label" for="waWebhookUrl">Callback URL</label>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="waWebhookUrl" value="{{ $webhookUrl }}" readonly>
                    <button type="button" class="btn btn-light btn-copy" data-copy="#waWebhookUrl" title="Copy" aria-label="Copy callback URL"><i class="bi bi-clipboard"></i></button>
                </div>

                @if(! str_starts_with($webhookUrl, 'https://'))
                    <div class="alert alert-warning fs-13 mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Meta only calls <strong>https</strong> URLs reachable from the internet. Enter your public URL above.
                    </div>
                @endif
                @if(! $setting->getRawOriginal('app_secret'))
                    <div class="alert alert-warning fs-13 mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Add the <strong>App Secret</strong> - webhook calls are refused without it.
                    </div>
                @endif

                {{-- Hand both to Meta from here (webhook override on the Business Account) --}}
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <button type="button" class="btn btn-success btn-sm" id="waWebhookRegister"
                            data-url="{{ route('whatsapp-settings.webhook-register') }}">
                        <i class="bi bi-cloud-upload me-1"></i>Set in Meta
                    </button>
                    <button type="button" class="btn btn-light btn-sm" id="waWebhookCheck"
                            data-url="{{ route('whatsapp-settings.webhook-status') }}">
                        <i class="bi bi-search me-1"></i>Check
                    </button>
                </div>
                <div class="fs-13 mb-3" id="waWebhookResult" role="status"></div>

                <p class="fs-13 text-muted mb-0">
                    <strong>Set in Meta</strong> registers this URL and token for your WhatsApp Business Account - Meta checks the URL on the spot, so the app must already be reachable there.
                    Or paste both yourself in Meta &rsaquo; WhatsApp &rsaquo; Configuration. Either way, the <code>messages</code> field must be subscribed there.
                </p>
            </div>
        </div>

        <div class="card h-auto">
            <div class="card-header py-3">
                <h4 class="card-title"><i class="bi bi-list-check me-2 text-primary"></i>Setup steps</h4>
            </div>
            <div class="card-body fs-13">
                <ol class="numbered-steps">
                    <li>On <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">developers.facebook.com</a>, create a <strong>Business</strong> app and add the <strong>WhatsApp</strong> product.</li>
                    <li>WhatsApp &rsaquo; API Setup: add your business number, copy the <strong>Phone Number ID</strong> and <strong>WhatsApp Business Account ID</strong>.</li>
                    <li>Business Settings &rsaquo; System Users: generate a <strong>permanent token</strong> for the app.</li>
                    <li>App settings &rsaquo; Basic: copy the <strong>App Secret</strong>. Save everything here and press <em>Test Connection</em>.</li>
                    <li>Webhook card: enter your <strong>public URL</strong>, save, then press <strong>Set in Meta</strong> (or paste the callback URL and token in Meta &rsaquo; WhatsApp &rsaquo; Configuration). Subscribe the <code>messages</code> field there.</li>
                    <li>WhatsApp Manager &rsaquo; Message templates: create templates. A first message to a contact must use one that Meta has <strong>approved</strong>.</li>
                </ol>
                <p class="text-muted mt-3 mb-0">
                    Graph API version: <code>{{ $apiVersion }}</code>
                    {{-- Only someone with server access can change it, so only they need to know where. --}}
                    @if(auth()->user()->isSuperAdmin())
                        <span class="d-block fs-12 mt-1">Set by <code>WHATSAPP_API_VERSION</code> in <code>.env</code>.</span>
                    @endif
                </p>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    (function () {
        const form   = document.getElementById('waSettingsForm');
        const btn    = document.getElementById('waTestBtn');
        const result = document.getElementById('waTestResult');

        btn.addEventListener('click', async function () {
            const body = new FormData(form);
            body.delete('_method');

            btn.disabled = true;
            result.className = 'fs-13 text-muted';
            result.textContent = 'Testing…';

            try {
                const res  = await fetch(form.dataset.testUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body,
                });
                const json = await res.json();
                const msg  = json.errors ? Object.values(json.errors)[0][0] : json.message;
                result.className = 'fs-13 ' + (json.ok ? 'text-success' : 'text-danger');
                result.textContent = msg || (res.ok ? 'OK' : 'Request failed.');
            } catch (e) {
                result.className = 'fs-13 text-danger';
                result.textContent = 'Request failed.';
            } finally {
                btn.disabled = false;
            }
        });

        // "Set in Meta" / "Check": both answer { ok, message } and share one result line.
        const hookResult = document.getElementById('waWebhookResult');

        [['waWebhookRegister', 'POST'], ['waWebhookCheck', 'GET']].forEach(function (pair) {
            const hookBtn = document.getElementById(pair[0]);
            if (! hookBtn) return;

            hookBtn.addEventListener('click', async function () {
                hookBtn.disabled = true;
                hookResult.className = 'fs-13 mb-3 text-muted';
                hookResult.textContent = pair[1] === 'POST' ? 'Sending to Meta - it checks the URL now…' : 'Asking Meta…';

                try {
                    const res = await fetch(hookBtn.dataset.url, {
                        method: pair[1],
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });
                    const json = await res.json();
                    hookResult.className = 'fs-13 mb-3 ' + (json.ok ? 'text-success' : 'text-danger');
                    hookResult.textContent = json.message || (res.ok ? 'OK' : 'Request failed.');
                } catch (e) {
                    hookResult.className = 'fs-13 mb-3 text-danger';
                    hookResult.textContent = 'Request failed.';
                } finally {
                    hookBtn.disabled = false;
                }
            });
        });

        document.querySelectorAll('.btn-copy').forEach(function (copyBtn) {
            copyBtn.addEventListener('click', function () {
                const input = document.querySelector(copyBtn.dataset.copy);
                const icon  = copyBtn.querySelector('i');
                const done  = function () {
                    icon.className = 'bi bi-clipboard-check text-success';
                    window.setTimeout(function () { icon.className = 'bi bi-clipboard'; }, 1500);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(input.value).then(done);
                } else {
                    input.select();
                    document.execCommand('copy');
                    done();
                }
            });
        });
    })();
</script>
@endpush
