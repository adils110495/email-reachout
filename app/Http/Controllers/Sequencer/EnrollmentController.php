<?php

namespace App\Http\Controllers\Sequencer;

use App\Http\Requests\Sequencer\EnrollRequest;
use App\Models\Bulk;
use App\Models\Category;
use App\Models\Lead;
use App\Models\MailSetting;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Sequencer\Enums\EnrollmentStatus;
use App\Sequencer\Services\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EnrollmentController extends SequencerController
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    public function index(Request $request, Sequence $sequence): View
    {
        $enrollments = SequenceEnrollment::where('sequence_id', $sequence->id)
            ->when($request->query('status'), fn ($q, $s) => in_array($s, EnrollmentStatus::values(), true) ? $q->where('status', $s) : $q)
            ->when($request->query('q'), fn ($q, $term) => $q->whereHas('lead', fn ($l) => $l->search($term)))
            ->with(['lead', 'mailSetting:id,name,from_address,username'])
            ->latest('id')
            ->paginate($this->perPage())
            ->withQueryString();

        return view('sequencer.enrollments.index', [
            'sequence' => $sequence,
            'enrollments' => $enrollments,
            'statuses' => EnrollmentStatus::cases(),
            'filters' => $request->only(['q', 'status']),
            'accounts' => MailSetting::active()->orderByDesc('is_default')->orderBy('name')->get(),
            'categories' => Category::active()->withCount('leads')->orderBy('name')->get(),
        ]);
    }

    /**
     * Enroll chosen leads and/or a whole category. Always queued as a Bulk run:
     * a category can hold thousands of leads.
     */
    public function store(EnrollRequest $request, Sequence $sequence): RedirectResponse
    {
        $ids = Lead::query()
            ->where(function ($q) use ($request) {
                $q->whereIn('id', $request->input('lead_ids') ?? []);
                if ($request->filled('category_id')) {
                    $q->orWhere(fn ($c) => $c->inCategory((int) $request->input('category_id')));
                }
            })
            ->pluck('id')->all();

        if ($ids === []) {
            return back()->with('error', 'No leads found to enroll.');
        }

        $bulk = Bulk::queueSequenceAction(Bulk::TYPE_ENROLL, $ids, 'Enroll in '.$sequence->name, [
            'sequence_id' => $sequence->id,
            'mail_setting_id' => $request->filled('mail_setting_id') ? (int) $request->input('mail_setting_id') : null,
        ]);

        return redirect()->route('bulks.show', $bulk->id)
            ->with('success', "Enrolling {$bulk->total_records} lead(s) in the background. Unsubscribed, bounced and already-enrolled leads are skipped.");
    }

    public function pause(SequenceEnrollment $enrollment): RedirectResponse
    {
        return $this->enrollments->pause($enrollment)
            ? back()->with('success', 'Enrollment paused.')
            : back()->with('error', 'This enrollment cannot be paused.');
    }

    public function resume(SequenceEnrollment $enrollment): RedirectResponse
    {
        return $this->enrollments->resume($enrollment)
            ? back()->with('success', 'Enrollment resumed from its current step.')
            : back()->with('error', 'Only paused enrollments can be resumed.');
    }

    public function remove(SequenceEnrollment $enrollment): RedirectResponse
    {
        return $this->enrollments->remove($enrollment)
            ? back()->with('success', 'Lead removed from the sequence.')
            : back()->with('error', 'This enrollment is already finished.');
    }

    public function retry(SequenceEnrollment $enrollment): RedirectResponse
    {
        return $this->enrollments->retry($enrollment)
            ? back()->with('success', 'Enrollment re-queued from its current step.')
            : back()->with('error', 'Only failed enrollments with steps remaining can be retried.');
    }

    /** Bulk pause / resume / remove on ticked rows, or on every enrollment of the sequence. */
    public function bulk(Request $request, Sequence $sequence): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in([Bulk::TYPE_PAUSE, Bulk::TYPE_RESUME, Bulk::TYPE_REMOVE])],
            'ids' => ['nullable', 'array', 'max:100000'],
            'ids.*' => ['integer'],
            'all' => ['nullable', 'boolean'],
        ]);

        if (! $request->boolean('all') && empty($data['ids'])) {
            return back()->with('error', 'Tick at least one row, or choose "all".');
        }

        // Only this sequence's enrollments, whatever ids were posted.
        $ids = SequenceEnrollment::where('sequence_id', $sequence->id)
            ->when(! $request->boolean('all'), fn ($q) => $q->whereIn('id', $data['ids']))
            ->pluck('id')->all();

        $bulk = Bulk::queueSequenceAction($data['action'], $ids, ucfirst($data['action']).' enrollments: '.$sequence->name, ['sequence_id' => $sequence->id]);

        return redirect()->route('bulks.show', $bulk->id)->with('success', "{$bulk->type_label} queued for {$bulk->total_records} enrollment(s).");
    }
}
