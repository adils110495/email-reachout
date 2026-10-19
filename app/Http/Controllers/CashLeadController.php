<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Concerns\QueuesBulkSend;
use App\Http\Controllers\Concerns\RedirectsBack;
use App\Models\CashLead;
use App\Models\Category;
use App\Models\GmbLead;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashLeadController extends Controller
{
    use ExportsCsv;
    use QueuesBulkSend;
    use RedirectsBack;

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $cashLeads = $this->filtered($filters)
            ->with('category')
            ->withCount(['whatsappMessages', 'leadEmails'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $data = $filters + [
            'cashLeads'  => $cashLeads,
            'categories' => Category::active()->orderBy('name')->get(),
            'sources'    => CashLead::SOURCES,
        ];

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

        // The deal belongs to the lead's owner, also when a super admin marks it.
        $this->access()->runAs($lead->user_id ?? auth()->id(), fn () => CashLead::create([
            'source'       => CashLead::SOURCE_LEAD,
            'lead_id'      => $lead->id,
            'category_id'  => $lead->category_id,
            'company_name' => $lead->company_name,
            'email'        => implode(', ', $lead->email_list) ?: null,
            'website'      => $lead->website,
        ]));

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

        $this->access()->runAs($gmb->user_id ?? auth()->id(), fn () => CashLead::create([
            'source'       => CashLead::SOURCE_GMB,
            'gmb_lead_id'  => $gmb->id,
            'category_id'  => $gmb->category_id,
            'company_name' => $gmb->name,
            'phone'        => $gmb->phone,
            'address'      => $gmb->address,
        ]));

        return back()->with('success', "\"{$gmb->name}\" added to Deals.");
    }

    /**
     * Bulk send: an approved WhatsApp template, an email, or both, to the
     * selected deals or to every deal matching the list's current filters.
     */
    public function bulkSend(Request $request): JsonResponse
    {
        $query = $request->boolean('all') ? $this->filtered($this->filters($request)) : CashLead::query();

        return $this->queueBulkSend($request, $query, fn (CashLead $deal) => [
            'cash_lead_id' => $deal->id,
            'name'         => $deal->company_name,
            'phone'        => $deal->phone,
            'email'        => $deal->email,
        ], ['whatsapp', 'email']);
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
            'category_id'  => ['nullable', $this->access()->existsRule('categories')],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'website'      => ['nullable', 'string', 'max:255'],
            'address'      => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /**
     * The list's filters from the query string, normalised. The keys are the
     * view's variable names. Shared by the list and Bulk send, so "all
     * matching" is exactly the rows on screen.
     *
     * @return array{activeCategory: ?int, activeSource: ?string, search: string}
     */
    private function filters(Request $request): array
    {
        return [
            'activeCategory' => $request->filled('category') ? (int) $request->input('category') : null,
            'activeSource'   => array_key_exists((string) $request->input('source'), CashLead::SOURCES) ? (string) $request->input('source') : null,
            'search'         => trim((string) $request->input('q')),
        ];
    }

    private function filtered(array $f): Builder
    {
        return CashLead::query()
            ->when($f['activeCategory'], fn (Builder $q) => $q->where('category_id', $f['activeCategory']))
            ->when($f['activeSource'], fn (Builder $q) => $q->where('source', $f['activeSource']))
            ->when($f['search'] !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($f) {
                $w->where('company_name', 'like', "%{$f['search']}%")
                  ->orWhere('email', 'like', "%{$f['search']}%")
                  ->orWhere('phone', 'like', "%{$f['search']}%")
                  ->orWhere('website', 'like', "%{$f['search']}%")
                  ->orWhere('notes', 'like', "%{$f['search']}%");
            }));
    }
}
