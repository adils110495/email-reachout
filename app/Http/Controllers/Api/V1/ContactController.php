<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Sequencer\ContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Lead;
use App\Sequencer\Enums\ContactStatus;
use App\Sequencer\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Contacts are the app's leads. */
class ContactController extends ApiController
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $leads = Lead::query()
            ->search($request->query('q'))
            ->when($request->query('status'), fn ($q, $s) => in_array($s, ContactStatus::values(), true) ? $q->where('contact_status', $s) : $q)
            ->when($request->integer('list_id'), fn ($q, $id) => $q->inCategory($id))
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ContactResource::collection($leads);
    }

    public function store(ContactRequest $request): JsonResponse
    {
        $lead = Lead::create($request->validated() + ['status' => Lead::STATUS_NEW]);

        return (new ContactResource($lead->refresh()))->response()->setStatusCode(201);
    }

    public function show(Lead $contact): ContactResource
    {
        return new ContactResource($contact->load('categories:id,name'));
    }

    public function update(ContactRequest $request, Lead $contact): ContactResource
    {
        $contact->update($request->validated());

        // Email status is system-managed (stops sequences; an unsubscribe is permanent).
        if ($request->filled('status') && ($status = ContactStatus::tryFrom((string) $request->input('status')))) {
            $this->enrollments->setContactStatus($contact, $status);
        }

        return new ContactResource($contact->refresh());
    }

    public function destroy(Lead $contact): Response
    {
        $contact->delete();   // enrollments and sent emails cascade, as in the Leads module

        return response()->noContent();
    }
}
