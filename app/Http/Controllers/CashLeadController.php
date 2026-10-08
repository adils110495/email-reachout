<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Concerns\RedirectsBack;
use App\Models\CashLead;
use App\Models\Category;
use App\Models\GmbLead;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashLeadController extends Controller
{
    use ExportsCsv;
    use RedirectsBack;

    public function index(Request $request): View
    {
        $categories     = Category::active()->orderBy('name')->get();
        $activeCategory = $request->filled('category') ? (int) $request->input('category') : null;
        $activeSource   = array_key_exists((string) $request->input('source'), CashLead::SOURCES) ? (string) $request->input('source') : null;
        $search         = trim((string) $request->input('q'));

        $cashLeads = CashLead::with('category')
            ->when($activeCategory, fn (Builder $q) => $q->where('category_id', $activeCategory))
            ->when($activeSource, fn (Builder $q) => $q->where('source', $activeSource))
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($search) {
                $w->where('company_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('website', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $sources = CashLead::SOURCES;
        $data    = compact('cashLeads', 'categories', 'activeCategory', 'activeSource', 'search', 'sources');

        // A filter / search / page change fetches just the table partial.
        if ($request->ajax()) {
            return view('cash-leads._table', $data);
        }

        return view('cash-leads.index', $data);
    }

    /**
     * Add a cash lead by hand.
     */
    public function store(Request $request): RedirectResponse
    {
        CashLead::create($this->validated($request) + ['source' => CashLead::SOURCE_MANUAL]);

        return redirect()->route('cash-leads.index', $this->redirectQuery($request))->with('success', 'Deal added.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        CashLead::findOrFail($id)->update($this->validated($request));

        return redirect()->route('cash-leads.index', $this->redirectQuery($request))->with('success', 'Deal updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        CashLead::findOrFail($id)->delete();

        return redirect()->route('cash-leads.index', $this->redirectQuery($request))->with('success', 'Deal removed.');
    }

    /**
     * "Mark as Cash Lead" from the Leads list.
     */
    public function fromLead(int $id): RedirectResponse
    {
        $lead = Lead::findOrFail($id);

        if (CashLead::where('lead_id', $lead->id)->exists()) {
            return back()->with('error', "\"{$lead->company_name}\" is already a deal.");
        }

        CashLead::create([
            'source'       => CashLead::SOURCE_LEAD,
            'lead_id'      => $lead->id,
            'category_id'  => $lead->category_id,
            'company_name' => $lead->company_name,
            'email'        => implode(', ', $lead->email_list) ?: null,
            'website'      => $lead->website,
        ]);

        return back()->with('success', "\"{$lead->company_name}\" added to Deals.");
    }

    /**
     * "Mark as Cash Lead" from the GMB Leads list.
     */
    public function fromGmb(int $id): RedirectResponse
    {
        $gmb = GmbLead::findOrFail($id);

        if (CashLead::where('gmb_lead_id', $gmb->id)->exists()) {
            return back()->with('error', "\"{$gmb->name}\" is already a deal.");
        }

        CashLead::create([
            'source'       => CashLead::SOURCE_GMB,
            'gmb_lead_id'  => $gmb->id,
            'category_id'  => $gmb->category_id,
            'company_name' => $gmb->name,
            'phone'        => $gmb->phone,
            'address'      => $gmb->address,
        ]);

        return back()->with('success', "\"{$gmb->name}\" added to Deals.");
    }

    public function export(): StreamedResponse
    {
        return $this->streamCsv(
            'cash_leads',
            ['ID', 'Company', 'Category', 'Email', 'Phone', 'Website', 'Address', 'Source', 'Notes', 'Created At'],
            CashLead::with('category')->latest()->get(),
            fn (CashLead $c) => [
                $c->id, $c->company_name, $c->category?->name, $c->email, $c->phone, $c->website,
                $c->address, CashLead::SOURCES[$c->source] ?? $c->source, $c->notes, $c->created_at->toDateTimeString(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'category_id'  => ['nullable', 'exists:categories,id'],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'website'      => ['nullable', 'string', 'max:255'],
            'address'      => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
