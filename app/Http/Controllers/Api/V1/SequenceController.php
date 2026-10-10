<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Sequencer\SequenceRequest;
use App\Http\Resources\SequenceResource;
use App\Models\Sequence;
use App\Sequencer\Enums\SequenceStatus;
use App\Sequencer\Services\AnalyticsService;
use App\Sequencer\Services\SequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SequenceController extends ApiController
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly AnalyticsService $analytics,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $sequences = Sequence::query()
            ->when($request->query('status'), fn ($q, $s) => in_array($s, SequenceStatus::values(), true) ? $q->where('status', $s) : $q)
            ->withCount(['steps', 'enrollments'])
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return SequenceResource::collection($sequences);
    }

    public function store(SequenceRequest $request): JsonResponse
    {
        $sequence = $this->sequences->create($this->user(), $request->validated());

        return (new SequenceResource($sequence))->response()->setStatusCode(201);
    }

    public function show(Sequence $sequence): SequenceResource
    {
        return new SequenceResource($sequence->load('steps'));
    }

    public function update(SequenceRequest $request, Sequence $sequence): SequenceResource
    {
        return new SequenceResource($this->sequences->update($sequence, $request->validated()));
    }

    public function destroy(Sequence $sequence): Response
    {
        $sequence->delete();

        return response()->noContent();
    }

    public function activate(Sequence $sequence): SequenceResource
    {
        return new SequenceResource($this->sequences->activate($sequence));
    }

    public function pause(Sequence $sequence): SequenceResource
    {
        return new SequenceResource($this->sequences->pause($sequence));
    }

    public function resume(Sequence $sequence): SequenceResource
    {
        return new SequenceResource($this->sequences->resume($sequence));
    }

    public function archive(Sequence $sequence): SequenceResource
    {
        return new SequenceResource($this->sequences->archive($sequence));
    }

    public function analytics(Sequence $sequence): JsonResponse
    {
        $data = $this->analytics->sequence($sequence->load('steps'));

        return response()->json([
            'enrollments' => $data['enrollments'],
            'emails' => $data['emails'],
            'rates' => $data['rates'],
        ]);
    }
}
