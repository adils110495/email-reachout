<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Concerns\QueuesBulkSend;
use App\Http\Controllers\Concerns\RedirectsBack;
use App\Models\Category;
use App\Models\GmbLead;
use App\Jobs\GmbSearchJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GmbLeadController extends Controller
{
    use ExportsCsv;
    use QueuesBulkSend;
    use RedirectsBack;

    /** Rating is a dropdown of "minimum stars". */
    private const RATING_OPTIONS = ['3' => '3.0+', '3.5' => '3.5+', '4' => '4.0+', '4.5' => '4.5+'];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $gmbLeads = $this->filtered($filters)
            ->with('category')
            ->withCount('whatsappMessages')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $data = $filters + [
            'categories'    => Category::where('status', 'active')->orderBy('name')->get(),
            'ratingOptions' => self::RATING_OPTIONS,
            'gmbLeads'      => $gmbLeads,
        ];

        // A filter / search / page change fetches just the table partial.
        if ($request->ajax()) {
            return view('gmb-leads._table', $data);
        }

        return view('gmb-leads.index', $data);
    }

    /**
     * Search Google Maps and store the businesses that have no website.
     */
    public function search(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $request->validate([
            'keyword'         => ['required', 'string', 'min:2', 'max:200'],
            'search_category' => ['required', $this->access()->existsRule('categories')],
        ]);

        $keyword    = trim($request->input('keyword'));
        $categoryId = (int) $request->input('search_category');

        // The SerpAPI calls and inserts run on the queue; GmbSearchJob raises a
        // notification when it finishes.
        GmbSearchJob::dispatch($keyword, $categoryId, $request->user()->id)->onQueue('default');

        // The page submits this by fetch() and stays put; the notification
        // bell reports the result.
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('gmb-leads.index', ['category' => $categoryId])
            ->with('success', "Searching \"{$keyword}\" in the background. You will be notified when it is done.");
    }

    /**
     * Add a GMB lead by hand - a business found some other way than the search.
     */
    public function store(Request $request): RedirectResponse
    {
        GmbLead::create($this->validated($request) + [
            // Google's place id keeps search results from being saved twice; a
            // hand-made lead has none, so it gets its own that no search can match.
            'place_id' => GmbLead::MANUAL_PREFIX . Str::uuid(),
        ]);

        return redirect()->route('gmb-leads.index', $this->redirectQuery($request))->with('success', 'GMB lead added.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $lead = GmbLead::findOrFail($id);
        $lead->update($this->validated($request));

        return redirect()->route('gmb-leads.index', $this->redirectQuery($request))->with('success', "\"{$lead->name}\" updated.");
    }

    /**
     * Bulk WhatsApp: an approved template to the selected leads, or to every
     * lead matching the list's current filters. GMB leads have no email, so
     * WhatsApp is the only channel.
     */
    public function whatsapp(Request $request): JsonResponse
    {
        $query = $request->boolean('all') ? $this->filtered($this->filters($request)) : GmbLead::query();

        return $this->queueBulkSend($request, $query, fn (GmbLead $lead) => [
            'gmb_lead_id' => $lead->id,
            'name'        => $lead->name,
            'phone'       => $lead->phone,
        ], ['whatsapp']);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        GmbLead::findOrFail($id)->delete();

        return redirect()->route('gmb-leads.index', $this->redirectQuery($request))->with('success', 'GMB lead deleted.');
    }

    public function export(): StreamedResponse
    {
        return $this->streamCsv(
            'gmb_leads',
            ['ID', 'Name', 'Type', 'Category', 'Phone', 'Address', 'Rating', 'Reviews', 'Keyword', 'Maps URL', 'Created At'],
            GmbLead::with('category')->latest()->get(),
            fn (GmbLead $l) => [
                $l->id, $l->name, $l->type, $l->category?->name, $l->phone, $l->address,
                $l->rating, $l->reviews, $l->keyword, $l->maps_url, $l->created_at->toDateTimeString(),
            ],
        );
    }

    /**
     * The Add / Edit form. The form's keyword field is "lead_keyword" so a
     * failed save cannot refill the search box above (also "keyword").
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'type'         => ['nullable', 'string', 'max:255'],
            'category_id'  => ['nullable', $this->access()->existsRule('categories')],
            'phone'        => ['nullable', 'string', 'max:50'],
            'address'      => ['nullable', 'string', 'max:255'],
            'rating'       => ['nullable', 'numeric', 'between:0,5'],
            'reviews'      => ['nullable', 'integer', 'min:0', 'max:100000000'],
            // Shown as the "View on Maps" link: http(s) only, never javascript:
            'maps_url'     => ['nullable', 'url:http,https', 'max:2000'],
            'lead_keyword' => ['nullable', 'string', 'max:255'],
        ], [
            'maps_url.url' => 'Enter the full Google Maps link, starting with https://',
        ]);

        // The form is the whole record: a field left empty clears it.
        foreach (['type', 'category_id', 'phone', 'address', 'rating', 'reviews', 'maps_url'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? $data[$field] : null;
        }

        $data['keyword'] = trim((string) ($data['lead_keyword'] ?? ''));
        unset($data['lead_keyword']);

        return $data;
    }

    /**
     * The list's filters from the query string, normalised. The keys are the
     * view's variable names. Shared by the list and Bulk WhatsApp, so "all
     * matching" is exactly the rows on screen.
     *
     * @return array{activeCategory: ?int, search: string, activeRating: ?string, reviewsMin: mixed, reviewsMax: mixed}
     */
    private function filters(Request $request): array
    {
        // Min / max range filters; either end may be left empty. Non-numeric input is ignored.
        $range = fn (string $key) => is_numeric($request->input($key)) ? $request->input($key) : null;

        return [
            'activeCategory' => $request->filled('category') ? (int) $request->input('category') : null,
            'search'         => trim((string) $request->input('q')),
            'activeRating'   => array_key_exists((string) $request->input('rating'), self::RATING_OPTIONS) ? (string) $request->input('rating') : null,
            'reviewsMin'     => $range('reviews_min'),
            'reviewsMax'     => $range('reviews_max'),
        ];
    }

    private function filtered(array $f): Builder
    {
        return GmbLead::query()
            ->when($f['activeCategory'], fn (Builder $q) => $q->where('category_id', $f['activeCategory']))
            ->when($f['activeRating'], fn (Builder $q) => $q->where('rating', '>=', (float) $f['activeRating']))
            ->when($f['reviewsMin'] !== null, fn (Builder $q) => $q->where('reviews', '>=', (int) $f['reviewsMin']))
            ->when($f['reviewsMax'] !== null, fn (Builder $q) => $q->where('reviews', '<=', (int) $f['reviewsMax']))
            ->when($f['search'] !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($f) {
                $w->where('name', 'like', "%{$f['search']}%")
                  ->orWhere('phone', 'like', "%{$f['search']}%")
                  ->orWhere('address', 'like', "%{$f['search']}%")
                  ->orWhere('keyword', 'like', "%{$f['search']}%");
            }));
    }
}
