<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\EmailLogResource;
use App\Models\LeadEmail;
use App\Sequencer\Enums\EmailLogStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Email logs are the lead_emails rows also shown on the Email Activity page. */
class EmailLogController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $status = EmailLogStatus::tryFrom((string) $request->query('status'));

        $logs = LeadEmail::query()
            ->when($status, fn ($q) => match ($status) {
                // Engagement states are derived from timestamps; delivery states are stored.
                EmailLogStatus::Opened => $q->where('open_count', '>', 0),
                EmailLogStatus::Clicked => $q->whereNotNull('clicked_at'),
                EmailLogStatus::Replied => $q->whereNotNull('replied_at'),
                default => $q->where('status', $status->value),
            })
            ->when($request->integer('sequence_id'), fn ($q, $id) => $q->where('sequence_id', $id))
            ->when($request->integer('contact_id'), fn ($q, $id) => $q->where('lead_id', $id))
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return EmailLogResource::collection($logs);
    }

    public function show(LeadEmail $log): EmailLogResource
    {
        return new EmailLogResource($log);
    }
}
