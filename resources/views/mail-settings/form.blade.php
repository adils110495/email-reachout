@extends('layouts.app')

@php
    $editing = $account->exists;
    $smtpEnc = old('encryption', $account->encryption ?? 'tls');
    $imapEnc = old('imap_encryption', $account->imap_encryption === 'notls' ? 'none' : ($account->imap_encryption ?? 'ssl'));
@endphp

@section('title', ($editing ? 'Edit' : 'Add').' Account — Mail Settings')
@section('page-title', $editing ? 'Edit Account' : 'Add Account')

@section('breadcrumb')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item"><a href="{{ route('mail-settings.index') }}">Mail Settings</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $editing ? 'Edit' : 'Add' }}</li>
@endsection

@section('content')

@if ($errors->any())
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix the following:
        <ul class="mb-0 mt-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

@if ($editing && ($unreadable = $account->unreadableSecrets()))
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        The saved {{ implode(' and ', $unreadable) }} password can't be read because the application key (APP_KEY) changed
        after it was saved. Enter it again below and save.
    </div>
@endif

<form method="POST" id="accountForm" autocomplete="off"
      action="{{ $editing ? route('mail-settings.update', $account) : route('mail-settings.store') }}"
      data-test-url="{{ route('mail-settings.test') }}">
    @csrf
    @if ($editing) @method('PUT') <input type="hidden" name="account_id" value="{{ $account->id }}"> @endif

    <div class="card">
        <div class="card-header"><h4 class="card-title mb-0"><i class="bi bi-person-badge me-2 text-primary"></i>Account</h4></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="name">Account Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $account->name) }}" placeholder="e.g. Sales mailbox" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="provider">Provider</label>
                    <select id="provider" name="provider" class="form-select">
                        @foreach ($providers as $key => $label)<option value="{{ $key }}" @selected(old('provider', $account->provider ?: 'smtp') === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="from_address">From Email <span class="text-danger">*</span></label>
                    <input type="email" id="from_address" name="from_address" class="form-control" value="{{ old('from_address', $account->from_address) }}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="from_name">From Name</label>
                    <input type="text" id="from_name" name="from_name" class="form-control" value="{{ old('from_name', $account->from_name) }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h4 class="card-title mb-0"><i class="bi bi-send me-2 text-primary"></i>SMTP (Sending)</h4></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="host">SMTP Host <span class="text-danger">*</span></label>
                    <input type="text" id="host" name="host" class="form-control" value="{{ old('host', $account->host) }}" placeholder="smtp.example.com" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="port">Port <span class="text-danger">*</span></label>
                    <input type="number" id="port" name="port" class="form-control" value="{{ old('port', $account->port ?? 587) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="encryption">Encryption</label>
                    <select id="encryption" name="encryption" class="form-select">
                        <option value="tls"  @selected($smtpEnc === 'tls')>TLS (STARTTLS, 587)</option>
                        <option value="ssl"  @selected($smtpEnc === 'ssl')>SSL (465)</option>
                        <option value="none" @selected($smtpEnc === 'none')>None</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" autocomplete="off" value="{{ old('username', $account->username) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" autocomplete="new-password"
                           placeholder="{{ $editing && $account->hasStoredSecret('password') ? '•••••••• (leave blank to keep current)' : '' }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-block">
            <h4 class="card-title mb-0"><i class="bi bi-inbox me-2 text-primary"></i>IMAP <small class="text-muted">optional</small></h4>
            <p class="mb-0 fs-13">Used to copy sent emails to the Sent folder and to detect replies and bounces. Leave the host empty to switch both off for this account.</p>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="imap_host">IMAP Host</label>
                    <input type="text" id="imap_host" name="imap_host" class="form-control" value="{{ old('imap_host', $account->imap_host) }}" placeholder="imap.example.com">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="imap_port">Port</label>
                    <input type="number" id="imap_port" name="imap_port" class="form-control" value="{{ old('imap_port', $account->imap_port ?? 993) }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="imap_encryption">Encryption</label>
                    <select id="imap_encryption" name="imap_encryption" class="form-select">
                        <option value="ssl"  @selected($imapEnc === 'ssl')>SSL (993)</option>
                        <option value="tls"  @selected($imapEnc === 'tls')>TLS (STARTTLS, 143)</option>
                        <option value="none" @selected($imapEnc === 'none')>None</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="imap_username">Username</label>
                    <input type="text" id="imap_username" name="imap_username" class="form-control" autocomplete="off" value="{{ old('imap_username', $account->imap_username) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="imap_password">Password</label>
                    <input type="password" id="imap_password" name="imap_password" class="form-control" autocomplete="new-password"
                           placeholder="{{ $editing && $account->hasStoredSecret('imap_password') ? '•••••••• (leave blank to keep current)' : '' }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="folder">Sent Folder</label>
                    <input type="text" id="folder" name="folder" class="form-control" value="{{ old('folder', $account->folder ?? 'INBOX.Sent') }}">
                    <div class="form-text">Sent emails are copied here (e.g. <code>INBOX.Sent</code> or <code>[Gmail]/Sent Mail</code>).</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="imap_folder">Inbox Folder</label>
                    <input type="text" id="imap_folder" name="imap_folder" class="form-control" value="{{ old('imap_folder', $account->imap_folder ?: 'INBOX') }}">
                    <div class="form-text">Checked every 2 minutes for replies and bounces.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h4 class="card-title mb-0"><i class="bi bi-speedometer2 me-2 text-primary"></i>Sequence Sending Limits</h4></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="rate_limit_per_minute">Emails per minute <span class="text-danger">*</span></label>
                    <input type="number" id="rate_limit_per_minute" name="rate_limit_per_minute" class="form-control" min="1" max="1000"
                           value="{{ old('rate_limit_per_minute', $account->rate_limit_per_minute ?? 10) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="daily_limit">Daily cap for this account</label>
                    <input type="number" id="daily_limit" name="daily_limit" class="form-control" min="1" value="{{ old('daily_limit', $account->daily_limit) }}" placeholder="No cap">
                </div>
            </div>
            <div class="form-check form-switch mb-2">
                <input type="checkbox" class="form-check-input" name="is_active" value="1" id="isActive" @checked(old('is_active', $account->is_active ?? true))>
                <label class="form-check-label" for="isActive">Active (use this account for sending)</label>
            </div>
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="is_default" value="1" id="isDefault" @checked(old('is_default', $account->is_default))>
                <label class="form-check-label" for="isDefault">Default account (used by the Leads page)</label>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Account</button>
        <button type="button" class="btn btn-light btn-test"><i class="bi bi-plug me-1"></i>Test Connection</button>
        <a href="{{ route('mail-settings.index') }}" class="btn btn-link">Cancel</a>
        <span class="test-result fs-13 w-100" role="status"></span>
    </div>
</form>

@endsection

@push('scripts')
<script>
    document.querySelector('.btn-test').addEventListener('click', async function () {
        const btn = this, form = document.getElementById('accountForm'), result = form.querySelector('.test-result');
        const body = new FormData(form);
        body.delete('_method');

        btn.disabled = true;
        result.className = 'test-result fs-13 w-100 text-muted';
        result.textContent = 'Testing…';

        try {
            const res  = await fetch(form.dataset.testUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: body });
            const json = await res.json();

            if (res.status === 422) {
                result.className = 'test-result fs-13 w-100 text-danger';
                result.textContent = Object.values(json.errors || {}).flat().join(' ');
                return;
            }

            result.className = 'test-result fs-13 w-100';
            result.replaceChildren(...[['SMTP', json.smtp], ['IMAP', json.imap]].map(([name, r]) => {
                const line = document.createElement('div');
                line.className = r ? (r.ok ? 'text-success' : 'text-danger') : 'text-muted';
                line.textContent = name + ': ' + (r ? r.message : 'not configured');
                return line;
            }));
        } catch (e) {
            result.className = 'test-result fs-13 w-100 text-danger';
            result.textContent = 'Request failed.';
        } finally {
            btn.disabled = false;
        }
    });
</script>
@endpush
