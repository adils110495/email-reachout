<?php

namespace App\Http\Controllers;

use App\Models\LeadEmail;
use App\Models\User;
use App\Services\ReplyCheckerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Activity log of every email sent. The owner scope limits it to the user's own
 * emails; a super admin sees everyone's and can narrow it down by user.
 */
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

        // The user filter is a super admin tool. For anyone else it is ignored
        // (the owner scope already limits them to their own emails).
        $isSuperAdmin = $this->access()->isSuperAdmin();
        $users        = $isSuperAdmin ? User::orderBy('name')->get(['id', 'name', 'username']) : collect();
        $owner        = $isSuperAdmin && ctype_digit((string) $request->input('user')) ? (int) $request->input('user') : null;

        // Only emails we actually sent.
        $base = LeadEmail::with('lead', 'user')
            ->where('status', 'sent')
            ->when($owner, fn (Builder $q) => $q->where('user_id', $owner));

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
            'isSuperAdmin'    => $isSuperAdmin,
            'users'           => $users,
            'owner'           => $owner,
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

    private function filterActivity(Builder $query, string $activity): Builder
    {
        return match ($activity) {
            LeadEmail::ACTIVITY_REPLIED    => $query->whereNotNull('replied_at'),
            LeadEmail::ACTIVITY_OPENED     => $query->whereNull('replied_at')->where('open_count', '>', 0),
            LeadEmail::ACTIVITY_NOT_OPENED => $query->whereNull('replied_at')->where('open_count', 0),
        };
    }
}
