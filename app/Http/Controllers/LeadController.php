<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Jobs\FindLeadsJob;
use App\Mail\OutreachMail;
use App\Models\Address;
use App\Models\Category;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Platform;
use App\Services\AIService;
use App\Services\EmailExtractorService;
use App\Services\EmailSenderService;
use App\Services\ImapService;
use App\Services\MailConfigService;
use App\Services\ScraperService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    use ExportsCsv;

    public function __construct(
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

        $query = Lead::with(['platform', 'category'])->orderBy('id', 'desc');

        $validStatuses = ['new', 'sent', 'failed', 'replied'];
        if ($request->filled('status') && in_array($request->status, $validStatuses)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category') && is_numeric($request->category)) {
            $query->where('category_id', (int) $request->category);
        }

        if ($request->filled('platform') && is_numeric($request->platform)) {
            $query->where('platform_id', (int) $request->platform);
        }

        $leads      = $query->paginate($perPage)->withQueryString();
        $platforms  = Platform::active()->orderBy('name')->get();
        $categories = Category::active()->orderBy('name')->get();
        $addresses  = Address::active()->orderBy('id')->get();

        $activeCategory = $request->input('category');
        $activeCatObj   = ($activeCategory && is_numeric($activeCategory))
            ? $categories->firstWhere('id', (int) $activeCategory)
            : null;

        $activePlatform = $request->input('platform');

        $senderName    = env('SENDER_NAME', 'Sales Team');
        $senderCompany = env('SENDER_COMPANY', 'Our Company');

        $data = compact('leads', 'platforms', 'categories', 'addresses', 'activeCategory', 'activeCatObj', 'activePlatform', 'senderName', 'senderCompany');

        // Filter / paginate / per-page changes fetch just the table partial so
        // the page swaps it in without a full reload.
        if ($request->ajax()) {
            return view('leads._table', $data);
        }

        return view('leads.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'website'      => ['required', 'url', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'linkedin'     => ['nullable', 'url', 'max:255'],
            'platform_id'  => ['nullable', 'exists:platforms,id'],
            'category_id'  => ['nullable', 'exists:categories,id'],
        ]);

        $raw = $request->input('category_id');

        Lead::create([
            'company_name' => $request->company_name,
            'website'      => $request->website,
            'email'        => $request->email,
            'linkedin'     => $request->linkedin,
            'status'       => 'new',
            'platform_id'  => $request->platform_id,
            'category_id'  => ($raw !== null && $raw !== '') ? (int) $raw : null,
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
            'keyword'         => ['required', 'string', 'min:2', 'max:200'],
            'search_category' => ['required', 'exists:categories,id'],
        ]);

        $keyword    = $request->input('keyword');
        $categoryId = (int) $request->input('search_category');

        // The SerpAPI call and the lead inserts run on the queue so the request
        // returns straight away. env() is read here, not in the job, because it is
        // unreliable in a worker once config is cached.
        FindLeadsJob::dispatch(
            keyword:    $keyword,
            categoryId: $categoryId,
            country:    env('LEAD_COUNTRY',  null) ?: null,
            language:   env('LEAD_LANGUAGE', null) ?: null,
        )->onQueue('default');

        return redirect()->route('leads.index', ['category' => $categoryId])
            ->with('success', "Searching for \"{$keyword}\" in the background. New leads will appear here as they are found.")
            ->with('search_queued', true);
    }

    /**
     * Return a single lead as JSON (used by the View modal).
     */
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        return response()->json(Lead::with(['platform', 'category'])->findOrFail($id));
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
        return response()->json(Lead::with(['platform', 'category'])->findOrFail($id));
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
            'category_id'  => ['nullable', 'exists:categories,id'],
        ]);

        $lead->update($data);

        $redirectBack = $request->input('_redirect_back', '');
        $query = [];
        if ($redirectBack) {
            parse_str(ltrim($redirectBack, '?'), $query);
        }

        return redirect()->route('leads.index', $query)->with('success', "Lead \"{$lead->company_name}\" updated.");
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
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $lead = Lead::findOrFail($id);
        $lead->delete();

        $query = [];
        if ($redirectBack = $request->input('_redirect_back', '')) {
            parse_str(ltrim($redirectBack, '?'), $query);
        }

        return redirect()->route('leads.index', $query)->with('success', "Lead deleted.");
    }

    /**
     * Bulk-delete selected leads.
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);

        $count = Lead::whereIn('id', $request->ids)->delete();

        $query = [];
        if ($redirectBack = $request->input('_redirect_back', '')) {
            parse_str(ltrim($redirectBack, '?'), $query);
        }

        return redirect()->route('leads.index', $query)->with('success', "{$count} lead(s) deleted.");
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
            // Homepage only: everything read below (og:site_name, <title>) lives
            // there, so contact/about/careers pages would be requests thrown away.
            // Budgeted because this runs inside a web request.
            $html = $this->scraper->fetch($lead->website, budgetSeconds: 12.0, maxExtraPages: 0);

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
            'address_id'                 => ['nullable', 'exists:addresses,id'],
            'attachments'                => ['nullable', 'array'],
            'attachments.*'              => ['file', 'max:10240'],
            'template_attachment_paths'  => ['nullable', 'array'],
            'template_attachment_paths.*'=> ['string'],
            'template_attachment_names'  => ['nullable', 'array'],
            'template_attachment_names.*'=> ['string'],
        ]);

        $lead = Lead::findOrFail($id);

        $redirectQuery = [];
        if ($redirectBack = $request->input('_redirect_back', '')) {
            parse_str(ltrim($redirectBack, '?'), $redirectQuery);
        }

        if (! $lead->hasEmail()) {
            return redirect()->route('leads.index', $redirectQuery)
                ->with('error', "Lead \"{$lead->company_name}\" has no email address.");
        }

        if ($lead->status === Lead::STATUS_SENT) {
            return redirect()->route('leads.index', $redirectQuery)
                ->with('error', "Email already sent to \"{$lead->company_name}\".");
        }

        $senderName    = env('SENDER_NAME', 'Sales Team');
        $senderCompany = env('SENDER_COMPANY', 'Our Company');
        $subject       = $request->input('subject');
        $body          = $request->input('body');
        $address       = $request->filled('address_id') ? Address::find((int) $request->input('address_id')) : null;

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
            // Use the SMTP saved under Settings > Mail Settings (falls back to .env)
            $mailConfig = app(MailConfigService::class);
            $mailConfig->applySmtp();

            // Tracking: the token keys the open pixel, the Message-ID lets replies be matched.
            $trackingToken = Str::random(40);
            $fromAddress   = (string) $mailConfig->fromAddress();
            $messageId     = $trackingToken . '@' . (Str::after($fromAddress, '@') ?: 'localhost');

            $mailable = new OutreachMail($lead, $body, $subject, $senderName, $senderCompany, emailAttachments: $attachments, address: $address, trackingToken: $trackingToken, messageId: $messageId);

            // Send directly — no queue
            Mail::to($lead->email)->send($mailable);

            // Copy to IMAP Sent folder (with same attachments + original names).
            // Rendered WITHOUT the pixel, otherwise opening the Sent folder would count as an open.
            $sentCopy = new OutreachMail($lead, $body, $subject, $senderName, $senderCompany, emailAttachments: $attachments, address: $address);

            app(ImapService::class)->copyToSentFolder(
                to:          $lead->email,
                subject:     $subject,
                htmlBody:    $sentCopy->render(),
                fromName:    $mailConfig->fromName($senderName),
                fromEmail:   (string) $mailConfig->fromAddress(),
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
                'tracking_token' => $trackingToken,
                'message_id'     => $messageId,
            ]);

            Log::info('sendEmail: sent directly', [
                'lead_id'     => $lead->id,
                'to'          => $lead->email,
                'attachments' => count($attachmentMeta),
            ]);

            return redirect()->route('leads.index', $redirectQuery)
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

            return redirect()->route('leads.index', $redirectQuery)
                ->with('error', "Failed to send email: " . $e->getMessage());
        }
    }

    /**
     * Export all leads as a CSV file.
     */
    public function export(): StreamedResponse
    {
        return $this->streamCsv(
            'leads',
            ['ID', 'Company Name', 'Website', 'Email', 'LinkedIn', 'Status', 'Created At'],
            Lead::orderBy('created_at', 'desc')->get(),
            fn (Lead $lead) => [
                $lead->id,
                $lead->company_name,
                $lead->website,
                $lead->email,
                $lead->linkedin,
                $lead->status,
                $lead->created_at->toDateTimeString(),
            ],
        );
    }
}
