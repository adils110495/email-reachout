<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Concerns\RedirectsBack;
use App\Models\EmailTemplate;
use App\Support\EmailHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailTemplateController extends Controller
{
    use ExportsCsv;
    use RedirectsBack;

    /**
     * Attachment types a browser may show in its own tab. Anything else - an
     * HTML or SVG file above all, which would run as a page of this app - is
     * only ever downloaded.
     */
    private const INLINE_TYPES = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'text/plain'];

    public function export(): StreamedResponse
    {
        return $this->streamCsv(
            'email_templates',
            ['ID', 'Name', 'Subject', 'Body', 'Attachments', 'Status', 'Created At'],
            EmailTemplate::latest()->get(),
            fn (EmailTemplate $template) => [
                $template->id,
                $template->name,
                $template->subject,
                // Body is stored as HTML; flatten it so the CSV stays readable.
                trim(html_entity_decode(strip_tags($template->body))),
                collect($template->attachments ?? [])->pluck('name')->implode(', '),
                $template->status,
                $template->created_at->toDateTimeString(),
            ],
        );
    }

    public function index(Request $request): View
    {
        $statusOptions = [
            'active'   => 'Active',
            'inactive' => 'Inactive',
            'deleted'  => 'Deleted',
        ];

        $query = EmailTemplate::latest();

        // Status is a URL-driven filter, same as the Leads page.
        if ($request->filled('status') && array_key_exists($request->status, $statusOptions)) {
            $query->where('status', $request->status);
        }

        $templates    = $query->get();
        $activeStatus = $request->input('status');

        $data = compact('templates', 'activeStatus', 'statusOptions');

        // A filter change fetches just the table partial so the page swaps it
        // in without a full reload.
        if ($request->ajax()) {
            return view('templates._table', $data);
        }

        return view('templates.index', $data);
    }

    public function create(): View
    {
        return view('templates.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'subject'       => ['required', 'string', 'max:255'],
            'body'          => $this->bodyRules(),
            'attachments'   => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $template = EmailTemplate::create($request->only('name', 'subject') + ['body' => EmailHtml::render($request->input('body'))]);

        if ($request->hasFile('attachments')) {
            $template->update(['attachments' => $this->storeFiles($request->file('attachments'), $template->id)]);
        }

        return redirect()->route('templates.index')
            ->with('success', 'Template created successfully.');
    }

    public function edit(int $id): View
    {
        $template = EmailTemplate::findOrFail($id);
        return view('templates.edit', compact('template'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'subject'          => ['required', 'string', 'max:255'],
            'body'             => $this->bodyRules(),
            'attachments'      => ['nullable', 'array'],
            'attachments.*'    => ['file', 'max:10240'],
            'remove_attachments'   => ['nullable', 'array'],
            'remove_attachments.*' => ['string'],
        ]);

        $template    = EmailTemplate::findOrFail($id);
        $existing    = $template->attachments ?? [];

        // Remove flagged attachments
        $toRemove = $request->input('remove_attachments', []);
        $existing = array_values(array_filter($existing, function ($att) use ($toRemove) {
            if (in_array($att['path'], $toRemove)) {
                Storage::disk('local')->delete($att['path']);
                return false;
            }
            return true;
        }));

        // Add newly uploaded attachments
        if ($request->hasFile('attachments')) {
            $existing = array_merge($existing, $this->storeFiles($request->file('attachments'), $template->id));
        }

        $template->update(array_merge(
            $request->only('name', 'subject'),
            ['body' => EmailHtml::render($request->input('body')), 'attachments' => $existing ?: null]
        ));

        return redirect()->route('templates.index')
            ->with('success', 'Template updated successfully.');
    }

    /**
     * Open one of the template's attachments from its edit page: in the
     * browser for images, PDFs and text, as a download for anything else or
     * with ?download=1. Only files the template lists - and the owner scope
     * only finds the user's own templates.
     */
    public function attachment(Request $request, int $id, string $file): BinaryFileResponse
    {
        $template   = EmailTemplate::findOrFail($id);
        $attachment = collect($template->attachments ?? [])->first(fn (array $a) => basename($a['path'] ?? '') === $file);
        $disk       = Storage::disk('local');

        abort_unless($attachment && $disk->exists($attachment['path']), 404, 'Attachment not found.');

        $path    = $disk->path($attachment['path']);
        $name    = $attachment['name'] ?? $file;
        $mime    = $disk->mimeType($attachment['path']) ?: 'application/octet-stream';
        $inline  = ! $request->boolean('download') && in_array($mime, self::INLINE_TYPES, true);
        $headers = ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff'];

        // A sandboxed response can never run script on this origin. Not for
        // PDFs: the browser's PDF viewer will not open in a sandbox.
        if ($mime !== 'application/pdf') {
            $headers['Content-Security-Policy'] = 'sandbox';
        }

        return response()->download($path, $name, $headers, $inline ? 'inline' : 'attachment');
    }

    public function toggleStatus(Request $request, int $id): RedirectResponse
    {
        $request->validate(['status' => ['required', 'in:active,inactive,deleted']]);
        EmailTemplate::findOrFail($id)->update(['status' => $request->status]);

        return redirect()->route('templates.index', $this->redirectQuery($request))
            ->with('success', 'Template status updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $template = EmailTemplate::findOrFail($id);

        foreach ($template->attachments ?? [] as $att) {
            Storage::disk('local')->delete($att['path']);
        }

        $template->update(['status' => 'deleted']);

        return redirect()->route('templates.index', $this->redirectQuery($request))
            ->with('success', 'Template deleted.');
    }

    /** @return array<int, mixed> */
    private function bodyRules(): array
    {
        return ['required', 'string', 'max:100000', EmailHtml::notBlankRule('Write the email body.')];
    }

    private function storeFiles(array $files, int $templateId): array
    {
        $stored = [];
        foreach ($files as $file) {
            $path    = $file->store("template-attachments/{$templateId}", 'local');
            $stored[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'size' => $file->getSize(),
            ];
        }
        return $stored;
    }
}
