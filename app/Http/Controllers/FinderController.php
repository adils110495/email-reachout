<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Concerns\FiltersRequests;
use App\Http\Controllers\Concerns\SavesFinderResults;
use App\Jobs\FinderSearchJob;
use App\Models\Category;
use App\Models\EmailVerification;
use App\Models\FinderResult;
use App\Services\EmailFinderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Finder: search the lead database, and discover new addresses on demand.
 *
 * Two halves that feed each other:
 *
 *   index()   the results table - every lead already in the database, filtered
 *             server-side and paginated. Swapped in over AJAX by the shared
 *             assets/js/ajax-filters.js contract.
 *
 *   search()  the live lookup - scrapes a company website (domain search) or
 *             scores the likely name patterns (person search) via
 *             EmailFinderService, and returns JSON for the results panel.
 *             Nothing is stored until the user saves a result as a lead.
 */
class FinderController extends Controller
{
    use ExportsCsv, FiltersRequests, SavesFinderResults;

    public function __construct(private readonly EmailFinderService $finder) {}

    /**
     * Results table over the existing lead database.
     *
     * Filters: free-text q (company / website / email), category, platform,
     * has_email, and status - all optional, all shareable through the URL.
     */
    public function index(Request $request): View
    {
        $results = $this->filtered($request)
            ->paginate($this->perPage($request))
            ->withQueryString();

        $data = [
            'results'    => $results,
            'categories' => Category::active()->orderBy('name')->get(),
            'totals'     => $this->totals(),
            'filters'    => [
                'q'      => $this->strParam($request, 'q'),
                'status' => $this->enumParam($request, 'status', array_keys(EmailVerification::STATUSES)),
                'mode'   => $this->enumParam($request, 'mode', [FinderResult::MODE_DOMAIN, FinderResult::MODE_PERSON]),
                'saved'  => $this->enumParam($request, 'saved', ['yes', 'no']),
            ],
        ];

        // Filter / pagination changes fetch only the table partial.
        if ($request->ajax()) {
            return view('finder._results', $data);
        }

        return view('finder.index', $data);
    }

    /** @return array<string,int> headline figures for the stat cards */
    private function totals(): array
    {
        $byStatus = FinderResult::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'found'  => (int) $byStatus->sum(),
            'valid'  => (int) ($byStatus[EmailVerification::STATUS_VALID] ?? 0),
            'risky'  => (int) ($byStatus[EmailVerification::STATUS_RISKY] ?? 0),
            'saved'  => (int) FinderResult::whereNotNull('lead_id')->count(),
        ];
    }

    /**
     * Queue a live lookup. A domain search can take up to a minute (it reads the
     * site and follows its links), so FinderSearchJob does the work and raises
     * a notification; the results land in the Finder Results table.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode'   => ['required', 'in:domain,person'],
            'domain' => ['required', 'string', 'max:255'],
            'name'   => ['required_if:mode,person', 'nullable', 'string', 'max:120'],
            // The automatic save writes this to leads.category_id, which carries
            // a foreign key - an id that does not exist would be a 500, not a
            // validation message.
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $domain = $this->finder->normaliseDomain($validated['domain']);

        if ($domain === '') {
            return response()->json([
                'ok'      => false,
                'message' => 'That does not look like a valid domain. Try "example.com".',
            ], 422);
        }

        FinderSearchJob::dispatch(
            mode:       $validated['mode'],
            domain:     $domain,
            name:       (string) ($validated['name'] ?? ''),
            categoryId: isset($validated['category_id']) ? (int) $validated['category_id'] : null,
        )->onQueue('default');

        return response()->json(['ok' => true, 'queued' => true]);
    }

    /**
     * Save one discovered address into the lead database.
     *
     * Existing leads for the same website are updated rather than duplicated,
     * which keeps the Finder from re-adding rows the Leads module already has.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'        => ['required', 'email', 'max:255'],
            'domain'       => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'category_id'  => ['nullable', 'exists:categories,id'],
        ]);

        $domain = $this->finder->normaliseDomain($validated['domain']);

        if ($domain === '') {
            return response()->json(['ok' => false, 'message' => 'Invalid domain.'], 422);
        }

        $saved = $this->saveLead(
            $domain,
            $validated['email'],
            $validated['company_name'] ?? null,
            $validated['category_id'] ?? null,
        );

        // Mark it saved in the Finder's own table too, so the row stops
        // offering a Save button it has already been through.
        FinderResult::where('domain', $domain)
            ->where('email', strtolower($validated['email']))
            ->update(['lead_id' => $saved['lead_id']]);

        return response()->json($saved);
    }

    /** Export the current filtered result set. */
    public function export(Request $request): StreamedResponse
    {
        return $this->streamCsv(
            'finder-results',
            ['ID', 'Email', 'Domain', 'Company', 'Person', 'Mode', 'Result', 'Score', 'Guessed', 'Pattern', 'Saved As Lead', 'Found At'],
            $this->filtered($request)->cursor(),
            fn (FinderResult $row) => [
                $row->id,
                $row->email,
                $row->domain,
                $row->company,
                $row->person,
                $row->mode,
                $row->status,
                $row->score,
                $row->guessed ? 'yes' : 'no',
                $row->pattern,
                $row->lead?->company_name,
                $row->created_at?->toDateTimeString(),
            ],
        );
    }

    /**
     * The lead query behind both the results table and the CSV export, so the
     * export always contains exactly the rows the user is looking at.
     *
     * Every filter is optional and read defensively - these arrive from a URL
     * the user can hand-edit.
     */
    private function filtered(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = FinderResult::with('lead')->orderByDesc('id');

        // Free-text search across the columns the table actually shows.
        if ($term = $this->strParam($request, 'q')) {
            $escaped = $this->likePattern($term);

            $query->where(function ($q) use ($escaped) {
                $q->where('email', 'like', $escaped)
                  ->orWhere('domain', 'like', $escaped)
                  ->orWhere('company', 'like', $escaped)
                  ->orWhere('person', 'like', $escaped);
            });
        }

        if ($status = $this->enumParam($request, 'status', array_keys(EmailVerification::STATUSES))) {
            $query->where('status', $status);
        }

        if ($mode = $this->enumParam($request, 'mode', [FinderResult::MODE_DOMAIN, FinderResult::MODE_PERSON])) {
            $query->where('mode', $mode);
        }

        $saved = $this->enumParam($request, 'saved', ['yes', 'no']);

        if ($saved === 'yes') {
            $query->whereNotNull('lead_id');
        } elseif ($saved === 'no') {
            $query->whereNull('lead_id');
        }

        return $query;
    }

}
