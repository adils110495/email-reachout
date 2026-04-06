<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeLeadEmailJob;
use App\Mail\OutreachMail;
use App\Models\Lead;
use App\Models\Platform;
use App\Services\AIService;
use App\Services\EmailExtractorService;
use App\Services\EmailSenderService;
use App\Services\ImapService;
use App\Services\LeadFinderService;
use App\Services\ScraperService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    public function __construct(
        private readonly LeadFinderService $leadFinder,
        private readonly ScraperService $scraper,
        private readonly EmailExtractorService $emailExtractor,
        private readonly AIService $aiService,
        private readonly EmailSenderService $emailSender,
    ) {}

    /**
     * Show the main dashboard with all leads.
     */
    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->query('per_page'), [10, 20, 50, 100])
            ? (int) $request->query('per_page')
            : 10;

        $query = Lead::with('platform')->latest();

        $validStatuses = ['new', 'sent', 'failed', 'replied'];
        if ($request->filled('status') && in_array($request->status, $validStatuses)) {
            $query->where('status', $request->status);
        }

        $leads     = $query->paginate($perPage)->withQueryString();
        $platforms = Platform::active()->orderBy('name')->get();

        return view('leads.index', compact('leads', 'platforms'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'website'      => ['required', 'url', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'linkedin'     => ['nullable', 'url', 'max:255'],
            'platform_id'  => ['nullable', 'exists:platforms,id'],
        ]);

        Lead::create([
            'company_name' => $request->company_name,
            'website'      => $request->website,
            'email'        => $request->email,
            'linkedin'     => $request->linkedin,
            'status'       => 'new',
            'platform_id'  => $request->platform_id,
        ]);

        return redirect()->route('leads.index')->with('success', 'Lead added successfully.');
    }

    /**
     * Search for leads using a keyword via SerpAPI.
     * Leads are saved instantly; email scraping runs in the background queue.
     */
    public function search(Request $request): RedirectResponse
    {
        $request->validate([
            'keyword' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        $keyword = $request->input('keyword');

        // 1. Fetch results from SerpAPI (fast — single HTTP call)
        $results = $this->leadFinder->find($keyword);

        $newLeadsCount = 0;
        $googlePlatform = Platform::where('name', 'Google')->first();

        foreach ($results as $result) {
            // Skip duplicates
            if (Lead::where('website', $result['url'])->exists()) {
                continue;
            }

            // 2. Save lead immediately (no scraping yet)
            $lead = Lead::create([
                'company_name' => $result['title'],
                'website'      => $result['url'],
                'email'        => null,
                'status'       => Lead::STATUS_NEW,
                'platform_id'  => $googlePlatform?->id,
            ]);

            // 3. Dispatch background job to scrape email (non-blocking)
            ScrapeLeadEmailJob::dispatch($lead)->onQueue('default');

            $newLeadsCount++;
        }

        return redirect()->route('leads.index')
            ->with('success', "Found {$newLeadsCount} new leads for \"{$keyword}\". Emails are being extracted in the background.");
    }

    /**
     * Return a single lead as JSON (used by the View modal).
     */
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        return response()->json(Lead::with('platform')->findOrFail($id));
    }

    /**
     * Show the edit form for a lead (rendered inside a modal via AJAX).
     */
    public function edit(int $id): \Illuminate\Http\JsonResponse
    {
        return response()->json(Lead::with('platform')->findOrFail($id));
    }

    /**
     * Update a lead's details.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $lead = Lead::findOrFail($id);

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'website'      => ['required', 'url', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'linkedin'     => ['nullable', 'url', 'max:255'],
            'status'       => ['required', 'in:new,sent,failed,replied'],
            'platform_id'  => ['nullable', 'exists:platforms,id'],
        ]);

        $lead->update($data);

        return redirect()->route('leads.index')->with('success', "Lead \"{$lead->company_name}\" updated.");
    }

    /**
     * Manually mark a lead's status as "sent".
     */
    public function markSent(int $id): RedirectResponse
    {
        $lead = Lead::findOrFail($id);
        $lead->update(['status' => Lead::STATUS_SENT]);

        return redirect()->route('leads.index')
            ->with('success', "\"{$lead->company_name}\" marked as sent.");
    }

    /**
     * Delete a single lead.
     */
    public function destroy(int $id): RedirectResponse
    {
        $lead = Lead::findOrFail($id);
        $lead->delete();

        return redirect()->route('leads.index')->with('success', "Lead deleted.");
    }

    /**
     * Bulk-delete selected leads.
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);

        $count = Lead::whereIn('id', $request->ids)->delete();

        return redirect()->route('leads.index')->with('success', "{$count} lead(s) deleted.");
    }

    /**
     * Bulk-update status for selected leads.
     */
    public function bulkStatus(Request $request): RedirectResponse
    {
        $request->validate([
            'ids'    => ['required', 'array'],
            'ids.*'  => ['integer'],
            'status' => ['required', 'in:new,sent,failed,replied'],
        ]);

        $count = Lead::whereIn('id', $request->ids)->update(['status' => $request->status]);

        return redirect()->route('leads.index')->with('success', "{$count} lead(s) updated to \"{$request->status}\".");
    }

    /**
     * Return AI-generated subject + body for the compose modal (JSON).
     */
    public function compose(int $id): \Illuminate\Http\JsonResponse
    {
        $lead          = Lead::findOrFail($id);
        $senderName    = env('SENDER_NAME', 'Sales Team');
        $senderCompany = env('SENDER_COMPANY', 'Our Company');

        $subject = $this->aiService->generateSubjectLine($lead, $senderCompany);
        $body    = $this->aiService->generateOutreachEmail($lead, $senderName, $senderCompany);

        return response()->json([
            'lead'    => $lead,
            'subject' => $subject,
            'body'    => $body,
            'to'      => $lead->email,
        ]);
    }

    /**
     * Send outreach email directly (no queue) using subject/body from the compose modal.
     */
    public function sendEmail(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string'],
        ]);

        $lead = Lead::findOrFail($id);

        if (! $lead->hasEmail()) {
            return redirect()->route('leads.index')
                ->with('error', "Lead \"{$lead->company_name}\" has no email address.");
        }

        if ($lead->status === Lead::STATUS_SENT) {
            return redirect()->route('leads.index')
                ->with('error', "Email already sent to \"{$lead->company_name}\".");
        }

        $senderName    = env('SENDER_NAME', 'Sales Team');
        $senderCompany = env('SENDER_COMPANY', 'Our Company');
        $subject       = $request->input('subject');
        $body          = $request->input('body');

        try {
            $mailable = new OutreachMail($lead, $body, $subject, $senderName, $senderCompany);

            // Send directly — no queue
            Mail::to($lead->email)->send($mailable);

            // Copy to IMAP Sent folder
            app(ImapService::class)->copyToSentFolder(
                to:        $lead->email,
                subject:   $subject,
                htmlBody:  $mailable->render(),
                fromName:  env('MAIL_FROM_NAME', $senderName),
                fromEmail: env('MAIL_FROM_ADDRESS'),
            );

            $lead->update(['status' => Lead::STATUS_SENT]);

            Log::info('sendEmail: sent directly', ['lead_id' => $lead->id, 'to' => $lead->email]);

            return redirect()->route('leads.index')
                ->with('success', "Email sent successfully to \"{$lead->company_name}\".");

        } catch (\Throwable $e) {
            $lead->update(['status' => Lead::STATUS_FAILED]);

            Log::error('sendEmail: failed', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);

            return redirect()->route('leads.index')
                ->with('error', "Failed to send email: " . $e->getMessage());
        }
    }

    /**
     * Export all leads as a CSV file.
     */
    public function export(): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="leads_' . now()->format('Y-m-d') . '.csv"',
        ];

        $leads = Lead::orderBy('created_at', 'desc')->get();

        $callback = function () use ($leads) {
            $handle = fopen('php://output', 'w');

            // CSV header row
            fputcsv($handle, ['ID', 'Company Name', 'Website', 'Email', 'LinkedIn', 'Status', 'Created At']);

            foreach ($leads as $lead) {
                fputcsv($handle, [
                    $lead->id,
                    $lead->company_name,
                    $lead->website,
                    $lead->email,
                    $lead->linkedin,
                    $lead->status,
                    $lead->created_at->toDateTimeString(),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
