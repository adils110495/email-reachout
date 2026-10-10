<?php

namespace App\Sequencer\Services;

use App\Models\ActivityEvent;
use App\Models\LeadEmail;
use App\Sequencer\Enums\ActivityType;
use Carbon\CarbonInterface;

/** Writes the per-lead / per-enrollment activity timeline. */
class ActivityRecorder
{
    /**
     * @param  array{sequence_id?: ?int, lead_id?: ?int, enrollment_id?: ?int, lead_email_id?: ?int}  $refs
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ActivityType $type,
        string $description,
        array $refs = [],
        array $metadata = [],
        ?CarbonInterface $at = null,
    ): ActivityEvent {
        return ActivityEvent::create([
            'sequence_id' => $refs['sequence_id'] ?? null,
            'lead_id' => $refs['lead_id'] ?? null,
            'enrollment_id' => $refs['enrollment_id'] ?? null,
            'lead_email_id' => $refs['lead_email_id'] ?? null,
            'type' => $type,
            'description' => mb_substr($description, 0, 500),
            'metadata' => $metadata ?: null,
            'occurred_at' => $at ?? now(),
        ]);
    }

    /** Record an event that belongs to a sent email (inherits its sequence / lead / enrollment). */
    public function forEmail(ActivityType $type, LeadEmail $email, string $description, array $metadata = [], ?CarbonInterface $at = null): ActivityEvent
    {
        return $this->record($type, $description, [
            'sequence_id' => $email->sequence_id,
            'lead_id' => $email->lead_id,
            'enrollment_id' => $email->enrollment_id,
            'lead_email_id' => $email->id,
        ], $metadata, $at);
    }
}
