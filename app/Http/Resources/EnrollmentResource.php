<?php

namespace App\Http\Resources;

use App\Models\SequenceEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SequenceEnrollment */
class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sequence_id' => $this->sequence_id,
            'contact_id' => $this->lead_id,
            'email_account_id' => $this->mail_setting_id,
            'current_step' => $this->current_step,
            'status' => $this->status->value,
            'started_at' => $this->started_at?->toIso8601String(),
            'next_action_at' => $this->next_action_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'stopped_at' => $this->stopped_at?->toIso8601String(),
            'stop_reason' => $this->stop_reason,
            'contact' => new ContactResource($this->whenLoaded('lead')),
        ];
    }
}
