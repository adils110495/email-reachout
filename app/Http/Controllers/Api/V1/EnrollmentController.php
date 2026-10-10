<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Sequencer\EnrollRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\Bulk;
use App\Models\Lead;
use App\Models\MailSetting;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Sequencer\Enums\EnrollmentStatus;
use App\Sequencer\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EnrollmentController extends ApiController
{
    /** Up to this many leads are enrolled inline and reported individually; more go to a Bulk run. */
    private const SYNC_LIMIT = 50;

    public function __construct(private readonly EnrollmentService $enrollments) {}

    public function index(Request $request, Sequence $sequence): AnonymousResourceCollection
    {
        $rows = SequenceEnrollment::where('sequence_id', $sequence->id)
            ->when($request->query('status'), fn ($q, $s) => in_array($s, EnrollmentStatus::values(), true) ? $q->where('status', $s) : $q)
            ->with('lead')
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return EnrollmentResource::collection($rows);
    }

    /**
     * Enroll contact_ids (lead ids) and/or every lead in list_id (a category).
     * Small requests are processed inline and answered per lead; large ones are queued (202).
     */
    public function store(EnrollRequest $request, Sequence $sequence): JsonResponse
    {
        $account = $request->filled('mail_setting_id') ? MailSetting::find($request->input('mail_setting_id')) : null;
        $ids = array_map('intval', $request->input('lead_ids') ?? []);
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;

        if ($categoryId === null && count($ids) <= self::SYNC_LIMIT) {
            $results = [];
            foreach (Lead::whereIn('id', $ids)->get() as $lead) {
                $result = $this->enrollments->enroll($sequence, $lead, $account);
                $results[] = [
                    'contact_id' => $lead->id,
                    'outcome' => $result->outcome,
                    'reason' => $result->reason,
                    'enrollment_id' => $result->enrollment?->id,
                ];
            }

            return response()->json(['results' => $results], 201);
        }

        $all = Lead::query()
            ->where(fn ($q) => $q->whereIn('id', $ids)->when($categoryId, fn ($c) => $c->orWhere(fn ($x) => $x->inCategory($categoryId))))
            ->pluck('id')->all();

        $bulk = Bulk::queueSequenceAction(Bulk::TYPE_ENROLL, $all, 'Enroll in '.$sequence->name, [
            'sequence_id' => $sequence->id,
            'mail_setting_id' => $account?->id,
        ]);

        return response()->json(['bulk_id' => $bulk->id, 'total' => $bulk->total_records, 'status' => $bulk->status], 202);
    }

    public function show(SequenceEnrollment $enrollment): EnrollmentResource
    {
        return new EnrollmentResource($enrollment->load('lead'));
    }

    public function pause(SequenceEnrollment $enrollment): EnrollmentResource
    {
        $this->enrollments->pause($enrollment);

        return new EnrollmentResource($enrollment->refresh());
    }

    public function resume(SequenceEnrollment $enrollment): EnrollmentResource
    {
        $this->enrollments->resume($enrollment);

        return new EnrollmentResource($enrollment->refresh());
    }

    /** Remove the lead from the sequence (status "removed"). */
    public function destroy(SequenceEnrollment $enrollment): EnrollmentResource
    {
        $this->enrollments->remove($enrollment);

        return new EnrollmentResource($enrollment->refresh());
    }
}
