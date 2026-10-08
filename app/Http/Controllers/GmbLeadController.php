<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Concerns\RedirectsBack;
use App\Models\Category;
use App\Models\GmbLead;
use App\Jobs\GmbSearchJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GmbLeadController extends Controller
{
    use ExportsCsv;
    use RedirectsBack;

    public function index(Request $request): View
    {
        $categories     = Category::where('status', 'active')->orderBy('name')->get();
        $activeCategory = $request->filled('category') ? (int) $request->input('category') : null;
        $search         = trim((string) $request->input('q'));

        // Min / max range filters; either end may be left empty. Non-numeric input is ignored.
        $range = fn (string $key) => is_numeric($request->input($key)) ? $request->input($key) : null;

        // Rating is a dropdown of "minimum stars".
        $ratingOptions = ['3' => '3.0+', '3.5' => '3.5+', '4' => '4.0+', '4.5' => '4.5+'];
        $activeRating  = array_key_exists((string) $request->input('rating'), $ratingOptions) ? (string) $request->input('rating') : null;

        $reviewsMin = $range('reviews_min');
        $reviewsMax = $range('reviews_max');

        $gmbLeads = GmbLead::with('category')
            ->when($activeCategory, fn (Builder $q) => $q->where('category_id', $activeCategory))
            ->when($activeRating, fn (Builder $q) => $q->where('rating', '>=', (float) $activeRating))
            ->when($reviewsMin !== null, fn (Builder $q) => $q->where('reviews', '>=', (int) $reviewsMin))
            ->when($reviewsMax !== null, fn (Builder $q) => $q->where('reviews', '<=', (int) $reviewsMax))
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('keyword', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $data = compact('categories', 'activeCategory', 'search', 'gmbLeads', 'ratingOptions', 'activeRating', 'reviewsMin', 'reviewsMax');

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
            'search_category' => ['required', 'exists:categories,id'],
        ]);

        $keyword    = trim($request->input('keyword'));
        $categoryId = (int) $request->input('search_category');

        // The SerpAPI calls and inserts run on the queue; GmbSearchJob raises a
        // notification when it finishes.
        GmbSearchJob::dispatch($keyword, $categoryId)->onQueue('default');

        // The page submits this by fetch() and stays put; the notification
        // bell reports the result.
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('gmb-leads.index', ['category' => $categoryId])
            ->with('success', "Searching \"{$keyword}\" in the background. You will be notified when it is done.");
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
}
