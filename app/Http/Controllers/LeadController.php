<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeLeadEmailJob;
use App\Mail\OutreachMail;
use App\Models\Lead;
use App\Models\LeadEmail;
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
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50, 100])
            ? (int) $request->query('per_page')
            : 25;

        $query = Lead::with('platform')->orderBy('id', 'desc');

        $validStatuses = ['new', 'sent', 'failed', 'replied'];
        if ($request->filled('status') && in_array($request->status, $validStatuses)) {
            $query->where('status', $request->status);
        }

        $leads     = $query->paginate($perPage)->withQueryString();
        $platforms = Platform::active()->orderBy('name')->get();

        $senderName    = env('SENDER_NAME', 'Sales Team');
        $senderCompany = env('SENDER_COMPANY', 'Our Company');

        return view('leads.index', compact('leads', 'platforms', 'senderName', 'senderCompany'));
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

        // 1. Fetch results from SerpAPI — pass env-configured country/language/limit
        $results = $this->leadFinder->find(
            keyword:  $keyword,
            country:  env('LEAD_COUNTRY',  null) ?: null,
            language: env('LEAD_LANGUAGE', null) ?: null,
        );

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

    public function downloadAttachment(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $path         = $request->query('path');
        $originalName = $request->query('name');

        abort_if(!$path, 400);

        $fullPath = storage_path('app/' . $path);
        abort_if(!file_exists($fullPath), 404, 'Attachment not found.');

        return response()->download($fullPath, $originalName);
    }

    public function sentEmail(int $id): \Illuminate\Http\JsonResponse
    {
        $lead      = Lead::findOrFail($id);
        $leadEmail = LeadEmail::where('lead_id', $id)
                              ->where('status', 'sent')
                              ->latest()
                              ->first();

        return response()->json([
            'lead'  => $lead->only(['id', 'company_name', 'email']),
            'email' => $leadEmail,
        ]);
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
     * Scrape the lead's website in background to extract the real company name.
     */
    public function scrapeContact(int $id): \Illuminate\Http\JsonResponse
    {
        $lead = Lead::findOrFail($id);

        if (empty($lead->website)) {
            return response()->json(['client_name' => $lead->company_name]);
        }

        try {
            $html = $this->scraper->fetch($lead->website);

            // Try og:site_name first (most reliable)
            if (preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\'](.*?)["\']/i', $html, $m)) {
                $name = trim(html_entity_decode($m[1], ENT_QUOTES));
                if ($name) return response()->json(['client_name' => $name]);
            }

            // Try <title> tag — strip common suffixes
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
                $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES));
                // Remove everything after " - ", " | ", " – "
                $title = preg_split('/\s*[\-\|–]\s*/', $title)[0];
                $title = trim($title);
                if ($title) return response()->json(['client_name' => $title]);
            }

            // Fallback to stored company name
            return response()->json(['client_name' => $lead->company_name]);
        } catch (\Throwable $e) {
            return response()->json(['client_name' => $lead->company_name]);
        }
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
            'subject'                    => ['required', 'string', 'max:255'],
            'body'                       => ['required', 'string'],
            'attachments'                => ['nullable', 'array'],
            'attachments.*'              => ['file', 'max:10240'],
            'template_attachment_paths'  => ['nullable', 'array'],
            'template_attachment_paths.*'=> ['string'],
            'template_attachment_names'  => ['nullable', 'array'],
            'template_attachment_names.*'=> ['string'],
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

        // Store attachments permanently under lead-attachments/{lead_id}/
        $attachmentMeta = [];
        $attachments    = []; // [['path' => fullPath, 'name' => originalName], ...]

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $originalName = $file->getClientOriginalName();
                $storedPath   = $file->store("lead-attachments/{$lead->id}", 'local');
                $fullPath     = storage_path('app/' . $storedPath);

                $attachments[]    = ['path' => $fullPath, 'name' => $originalName];
                $attachmentMeta[] = [
                    'name' => $originalName,
                    'path' => $storedPath,
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                ];
            }
        }

        // Template attachments (already stored server-side)
        $tplPaths = $request->input('template_attachment_paths', []);
        $tplNames = $request->input('template_attachment_names', []);
        foreach ($tplPaths as $i => $storedPath) {
            $fullPath     = storage_path('app/' . $storedPath);
            $originalName = $tplNames[$i] ?? basename($storedPath);
            if (file_exists($fullPath)) {
                $attachments[]    = ['path' => $fullPath, 'name' => $originalName];
                $attachmentMeta[] = ['name' => $originalName, 'path' => $storedPath, 'size' => filesize($fullPath), 'mime' => mime_content_type($fullPath)];
            }
        }

        try {
            $mailable = new OutreachMail($lead, $body, $subject, $senderName, $senderCompany, emailAttachments: $attachments);

            // Send directly — no queue
            Mail::to($lead->email)->send($mailable);

            // Copy to IMAP Sent folder (with same attachments + original names)
            app(ImapService::class)->copyToSentFolder(
                to:          $lead->email,
                subject:     $subject,
                htmlBody:    $mailable->render(),
                fromName:    env('MAIL_FROM_NAME', $senderName),
                fromEmail:   env('MAIL_FROM_ADDRESS'),
                attachments: $attachments,
            );

            $lead->update(['status' => Lead::STATUS_SENT]);

            // Save email record with attachment metadata
            LeadEmail::create([
                'lead_id'     => $lead->id,
                'subject'     => $subject,
                'body'        => $body,
                'attachments' => !empty($attachmentMeta) ? json_encode($attachmentMeta) : null,
                'status'      => 'sent',
                'sent_at'     => now(),
            ]);

            Log::info('sendEmail: sent directly', [
                'lead_id'     => $lead->id,
                'to'          => $lead->email,
                'attachments' => count($attachmentMeta),
            ]);

            return redirect()->route('leads.index')
                ->with('success', "Email sent successfully to \"{$lead->company_name}\".");

        } catch (\Throwable $e) {
            $lead->update(['status' => Lead::STATUS_FAILED]);

            LeadEmail::create([
                'lead_id'     => $lead->id,
                'subject'     => $subject,
                'body'        => $body,
                'attachments' => !empty($attachmentMeta) ? json_encode($attachmentMeta) : null,
                'status'      => 'failed',
                'sent_at'     => now(),
            ]);

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
