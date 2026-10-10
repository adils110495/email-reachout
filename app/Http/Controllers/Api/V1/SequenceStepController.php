<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Sequencer\SequenceStepRequest;
use App\Http\Resources\SequenceStepResource;
use App\Models\EmailTemplate;
use App\Models\Sequence;
use App\Models\SequenceStep;
use App\Sequencer\Services\StepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SequenceStepController extends ApiController
{
    public function __construct(private readonly StepService $steps) {}

    public function index(Sequence $sequence): AnonymousResourceCollection
    {
        return SequenceStepResource::collection($sequence->steps);
    }

    public function store(SequenceStepRequest $request, Sequence $sequence): JsonResponse
    {
        $template = $request->filled('template_id') ? EmailTemplate::find($request->input('template_id')) : null;
        $step = $this->steps->add($sequence, $request->safe()->except('template_id'), $template);

        return (new SequenceStepResource($step->refresh()))->response()->setStatusCode(201);
    }

    public function show(SequenceStep $step): SequenceStepResource
    {
        return new SequenceStepResource($step);
    }

    public function update(SequenceStepRequest $request, SequenceStep $step): SequenceStepResource
    {
        return new SequenceStepResource($this->steps->update($step, $request->safe()->except('template_id')));
    }

    public function destroy(SequenceStep $step): Response
    {
        $this->steps->delete($step);

        return response()->noContent();
    }

    public function duplicate(SequenceStep $step): JsonResponse
    {
        return (new SequenceStepResource($this->steps->duplicate($step)))->response()->setStatusCode(201);
    }

    public function reorder(Request $request, Sequence $sequence): AnonymousResourceCollection
    {
        $data = $request->validate(['order' => ['required', 'array', 'min:1'], 'order.*' => ['integer']]);

        $this->steps->reorder($sequence, $data['order']);

        return SequenceStepResource::collection($sequence->steps()->get());
    }
}
