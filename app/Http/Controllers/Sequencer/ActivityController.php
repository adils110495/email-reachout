<?php

namespace App\Http\Controllers\Sequencer;

use App\Models\ActivityEvent;
use App\Models\Lead;
use App\Models\Sequence;
use App\Sequencer\Enums\ActivityType;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The activity timeline: email queued -> sent -> opened -> clicked -> reply -> sequence stopped. */
class ActivityController extends SequencerController
{
    public function index(Request $request): View
    {
        $leadId = $request->integer('lead') ?: null;

        $events = ActivityEvent::query()
            ->when($request->query('type'), fn ($q, $t) => ActivityType::tryFrom($t) ? $q->where('type', $t) : $q)
            ->when($request->integer('sequence'), fn ($q, $id) => $q->where('sequence_id', $id))
            ->when($leadId, fn ($q, $id) => $q->where('lead_id', $id))
            ->with(['lead', 'sequence:id,name'])
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();

        return view('sequencer.activity.index', [
            'events' => $events,
            'types' => ActivityType::cases(),
            'sequences' => Sequence::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['type', 'sequence', 'lead']),
            'lead' => $leadId ? Lead::find($leadId) : null,
        ]);
    }
}
