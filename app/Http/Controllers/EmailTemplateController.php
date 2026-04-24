<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    public function index(): View
    {
        $templates = EmailTemplate::latest()->get();
        return view('templates.index', compact('templates'));
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
            'body'          => ['required', 'string'],
            'attachments'   => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $template = EmailTemplate::create($request->only('name', 'subject', 'body'));

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
            'body'             => ['required', 'string'],
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
            $request->only('name', 'subject', 'body'),
            ['attachments' => $existing ?: null]
        ));

        return redirect()->route('templates.index')
            ->with('success', 'Template updated successfully.');
    }

    public function toggleStatus(Request $request, int $id): RedirectResponse
    {
        $request->validate(['status' => ['required', 'in:active,inactive,deleted']]);
        EmailTemplate::findOrFail($id)->update(['status' => $request->status]);

        return redirect()->route('templates.index')
            ->with('success', 'Template status updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $template = EmailTemplate::findOrFail($id);

        foreach ($template->attachments ?? [] as $att) {
            Storage::disk('local')->delete($att['path']);
        }

        $template->update(['status' => 'deleted']);

        return redirect()->route('templates.index')
            ->with('success', 'Template deleted.');
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
