<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Concerns\RedirectsBack;
use App\Models\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformController extends Controller
{
    use ExportsCsv;
    use RedirectsBack;

    public function index(Request $request): View
    {
        $statusOptions = [
            'active'   => 'Active',
            'inactive' => 'Inactive',
        ];

        $query = Platform::latest();

        // Status is a URL-driven filter, same as the Leads page.
        if ($request->filled('status') && array_key_exists($request->status, $statusOptions)) {
            $query->where('status', $request->status);
        }

        $platforms    = $query->get();
        $activeStatus = $request->input('status');

        $data = compact('platforms', 'activeStatus', 'statusOptions');

        // A filter change fetches just the table partial so the page swaps it
        // in without a full reload.
        if ($request->ajax()) {
            return view('platforms._table', $data);
        }

        return view('platforms.index', $data);
    }

    public function export(): StreamedResponse
    {
        return $this->streamCsv(
            'platforms',
            ['ID', 'Name', 'Status', 'Created At'],
            Platform::latest()->get(),
            fn (Platform $platform) => [
                $platform->id,
                $platform->name,
                $platform->status,
                $platform->created_at->toDateTimeString(),
            ],
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'   => ['required', 'string', 'max:255', 'unique:platforms,name'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        Platform::create($request->only('name', 'status'));

        return redirect()->route('platforms.index', $this->redirectQuery($request))->with('success', 'Platform added successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'name'   => ['required', 'string', 'max:255', 'unique:platforms,name,' . $id],
            'status' => ['required', 'in:active,inactive'],
        ]);

        Platform::findOrFail($id)->update($request->only('name', 'status'));

        return redirect()->route('platforms.index', $this->redirectQuery($request))->with('success', 'Platform updated successfully.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        Platform::findOrFail($id)->delete();

        return redirect()->route('platforms.index', $this->redirectQuery($request))->with('success', 'Platform deleted.');
    }
}
