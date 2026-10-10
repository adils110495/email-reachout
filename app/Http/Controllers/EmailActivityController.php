<?php

namespace App\Http\Controllers;

use App\Models\LeadEmail;
use App\Models\Sequence;
use App\Services\ReplyCheckerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailActivityController extends Controller
{
    public function index(Request $request): View
    {
        $activityOptions = [
            LeadEmail::ACTIVITY_NOT_OPENED => 'Not opened',
            LeadEmail::ACTIVITY_OPENED     => 'Opened',
            LeadEmail::ACTIVITY_REPLIED    => 'Replied',
        ];

        $activity = array_key_exists((string) $request->input('activity'), $activityOptions) ? $request->input('activity') : null;
        $search   = trim((string) $request->input('q'));

        // Sequence emails live in the same table; an optional filter narrows to one sequence.
        $sequenceId = $request->integer('sequence') ?: null;

        // Only emails we actually sent.
        $base = LeadEmail::with(['lead', 'sequence:id,name', 'step:id,step_number'])->where('status', 'sent')
            ->when($sequenceId, fn (Builder $q) => $q->where('sequence_id', $sequenceId));

        $query = (clone $base)
            ->when($activity, fn (Builder $q) => $this->filterActivity($q, $activity))
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($search) {
                $w->where('subject', 'like', "%{$search}%")
                  ->orWhereHas('lead', fn (Builder $l) => $l->where('company_name', 'like', "%{$search}%")
                                                           ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->latest('sent_at');

        $counts = [
            'total'                        => (clone $base)->count(),
            LeadEmail::ACTIVITY_NOT_OPENED => $this->filterActivity(clone $base, LeadEmail::ACTIVITY_NOT_OPENED)->count(),
            LeadEmail::ACTIVITY_OPENED     => $this->filterActivity(clone $base, LeadEmail::ACTIVITY_OPENED)->count(),
            LeadEmail::ACTIVITY_REPLIED    => $this->filterActivity(clone $base, LeadEmail::ACTIVITY_REPLIED)->count(),
        ];

        $data = [
            'emails'          => $query->paginate(20)->withQueryString(),
            'activityOptions' => $activityOptions,
            'activity'        => $activity,
            'search'          => $search,
            'counts'          => $counts,
            'sequences'       => Sequence::orderBy('name')->get(['id', 'name']),
            'sequenceId'      => $sequenceId,
        ];

        // A filter / search / page change fetches just the table partial so the
        // page swaps it in without a full reload.
        if ($request->ajax()) {
            return view('email-activity._table', $data);
        }

        return view('email-activity.index', $data);
    }

    /**
     * Scan the inbox now instead of waiting for the scheduler.
     *
     * The page calls this over AJAX and gets JSON back (message + fresh filter
     * counts), then reloads just the table; a plain form post still redirects.
     */
    public function checkReplies(Request $request, ReplyCheckerService $checker): RedirectResponse|JsonResponse
    {
        try {
            $count = $checker->check();
        } catch (\RuntimeException $e) {
            $message = 'Could not check replies: ' . $e->getMessage();

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        }

        $message = $count > 0 ? "{$count} new repl" . ($count === 1 ? 'y' : 'ies') . ' found.' : 'No new replies.';

        if ($request->expectsJson()) {
            $base = LeadEmail::where('status', 'sent')
                ->when($request->integer('sequence') ?: null, fn (Builder $q, $id) => $q->where('sequence_id', $id));

            return response()->json([
                'ok'      => true,
                'replies' => $count,
                'message' => $message,
                'counts'  => [
                    'total'                        => (clone $base)->count(),
                    LeadEmail::ACTIVITY_NOT_OPENED => $this->filterActivity(clone $base, LeadEmail::ACTIVITY_NOT_OPENED)->count(),
                    LeadEmail::ACTIVITY_OPENED     => $this->filterActivity(clone $base, LeadEmail::ACTIVITY_OPENED)->count(),
                    LeadEmail::ACTIVITY_REPLIED    => $this->filterActivity(clone $base, LeadEmail::ACTIVITY_REPLIED)->count(),
                ],
            ]);
        }

        return back()->with('success', $message);
    }

    private function filterActivity(Builder $query, string $activity): Builder
    {
        return match ($activity) {
            LeadEmail::ACTIVITY_REPLIED    => $query->whereNotNull('replied_at'),
            LeadEmail::ACTIVITY_OPENED     => $query->whereNull('replied_at')->where('open_count', '>', 0),
            LeadEmail::ACTIVITY_NOT_OPENED => $query->whereNull('replied_at')->where('open_count', 0),
        };
    }
}
