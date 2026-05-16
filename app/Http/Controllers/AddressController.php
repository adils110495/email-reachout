<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function index(): View
    {
        $addresses = Address::latest()->get();
        return view('addresses.index', compact('addresses'));
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

        return redirect()->route('addresses.index')->with('success', 'Address added successfully.');
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

        return redirect()->route('addresses.index')->with('success', 'Address updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        Address::findOrFail($id)->delete();

        return redirect()->route('addresses.index')->with('success', 'Address deleted.');
    }
}
