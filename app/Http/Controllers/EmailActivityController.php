<?php

namespace App\Http\Controllers;

use App\Models\LeadEmail;
use App\Models\User;
use App\Services\EmailBulkService;
use App\Services\ReplyCheckerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Activity log of every email sent. The owner scope limits it to the user's own
 * emails; a super admin sees everyone's and can narrow it down by user.
 *
 * ?batch=<uuid> shows one bulk send (Deals > Bulk Send) with its progress.
 */
class EmailActivityController extends Controller
{
    public function index(Request $request, EmailBulkService $bulk): View
    {
        $activityOptions = [
            LeadEmail::ACTIVITY_NOT_OPENED => 'Not opened',
            LeadEmail::ACTIVITY_OPENED     => 'Opened',
            LeadEmail::ACTIVITY_REPLIED    => 'Replied',
        ];

        $activity = array_key_exists((string) $request->input('activity'), $activityOptions) ? $request->input('activity') : null;
        $search   = trim((string) $request->input('q'));
        $batch    = Str::isUuid((string) $request->input('batch')) ? (string) $request->input('batch') : '';

        // The user filter is a super admin tool. For anyone else it is ignored
        // (the owner scope already limits them to their own emails).
        $isSuperAdmin = $this->access()->isSuperAdmin();
        $users        = $isSuperAdmin ? User::orderBy('name')->get(['id', 'name', 'username']) : collect();
        $owner        = $isSuperAdmin && ctype_digit((string) $request->input('user')) ? (int) $request->input('user') : null;

        // Only emails we actually sent - except in a bulk send's view, which
        // lists all of its emails: still queued, failed and cancelled too.
        $base = LeadEmail::with('lead', 'cashLead', 'user')
            ->when(
                $batch !== '',
                fn (Builder $q) => $q->where('batch_id', $batch),
                fn (Builder $q) => $q->where('status', LeadEmail::STATUS_SENT),
            )
            ->when($owner, fn (Builder $q) => $q->where('user_id', $owner));

        $query = (clone $base)
            ->when($activity, fn (Builder $q) => $this->filterActivity($q, $activity))
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($search) {
                $w->where('subject', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('to_email', 'like', "%{$search}%")
                  ->orWhereHas('lead', fn (Builder $l) => $l->where('company_name', 'like', "%{$search}%")
                                                           ->orWhere('email', 'like', "%{$search}%"));
            }))
            // A bulk send has emails not sent yet (no sent_at), so it is ordered by id.
            ->when($batch !== '', fn (Builder $q) => $q->latest('id'), fn (Builder $q) => $q->latest('sent_at'));

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
            'isSuperAdmin'    => $isSuperAdmin,
            'users'           => $users,
            'owner'           => $owner,
            'batch'           => $batch,
            // A bulk send gets a progress panel above its emails.
            'batchProgress'   => $batch !== '' ? $bulk->progress($batch) : null,
            'batchFirst'      => $batch !== '' ? LeadEmail::where('batch_id', $batch)->oldest('id')->first() : null,
        ];

        // A filter / search / page change fetches just the table partial so the
        // page swaps it in without a full reload.
        if ($request->ajax()) {
            return view('email-activity._table', $data);
        }

        return view('email-activity.index', $data);
    }

    /**
     * Scan the inbox now instead of waiting for the scheduler. Covers the
     * user's own emails - or every user's, each against their own inbox, for a
     * super admin.
     */
    public function checkReplies(ReplyCheckerService $checker): RedirectResponse
    {
        try {
            $count = $checker->check();
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Could not check replies: ' . $e->getMessage());
        }

        return back()->with('success', $count > 0 ? "{$count} new repl" . ($count === 1 ? 'y' : 'ies') . ' found.' : 'No new replies.');
    }

    /**
     * Polled by the page while a bulk send is running (JSON).
     */
    public function batchStatus(string $batch, EmailBulkService $bulk): JsonResponse
    {
        $progress = $bulk->progress($batch);

        abort_if($progress['total'] === 0, 404);

        return response()->json($progress);
    }

    /**
     * Stop a bulk send: what is still queued is cancelled.
     */
    public function cancelBatch(string $batch, EmailBulkService $bulk): RedirectResponse
    {
        $cancelled = $bulk->cancel($batch);

        return redirect()->route('email-activity.index', ['batch' => $batch])->with(
            $cancelled ? 'success' : 'error',
            $cancelled ? "Bulk send stopped - {$cancelled} queued email" . ($cancelled === 1 ? '' : 's') . ' cancelled.' : 'Nothing left to cancel.',
        );
    }

    /** Opens and replies only exist for emails that went out. */
    private function filterActivity(Builder $query, string $activity): Builder
    {
        $query->where('status', LeadEmail::STATUS_SENT);

        return match ($activity) {
            LeadEmail::ACTIVITY_REPLIED    => $query->whereNotNull('replied_at'),
            LeadEmail::ACTIVITY_OPENED     => $query->whereNull('replied_at')->where('open_count', '>', 0),
            LeadEmail::ACTIVITY_NOT_OPENED => $query->whereNull('replied_at')->where('open_count', 0),
        };
    }
}
