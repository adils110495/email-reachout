<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::latest()->get();
        return view('categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'   => ['required', 'string', 'max:255', 'unique:categories,name'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        Category::create($request->only('name', 'status'));

        return redirect()->route('categories.index')->with('success', 'Category added successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'name'   => ['required', 'string', 'max:255', 'unique:categories,name,' . $id],
            'status' => ['required', 'in:active,inactive'],
        ]);

        Category::findOrFail($id)->update($request->only('name', 'status'));

        return redirect()->route('categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        Category::findOrFail($id)->delete();

        return redirect()->route('categories.index')->with('success', 'Category deleted.');
    }
}
