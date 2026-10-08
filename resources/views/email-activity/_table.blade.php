{{--
    Email Activity table + empty state. Swapped in via AJAX on every filter,
    search or page change by assets/js/ajax-filters.js, so it must stay
    self-contained: no <script> here.
--}}
@php
    $badges = [
        'not_opened' => ['class' => 'badge-secondary', 'icon' => 'bi-envelope',      'label' => 'Not opened'],
        'opened'     => ['class' => 'badge-info',      'icon' => 'bi-envelope-open', 'label' => 'Opened'],
        'replied'    => ['class' => 'badge-success',   'icon' => 'bi-reply',         'label' => 'Replied'],
    ];
@endphp
<div class="ajax-content">
@if($emails->isEmpty())
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-activity empty-state-icon"></i>
            No sent emails match.
            @if($activity || $search)
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
                        <th class="mw-150">Lead</th>
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
                        @php $b = $badges[$email->activity]; @endphp
                        <tr>
                            <td>{{ $emails->firstItem() + $loop->index }}</td>
                            <td>
                                <h6 class="mb-0">{{ $email->lead?->company_name ?? '—' }}</h6>
                                <small class="text-muted">{{ $email->lead?->email }}</small>
                            </td>
                            <td>{{ $email->subject }}</td>
                            <td>{{ $email->sent_at?->format('d M Y, h:i A') }}</td>
                            <td>
                                <span class="badge {{ $b['class'] }} light">
                                    <i class="bi {{ $b['icon'] }} me-1"></i>{{ $b['label'] }}
                                </span>
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
