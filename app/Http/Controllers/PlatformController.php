<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformController extends Controller
{
    public function index(): View
    {
        $platforms = Platform::latest()->get();
        return view('platforms.index', compact('platforms'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'   => ['required', 'string', 'max:255', 'unique:platforms,name'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        Platform::create($request->only('name', 'status'));

        return redirect()->route('platforms.index')->with('success', 'Platform added successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'name'   => ['required', 'string', 'max:255', 'unique:platforms,name,' . $id],
            'status' => ['required', 'in:active,inactive'],
        ]);

        Platform::findOrFail($id)->update($request->only('name', 'status'));

        return redirect()->route('platforms.index')->with('success', 'Platform updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        Platform::findOrFail($id)->delete();

        return redirect()->route('platforms.index')->with('success', 'Platform deleted.');
    }
}
