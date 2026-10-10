<?php

namespace App\Http\Controllers\Sequencer;

use App\Http\Requests\Sequencer\SequenceRequest;
use App\Models\MailSetting;
use App\Models\Sequence;
use App\Sequencer\Enums\SequenceStatus;
use App\Sequencer\Services\AnalyticsService;
use App\Sequencer\Services\SequenceService;
use App\Sequencer\Support\Tz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SequenceController extends SequencerController
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly AnalyticsService $analytics,
    ) {}

    public function index(Request $request): View
    {
        $sequences = Sequence::query()
            ->when($request->query('status'), fn ($q, $s) => in_array($s, SequenceStatus::values(), true) ? $q->where('status', $s) : $q)
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->withCount(['steps', 'enrollments'])
            ->latest('id')
            ->paginate($this->perPage())
            ->withQueryString();

        return view('sequencer.sequences.index', [
            'sequences' => $sequences,
            'statuses' => SequenceStatus::cases(),
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function create(): View
    {
        $user = $this->user();

        return view('sequencer.sequences.form', $this->formData(new Sequence([
            'timezone' => $user->timezoneName(),
            'sending_start_time' => $user->setting('sending_start_time', config('sequencer.defaults.sending_start')),
            'sending_end_time' => $user->setting('sending_end_time', config('sequencer.defaults.sending_end')),
            'sending_days' => $user->setting('sending_days', config('sequencer.defaults.sending_days')),
            'daily_limit' => $user->setting('daily_limit', config('sequencer.defaults.daily_limit')),
            'track_opens' => $user->setting('track_opens', true),
            'track_clicks' => $user->setting('track_clicks', true),
        ])));
    }

    public function store(SequenceRequest $request): RedirectResponse
    {
        $sequence = $this->sequences->create($this->user(), $request->validated());

        return redirect()->route('outreach.sequences.show', $sequence)->with('success', 'Sequence created. Add your steps, then activate it.');
    }

    public function show(Sequence $sequence): View
    {
        $sequence->load('steps', 'mailSetting');

        return view('sequencer.sequences.show', [
            'sequence' => $sequence,
            'analytics' => $this->analytics->sequence($sequence),
        ]);
    }

    public function edit(Sequence $sequence): View
    {
        return view('sequencer.sequences.form', $this->formData($sequence));
    }

    public function update(SequenceRequest $request, Sequence $sequence): RedirectResponse
    {
        $this->sequences->update($sequence, $request->validated());

        return redirect()->route('outreach.sequences.show', $sequence)->with('success', 'Sequence updated.');
    }

    public function destroy(Sequence $sequence): RedirectResponse
    {
        $sequence->delete();   // steps + enrollments cascade; sent emails stay in Email Activity

        return redirect()->route('outreach.sequences.index')->with('success', 'Sequence deleted.');
    }

    public function activate(Sequence $sequence): RedirectResponse
    {
        try {
            $this->sequences->activate($sequence);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', 'Sequence is active. Emails go out inside the sending window.');
    }

    public function pause(Sequence $sequence): RedirectResponse
    {
        $this->sequences->pause($sequence);

        return back()->with('success', 'Sequence paused. Nothing will be sent until you resume it.');
    }

    public function resume(Sequence $sequence): RedirectResponse
    {
        try {
            $this->sequences->resume($sequence);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', 'Sequence resumed from where each lead left off.');
    }

    public function archive(Sequence $sequence): RedirectResponse
    {
        $this->sequences->archive($sequence);

        return redirect()->route('outreach.sequences.index')->with('success', 'Sequence archived.');
    }

    public function duplicate(Sequence $sequence): RedirectResponse
    {
        $copy = $this->sequences->duplicate($sequence, $this->user());

        return redirect()->route('outreach.sequences.show', $copy)->with('success', 'Sequence duplicated as a draft.');
    }

    public function analytics(Sequence $sequence): View
    {
        $sequence->load('steps');

        return view('sequencer.sequences.analytics', [
            'sequence' => $sequence,
            'analytics' => $this->analytics->sequence($sequence),
        ]);
    }

    private function formData(Sequence $sequence): array
    {
        return [
            'sequence' => $sequence,
            'timezones' => Tz::all(),
            // Sending accounts come from Settings > Mail Settings.
            'accounts' => MailSetting::active()->orderByDesc('is_default')->orderBy('name')->get(),
        ];
    }
}
