{{--
    Email Activity table + empty state. Swapped in via AJAX on every filter,
    search or page change by assets/js/ajax-filters.js, so it must stay
    self-contained: no <script> here. The bulk progress panel is polled by
    assets/js/bulk-progress.js (loaded by index.blade.php).
--}}
@php
    $badges = [
        'not_opened' => ['class' => 'badge-secondary', 'icon' => 'bi-envelope',      'label' => 'Not opened'],
        'opened'     => ['class' => 'badge-info',      'icon' => 'bi-envelope-open', 'label' => 'Opened'],
        'replied'    => ['class' => 'badge-success',   'icon' => 'bi-reply',         'label' => 'Replied'],
    ];
    // Emails of a bulk send that have not gone out (only listed in the bulk's view).
    $statusBadges = [
        \App\Models\LeadEmail::STATUS_QUEUED    => ['class' => 'badge-light',  'icon' => 'bi-hourglass-split', 'label' => 'Queued'],
        \App\Models\LeadEmail::STATUS_SENDING   => ['class' => 'badge-light',  'icon' => 'bi-arrow-repeat',    'label' => 'Sending'],
        \App\Models\LeadEmail::STATUS_FAILED    => ['class' => 'badge-danger', 'icon' => 'bi-x-circle',        'label' => 'Failed'],
        \App\Models\LeadEmail::STATUS_CANCELLED => ['class' => 'badge-dark',   'icon' => 'bi-slash-circle',    'label' => 'Cancelled'],
    ];
@endphp
<div class="ajax-content">

{{-- Bulk send progress: a running one is polled and the list swapped as it moves --}}
@if($batchProgress)
    @php
        $p     = $batchProgress;
        $chips = [
            'pending'   => ['Waiting',   'light'],
            'sent'      => ['Sent',      'secondary'],
            'opened'    => ['Opened',    'info'],
            'replied'   => ['Replied',   'success'],
            'failed'    => ['Failed',    'danger'],
            'cancelled' => ['Cancelled', 'dark'],
        ];
    @endphp
    <div class="card-body border-bottom"
         data-batch-status-url="{{ route('email-activity.batch-status', $batch) }}"
         data-batch-running="{{ $p['running'] ? '1' : '0' }}"
         data-batch-done="{{ $p['done'] }}">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
            <div>
                <h5 class="mb-1"><i class="bi bi-stack me-1 text-primary"></i>Bulk email</h5>
                <small class="text-muted">
                    @if($batchFirst){{ $batchFirst->created_at->format('d M Y, h:i A') }} · @endif
                    {{ number_format($p['done']) }} of {{ number_format($p['total']) }} done{{ $p['running'] ? ' - sending…' : '' }}
                </small>
            </div>
            <div class="d-flex gap-2">
                @if($p['pending'] > 0)
                    <form method="POST" action="{{ route('email-activity.batch-cancel', $batch) }}"
                          onsubmit="return confirm('Stop this bulk send? Emails not sent yet are cancelled.')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-danger light"><i class="bi bi-stop-circle me-1"></i>Stop</button>
                    </form>
                @endif
                <a href="{{ route('email-activity.index') }}" class="btn btn-sm btn-light">All emails</a>
            </div>
        </div>
        <div class="progress bulk-progress mb-3">
            <div class="progress-bar bg-primary {{ $p['running'] ? 'progress-bar-striped progress-bar-animated' : '' }}"
                 role="progressbar" style="width: {{ $p['progress'] }}%"
                 aria-valuenow="{{ $p['progress'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Bulk send progress"></div>
        </div>
        <div class="chip-row">
            @foreach($chips as $key => [$label, $colour])
                <span class="stat-chip">
                    <span class="badge badge-{{ $colour }} light">{{ $label }}</span>
                    <strong>{{ number_format($p[$key]) }}</strong>
                </span>
            @endforeach
        </div>
    </div>
@endif

@if($emails->isEmpty())
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-activity empty-state-icon"></i>
            No {{ $batch ? '' : 'sent ' }}emails match.
            @if($activity || $search || $owner || $batch)
                <a href="{{ route('email-activity.index') }}">Clear the filters</a>.
            @endif
        </div>
    </div>
@else
    <div class="card-body table-card-body px-0 pt-0 pb-2">
        <div class="table-responsive">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th class="mw-150">Recipient</th>
                        @if($isSuperAdmin)
                            <th>User</th>
                        @endif
                        <th class="mw-200">Subject</th>
                        <th>Sent</th>
                        <th>Activity</th>
                        <th class="text-center">Opens</th>
                        <th>Last opened</th>
                        <th>Replied</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($emails as $email)
                        @php $b = $statusBadges[$email->status] ?? $badges[$email->activity]; @endphp
                        <tr>
                            <td>{{ $emails->firstItem() + $loop->index }}</td>
                            <td>
                                <h6 class="mb-0">{{ $email->recipient_name ?? '—' }}</h6>
                                <small class="text-muted">{{ $email->recipient_email }}</small>
                            </td>
                            @if($isSuperAdmin)
                                <td class="text-nowrap">{{ $email->user?->name ?? '—' }}</td>
                            @endif
                            <td>
                                @if($email->batch_id && ! $batch)
                                    <a href="{{ route('email-activity.index', ['batch' => $email->batch_id]) }}"
                                       class="badge badge-info light fs-12 mb-1 text-decoration-none" title="Part of a bulk send - see all of it">
                                        <i class="bi bi-stack me-1"></i>Bulk
                                    </a>
                                @endif
                                <div>{{ $email->subject }}</div>
                            </td>
                            <td>{{ $email->status === \App\Models\LeadEmail::STATUS_SENT ? $email->sent_at?->format('d M Y, h:i A') : '—' }}</td>
                            <td>
                                <span class="badge {{ $b['class'] }} light">
                                    <i class="bi {{ $b['icon'] }} me-1"></i>{{ $b['label'] }}
                                </span>
                                @if($email->status === \App\Models\LeadEmail::STATUS_FAILED && $email->error)
                                    <div class="fs-12 text-danger mt-1 cell-wrap">{{ $email->error }}</div>
                                @endif
                            </td>
                            <td class="text-center">{{ $email->open_count }}</td>
                            <td>{{ $email->last_opened_at?->diffForHumans() ?? '—' }}</td>
                            <td>{{ $email->replied_at?->format('d M Y, h:i A') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($emails->hasPages())
        <div class="card-footer">{{ $emails->links() }}</div>
    @endif
@endif
</div>
