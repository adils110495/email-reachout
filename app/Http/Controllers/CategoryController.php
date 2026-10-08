<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Concerns\RedirectsBack;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryController extends Controller
{
    use ExportsCsv;
    use RedirectsBack;

    public function index(Request $request): View
    {
        $statusOptions = [
            'active'   => 'Active',
            'inactive' => 'Inactive',
        ];

        $query = Category::latest();

        // Status is a URL-driven filter, same as the Leads page.
        if ($request->filled('status') && array_key_exists($request->status, $statusOptions)) {
            $query->where('status', $request->status);
        }

        $categories   = $query->get();
        $activeStatus = $request->input('status');

        $data = compact('categories', 'activeStatus', 'statusOptions');

        // A filter change fetches just the table partial so the page swaps it
        // in without a full reload.
        if ($request->ajax()) {
            return view('categories._table', $data);
        }

        return view('categories.index', $data);
    }

    public function export(): StreamedResponse
    {
        return $this->streamCsv(
            'categories',
            ['ID', 'Name', 'Status', 'Created At'],
            Category::latest()->get(),
            fn (Category $category) => [
                $category->id,
                $category->name,
                $category->status,
                $category->created_at->toDateTimeString(),
            ],
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'   => ['required', 'string', 'max:255', 'unique:categories,name'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        Category::create($request->only('name', 'status'));

        return redirect()->route('categories.index', $this->redirectQuery($request))->with('success', 'Category added successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'name'   => ['required', 'string', 'max:255', 'unique:categories,name,' . $id],
            'status' => ['required', 'in:active,inactive'],
        ]);

        Category::findOrFail($id)->update($request->only('name', 'status'));

        return redirect()->route('categories.index', $this->redirectQuery($request))->with('success', 'Category updated successfully.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        Category::findOrFail($id)->delete();

        return redirect()->route('categories.index', $this->redirectQuery($request))->with('success', 'Category deleted.');
    }
}
