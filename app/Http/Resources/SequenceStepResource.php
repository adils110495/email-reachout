<?php

namespace App\Http\Resources;

use App\Models\SequenceStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SequenceStep */
class SequenceStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sequence_id' => $this->sequence_id,
            'step_number' => $this->step_number,
            'subject' => $this->subject,
            'body' => $this->body,
            'delay_minutes' => $this->delay_minutes,
            'delay_hours' => $this->delay_hours,
            'delay_days' => $this->delay_days,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
