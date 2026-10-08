<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Concerns\RedirectsBack;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AddressController extends Controller
{
    use ExportsCsv;
    use RedirectsBack;

    public function index(Request $request): View
    {
        $statusOptions = [
            'active'   => 'Active',
            'inactive' => 'Inactive',
        ];

        $query = Address::latest();

        // Status is a URL-driven filter, same as the Leads page.
        if ($request->filled('status') && array_key_exists($request->status, $statusOptions)) {
            $query->where('status', $request->status);
        }

        $addresses    = $query->get();
        $activeStatus = $request->input('status');

        $data = compact('addresses', 'activeStatus', 'statusOptions');

        // A filter change fetches just the table partial so the page swaps it
        // in without a full reload.
        if ($request->ajax()) {
            return view('addresses._table', $data);
        }

        return view('addresses.index', $data);
    }

    public function export(): StreamedResponse
    {
        return $this->streamCsv(
            'addresses',
            ['ID', 'Address', 'Email', 'Phone', 'Alternate Phone', 'Website', 'Status', 'Created At'],
            Address::latest()->get(),
            fn (Address $address) => [
                $address->id,
                $address->address,
                $address->email,
                $address->phone,
                $address->alternate_phone,
                $address->website,
                $address->status,
                $address->created_at->toDateTimeString(),
            ],
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'address'         => ['required', 'string', 'max:500'],
            'email'           => ['required', 'email', 'max:255'],
            'phone'           => ['required', 'string', 'max:50'],
            'alternate_phone' => ['nullable', 'string', 'max:50'],
            'website'         => ['nullable', 'url', 'max:255'],
            'status'          => ['required', 'in:active,inactive'],
        ]);

        Address::create($request->only('address', 'email', 'phone', 'alternate_phone', 'website', 'status'));

        return redirect()->route('addresses.index', $this->redirectQuery($request))->with('success', 'Address added successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'address'         => ['required', 'string', 'max:500'],
            'email'           => ['required', 'email', 'max:255'],
            'phone'           => ['required', 'string', 'max:50'],
            'alternate_phone' => ['nullable', 'string', 'max:50'],
            'website'         => ['nullable', 'url', 'max:255'],
            'status'          => ['required', 'in:active,inactive'],
        ]);

        Address::findOrFail($id)->update($request->only('address', 'email', 'phone', 'alternate_phone', 'website', 'status'));

        return redirect()->route('addresses.index', $this->redirectQuery($request))->with('success', 'Address updated successfully.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        Address::findOrFail($id)->delete();

        return redirect()->route('addresses.index', $this->redirectQuery($request))->with('success', 'Address deleted.');
    }
}
