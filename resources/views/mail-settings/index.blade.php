@extends('layouts.app')

@section('title', 'Mail Settings — Settings')
@section('page-title', 'Mail Settings')

@section('breadcrumb')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">Mail Settings</li>
@endsection

@section('content')

@php
    $smtpEnc = old('encryption', $smtp->encryption ?? 'tls');
    $imapEnc = old('encryption', $imap->encryption ?? 'ssl');
@endphp

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header py-3 d-block">
                <h4 class="card-title"><i class="bi bi-envelope-gear me-2 text-primary"></i>Mail Settings</h4>
                <p class="mb-0 fs-13">Credentials saved here are used to send emails. If nothing is saved, the values from <code>.env</code> are used.</p>
            </div>

            <div class="card-body">
                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link {{ old('_tab') === 'imap' ? '' : 'active' }}" data-bs-toggle="tab" data-bs-target="#tab-smtp" type="button">
                            <i class="bi bi-send me-1"></i>SMTP (Sending)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link {{ old('_tab') === 'imap' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-imap" type="button">
                            <i class="bi bi-inbox me-1"></i>IMAP (Sent folder)
                        </button>
                    </li>
                </ul>

                <div class="tab-content">

                    {{-- SMTP --}}
                    <div class="tab-pane fade {{ old('_tab') === 'imap' ? '' : 'show active' }}" id="tab-smtp">
                        <form method="POST" action="{{ route('mail-settings.smtp') }}" id="smtpForm" data-test-url="{{ route('mail-settings.test', 'smtp') }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="_tab" value="smtp">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SMTP Host <span class="text-danger">*</span></label>
                                    <input type="text" name="host" class="form-control @error('host') is-invalid @enderror"
                                           value="{{ old('host', $smtp->host ?? '') }}" placeholder="smtp.example.com" required>
                                    @error('host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Port <span class="text-danger">*</span></label>
                                    <input type="number" name="port" class="form-control @error('port') is-invalid @enderror"
                                           value="{{ old('port', $smtp->port ?? 587) }}" required>
                                    @error('port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Encryption</label>
                                    <select name="encryption" class="form-select">
                                        <option value="tls"  {{ $smtpEnc === 'tls'  ? 'selected' : '' }}>TLS (STARTTLS, 587)</option>
                                        <option value="ssl"  {{ $smtpEnc === 'ssl'  ? 'selected' : '' }}>SSL (465)</option>
                                        <option value="none" {{ $smtpEnc === 'none' ? 'selected' : '' }}>None</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" autocomplete="off"
                                           value="{{ old('username', $smtp->username ?? '') }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" autocomplete="new-password"
                                           placeholder="{{ $smtp?->getRawOriginal('password') ? '•••••••• (leave blank to keep current)' : '' }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">From Email <span class="text-danger">*</span></label>
                                    <input type="email" name="from_address" class="form-control @error('from_address') is-invalid @enderror"
                                           value="{{ old('from_address', $smtp->from_address ?? '') }}" required>
                                    @error('from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">From Name</label>
                                    <input type="text" name="from_name" class="form-control"
                                           value="{{ old('from_name', $smtp->from_name ?? '') }}">
                                </div>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input type="checkbox" class="form-check-input" name="is_active" value="1" id="smtpActive"
                                       {{ old('is_active', $smtp->is_active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="smtpActive">Active (use these settings for sending)</label>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save SMTP</button>
                                <button type="button" class="btn btn-light btn-test"><i class="bi bi-plug me-1"></i>Test Connection</button>
                                <span class="test-result fs-13"></span>
                            </div>
                        </form>
                    </div>

                    {{-- IMAP --}}
                    <div class="tab-pane fade {{ old('_tab') === 'imap' ? 'show active' : '' }}" id="tab-imap">
                        <form method="POST" action="{{ route('mail-settings.imap') }}" id="imapForm" data-test-url="{{ route('mail-settings.test', 'imap') }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="_tab" value="imap">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">IMAP Host <span class="text-danger">*</span></label>
                                    <input type="text" name="host" class="form-control @error('host') is-invalid @enderror"
                                           value="{{ old('host', $imap->host ?? '') }}" placeholder="imap.example.com" required>
                                    @error('host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Port <span class="text-danger">*</span></label>
                                    <input type="number" name="port" class="form-control @error('port') is-invalid @enderror"
                                           value="{{ old('port', $imap->port ?? 993) }}" required>
                                    @error('port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Encryption</label>
                                    <select name="encryption" class="form-select">
                                        <option value="ssl"   {{ $imapEnc === 'ssl'   ? 'selected' : '' }}>SSL (993)</option>
                                        <option value="tls"   {{ $imapEnc === 'tls'   ? 'selected' : '' }}>TLS (STARTTLS, 143)</option>
                                        <option value="notls" {{ $imapEnc === 'notls' ? 'selected' : '' }}>None</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" name="username" class="form-control @error('username') is-invalid @enderror" autocomplete="off"
                                           value="{{ old('username', $imap->username ?? '') }}" required>
                                    @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" autocomplete="new-password"
                                           placeholder="{{ $imap?->getRawOriginal('password') ? '•••••••• (leave blank to keep current)' : '' }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sent Folder <span class="text-danger">*</span></label>
                                    <input type="text" name="folder" class="form-control @error('folder') is-invalid @enderror"
                                           value="{{ old('folder', $imap->folder ?? 'INBOX.Sent') }}" required>
                                    <div class="form-text">Sent emails are copied here (e.g. <code>INBOX.Sent</code> or <code>[Gmail]/Sent Mail</code>).</div>
                                    @error('folder')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input type="checkbox" class="form-check-input" name="is_active" value="1" id="imapActive"
                                       {{ old('is_active', $imap->is_active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="imapActive">Active (copy sent emails to this mailbox)</label>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save IMAP</button>
                                <button type="button" class="btn btn-light btn-test"><i class="bi bi-plug me-1"></i>Test Connection</button>
                                <span class="test-result fs-13"></span>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    document.querySelectorAll('.btn-test').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const form   = btn.closest('form');
            const result = form.querySelector('.test-result');
            const body   = new FormData(form);
            body.delete('_method');

            btn.disabled = true;
            result.className = 'test-result fs-13 text-muted';
            result.textContent = 'Testing…';

            try {
                const res  = await fetch(form.dataset.testUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body,
                });
                const json = await res.json();
                result.className = 'test-result fs-13 ' + (json.ok ? 'text-success' : 'text-danger');
                result.textContent = json.message || (res.ok ? 'OK' : 'Request failed.');
            } catch (e) {
                result.className = 'test-result fs-13 text-danger';
                result.textContent = 'Request failed.';
            } finally {
                btn.disabled = false;
            }
        });
    });
</script>
@endpush
