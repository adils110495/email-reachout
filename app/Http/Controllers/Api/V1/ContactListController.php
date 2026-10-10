<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Sequencer\ContactListRequest;
use App\Http\Resources\ContactListResource;
use App\Http\Resources\ContactResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Lists are the app's categories. */
class ContactListController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ContactListResource::collection(
            Category::withCount('leads')->orderBy('name')->paginate($this->perPage($request))
        );
    }

    public function store(ContactListRequest $request): JsonResponse
    {
        $list = Category::create($request->validated() + ['status' => 'active']);

        return (new ContactListResource($list))->response()->setStatusCode(201);
    }

    public function show(Category $list): ContactListResource
    {
        return new ContactListResource($list->loadCount('leads'));
    }

    public function update(ContactListRequest $request, Category $list): ContactListResource
    {
        $list->update(array_filter($request->validated(), fn ($v) => $v !== null));

        return new ContactListResource($list);
    }

    public function destroy(Category $list): Response
    {
        $list->delete();   // memberships cascade; the leads are kept

        return response()->noContent();
    }

    public function contacts(Request $request, Category $list): AnonymousResourceCollection
    {
        return ContactResource::collection($list->leads()->orderBy('leads.id')->paginate($this->perPage($request)));
    }

    public function addContacts(Request $request, Category $list): JsonResponse
    {
        $data = $request->validate(['contact_ids' => ['required', 'array', 'min:1', 'max:5000'], 'contact_ids.*' => ['integer']]);

        return response()->json(['added' => $list->addLeads($data['contact_ids'])]);
    }

    public function removeContacts(Request $request, Category $list): JsonResponse
    {
        $data = $request->validate(['contact_ids' => ['required', 'array', 'min:1', 'max:5000'], 'contact_ids.*' => ['integer']]);

        return response()->json(['removed' => $list->removeLeads($data['contact_ids'])]);
    }
}
