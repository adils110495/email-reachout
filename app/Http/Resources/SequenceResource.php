<?php

namespace App\Http\Resources;

use App\Models\Sequence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sequence */
class SequenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
            'timezone' => $this->effectiveTimezone(),
            'sending_start_time' => $this->startTime(),
            'sending_end_time' => $this->endTime(),
            'sending_days' => $this->sending_days,
            'daily_limit' => $this->daily_limit,
            'email_account_id' => $this->mail_setting_id,
            'track_opens' => $this->track_opens,
            'track_clicks' => $this->track_clicks,
            'steps_count' => $this->whenCounted('steps'),
            'enrollments_count' => $this->whenCounted('enrollments'),
            'steps' => SequenceStepResource::collection($this->whenLoaded('steps')),
            'activated_at' => $this->activated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
