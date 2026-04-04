<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'name'    => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string'],
        ]);

        EmailTemplate::create($request->only('name', 'subject', 'body'));

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
            'name'    => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string'],
        ]);

        EmailTemplate::findOrFail($id)->update($request->only('name', 'subject', 'body'));

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
        EmailTemplate::findOrFail($id)->update(['status' => 'deleted']);

        return redirect()->route('templates.index')
            ->with('success', 'Template deleted.');
    }
}
