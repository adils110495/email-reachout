{{-- Day-grouped timeline: "08 Oct / Email sent / Opened / ..." --}}
@php $groups = $events->groupBy(fn ($e) => \App\Sequencer\Support\Tz::format($e->occurred_at, 'd M Y')); @endphp
@forelse ($groups as $day => $items)
    <div class="fw-semibold text-muted fs-13 mt-3 mb-1">{{ $day }}</div>
    @foreach ($items as $event)
        <div class="d-flex gap-2 py-1">
            <i class="bi {{ $event->type->icon() }} text-{{ $event->type->badge() }} mt-1"></i>
            <div>
                <span class="fw-medium">{{ $event->type->label() }}</span>
                <span class="text-muted fs-13">· @localtime($event->occurred_at, 'H:i')</span>
                <div class="fs-13 text-muted">{{ $event->description }}</div>
            </div>
        </div>
    @endforeach
@empty
    <div class="text-muted fs-13">No activity yet.</div>
@endforelse
