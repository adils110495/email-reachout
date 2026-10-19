{{--
    WhatsApp messages table + empty state. Swapped in via AJAX on every filter,
    search or page change by assets/js/ajax-filters.js, so it must stay
    self-contained: no <script> here. "Message again" is a .btn-wa-compose,
    handled by the delegated listener in whatsapp/_compose.blade.php; the bulk
    progress panel is polled by whatsapp/index.blade.php.
--}}
<div class="ajax-content" data-total="{{ $messages->total() }}">

{{-- Bulk send progress: a running one is polled and the list swapped as it moves --}}
@if($batchProgress)
    @php
        $p     = $batchProgress;
        $chips = [
            'pending'   => ['Waiting',   'light'],
            'accepted'  => ['Sent',      'secondary'],
            'delivered' => ['Delivered', 'info'],
            'read'      => ['Read',      'primary'],
            'replied'   => ['Replied',   'success'],
            'failed'    => ['Failed',    'danger'],
            'cancelled' => ['Cancelled', 'dark'],
        ];
    @endphp
    <div class="card-body border-bottom"
         data-batch-status-url="{{ route('whatsapp.batch-status', $batch) }}"
         data-batch-running="{{ $p['running'] ? '1' : '0' }}"
         data-batch-done="{{ $p['done'] }}">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
            <div>
                <h5 class="mb-1">
                    <i class="bi bi-stack me-1 text-success"></i>Bulk send
                    @if($batchFirst?->template_name)
                        <span class="badge badge-light light ms-1">{{ $batchFirst->template_name }}</span>
                    @endif
                </h5>
                <small class="text-muted">
                    @if($batchFirst){{ $batchFirst->created_at->format('d M Y, h:i A') }} · @endif
                    {{ number_format($p['done']) }} of {{ number_format($p['total']) }} done{{ $p['running'] ? ' - sending…' : '' }}
                </small>
            </div>
            <div class="d-flex gap-2">
                @if($p['pending'] > 0)
                    <form method="POST" action="{{ route('whatsapp.batch-cancel', $batch) }}"
                          onsubmit="return confirm('Stop this bulk send? Messages not sent yet are cancelled.')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-danger light"><i class="bi bi-stop-circle me-1"></i>Stop</button>
                    </form>
                @endif
                <a href="{{ route('whatsapp.index') }}" class="btn btn-sm btn-light">All messages</a>
            </div>
        </div>
        <div class="progress bulk-progress mb-3">
            <div class="progress-bar bg-success {{ $p['running'] ? 'progress-bar-striped progress-bar-animated' : '' }}"
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

@if($messages->isEmpty())
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-whatsapp empty-state-icon"></i>
            @if($activity || $search || $owner || $batch)
                No messages match. <a href="{{ route('whatsapp.index') }}">Clear the filters</a>.
            @else
                No WhatsApp messages yet. Message a GMB lead or a deal, or start a new message above.
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
                        <th class="mw-150">Contact</th>
                        @if($isSuperAdmin)
                            <th>User</th>
                        @endif
                        <th class="mw-220">Message</th>
                        <th>Sent</th>
                        <th>Status</th>
                        <th>Read</th>
                        <th class="mw-200">Reply</th>
                        <th style="width:80px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($messages as $message)
                        @php
                            $badge = $message->badge;
                            [$source, $sourceId] = match (true) {
                                (bool) $message->gmb_lead_id  => ['gmb', $message->gmb_lead_id],
                                (bool) $message->cash_lead_id => ['deal', $message->cash_lead_id],
                                default                       => ['direct', null],
                            };
                        @endphp
                        <tr>
                            <td>{{ $messages->firstItem() + $loop->index }}</td>
                            <td>
                                <h6 class="mb-0">{{ $message->contact_name ?: '—' }}</h6>
                                <small class="text-muted text-nowrap">{{ $message->display_phone }}</small>
                            </td>
                            @if($isSuperAdmin)
                                <td class="text-nowrap">{{ $message->user?->name ?? '—' }}</td>
                            @endif
                            <td>
                                @if($message->batch_id && ! $batch)
                                    <a href="{{ route('whatsapp.index', ['batch' => $message->batch_id]) }}"
                                       class="badge badge-info light fs-12 mb-1 text-decoration-none" title="Part of a bulk send - see all of it">
                                        <i class="bi bi-stack me-1"></i>Bulk
                                    </a>
                                @endif
                                @if($message->template_name)
                                    <span class="badge badge-light light fs-12 mb-1"><i class="bi bi-file-earmark-text me-1"></i>{{ $message->template_name }}</span>
                                @endif
                                <div class="fs-13 cell-wrap">{{ \Illuminate\Support\Str::limit($message->body, 120) ?: '—' }}</div>
                                @if($message->error)
                                    <div class="fs-12 text-danger mt-1">{{ $message->error }}</div>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                {{ $message->sent_at?->format('d M Y, h:i A') ?? '—' }}
                                <div class="fs-12 text-muted">
                                    {{ \App\Models\WhatsappMessage::CHANNEL_LABELS[$message->channel] ?? $message->channel }}
                                    @if($message->direction === \App\Models\WhatsappMessage::DIRECTION_IN) · received @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $badge['class'] }} light text-nowrap">
                                    <i class="bi {{ $badge['icon'] }} me-1"></i>{{ $badge['label'] }}
                                </span>
                            </td>
                            <td class="text-nowrap">{{ $message->read_at?->diffForHumans() ?? '—' }}</td>
                            <td>
                                @if($message->replied_at)
                                    <div class="fs-13 cell-wrap">{{ \Illuminate\Support\Str::limit($message->reply_body, 120) }}</div>
                                    <div class="fs-12 text-muted">{{ $message->replied_at->format('d M Y, h:i A') }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-light btn-square btn-wa-compose"
                                        title="Message again" aria-label="Message again"
                                        data-source="{{ $source }}"
                                        data-id="{{ $sourceId }}"
                                        data-name="{{ $message->contact_name }}"
                                        data-phone="{{ $message->display_phone }}">
                                    <i class="bi bi-whatsapp text-success"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($messages->hasPages())
        <div class="card-footer">{{ $messages->links() }}</div>
    @endif
@endif
</div>
