<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Concerns\FiltersRequests;
use App\Models\Category;
use App\Models\EmailVerification;
use App\Models\FinderResult;
use App\Models\Lead;
use App\Models\Platform;
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
    use ExportsCsv, FiltersRequests;

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
     * Run a live lookup. Returns JSON so the page can show loading / empty /
     * error states without a round trip.
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

        try {
            $result = $validated['mode'] === 'person'
                ? $this->finder->findByPerson((string) $validated['name'], $domain)
                : $this->finder->findByDomain($domain);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok'      => false,
                'message' => 'The lookup could not be completed. Please try again.',
            ], 500);
        }

        // A domain search reaches the network; say so when it came back empty,
        // because "site unreachable" and "site has no email" are different
        // problems for the user.
        $scraped = $result['scraped'] ?? true;

        // A domain search found a real, published address - file it against the
        // company's lead straight away rather than making the user press Save
        // for the result they just asked for.
        //
        // Deliberately only the best NON-guessed candidate:
        //   * guessed  - a person search produces patterns, not confirmed
        //                addresses. Storing one would put an unverified address
        //                into the outreach list, which is how bounces happen.
        //   * only one - every candidate here belongs to the same company, and
        //                a lead is keyed on its website. Saving all five would
        //                just overwrite the same row five times.
        $saved = null;
        $best  = $result['candidates'][0] ?? null;

        if ($best && ! $best['guessed']) {
            $saved = $this->saveLead(
                $result['domain'],
                $best['email'],
                $result['company'] ?? null,
                isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            );
        }

        // Record every candidate, not just the one that became a lead. A lead
        // holds one address per company, so without this the other four found on
        // the same site - and every person-search guess - would be thrown away
        // the moment the results panel is closed.
        $this->recordResults($result, $validated['mode'], $saved);

        return response()->json([
            'ok'         => true,
            'mode'       => $validated['mode'],
            'domain'     => $result['domain'],
            'company'    => $result['company'] ?? null,
            'person'     => $result['person'] ?? null,
            'scraped'    => $scraped,
            'saved'      => $saved,
            'candidates' => array_map(static fn (array $c) => [
                'email'   => $c['email'],
                'status'  => $c['status'],
                'score'   => $c['score'],
                'reason'  => $c['reason'],
                'source'  => $c['source'],
                'guessed' => $c['guessed'],
                'type'    => $c['type'],
                'pattern' => $c['pattern'],
                'role'    => (bool) ($c['checks']['role'] ?? false),
                'free'    => (bool) ($c['checks']['free'] ?? false),
            ], $result['candidates']),
        ]);
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

    /**
     * Write every candidate from one search into the Finder's own table.
     *
     * Keyed on (domain, email): searching the same company again refreshes each
     * address's score and verdict rather than stacking duplicate rows.
     *
     * @param  array<string,mixed>       $result  what EmailFinderService returned
     * @param  array<string,mixed>|null  $saved   the lead created, if any
     */
    private function recordResults(array $result, string $mode, ?array $saved): void
    {
        $savedEmail = $saved ? strtolower((string) $saved['email']) : null;

        foreach ($result['candidates'] as $candidate) {
            // lead_id is deliberately absent here. Writing it on every refresh
            // would clear the link on an address the user saved during an
            // earlier search - re-running a search must not un-save anything.
            $row = FinderResult::updateOrCreate(
                [
                    'domain' => $result['domain'],
                    'email'  => $candidate['email'],
                ],
                [
                    'mode'    => $mode,
                    'person'  => $result['person'] ?? null,
                    'company' => $result['company'] ?? null,
                    'status'  => $candidate['status'],
                    'score'   => $candidate['score'],
                    'reason'  => $candidate['reason'],
                    'source'  => $candidate['source'],
                    'guessed' => $candidate['guessed'],
                    'type'    => $candidate['type'],
                    'pattern' => $candidate['pattern'],
                ],
            );

            if ($saved && $savedEmail === strtolower($candidate['email'])) {
                $row->update(['lead_id' => $saved['lead_id']]);
            }
        }
    }

    /**
     * Record one discovered address against its company's lead.
     *
     * Shared by the explicit Save button and the automatic save that follows a
     * domain search, so both behave identically: one lead per website, and an
     * address the user already has is never overwritten.
     *
     * @return array{ok:bool, created:bool, lead_id:int, email:string, message:string}
     */
    private function saveLead(string $domain, string $email, ?string $companyName, ?int $categoryId): array
    {
        $website = 'https://'.$domain;

        $lead = Lead::where('website', $website)
            ->orWhere('website', $website.'/')
            ->first();

        if ($lead) {
            // Never clobber an address the user already has on the lead.
            if (empty($lead->email)) {
                $lead->update(['email' => $email]);
            }

            return [
                'ok'      => true,
                'created' => false,
                'lead_id' => $lead->id,
                'email'   => $lead->email,
                'message' => "Lead already existed - updated \"{$lead->company_name}\".",
            ];
        }

        $lead = Lead::create([
            'company_name' => $companyName ?: $domain,
            'website'      => $website,
            'email'        => $email,
            'status'       => Lead::STATUS_NEW,
            'platform_id'  => Platform::where('name', 'Google')->value('id'),
            'category_id'  => $categoryId ?: null,
        ]);

        return [
            'ok'      => true,
            'created' => true,
            'lead_id' => $lead->id,
            'email'   => $lead->email,
            'message' => "Saved \"{$lead->company_name}\" to your leads.",
        ];
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
