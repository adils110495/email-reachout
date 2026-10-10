<?php

namespace App\Sequencer\Services;

use App\Models\Sequence;
use App\Models\User;
use App\Sequencer\Enums\SequenceStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SequenceService
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    public function create(?User $creator, array $data): Sequence
    {
        $sequence = new Sequence($data + [
            'sending_days' => config('sequencer.defaults.sending_days'),
            'daily_limit' => config('sequencer.defaults.daily_limit'),
        ]);
        $sequence->created_by = $creator?->id;
        $sequence->status = SequenceStatus::Draft;
        $sequence->save();

        return $sequence;
    }

    public function update(Sequence $sequence, array $data): Sequence
    {
        $sequence->fill($data)->save();

        return $sequence;
    }

    /**
     * Start (draft) or resume (paused) a sequence. Needs at least one active step.
     * Enrollments added while it was a draft get their first due time now.
     *
     * @throws ValidationException
     */
    public function activate(Sequence $sequence): Sequence
    {
        if ($sequence->status === SequenceStatus::Active) {
            return $sequence;   // double click / repeated API call: nothing to do
        }

        if ($sequence->status === SequenceStatus::Archived) {
            throw ValidationException::withMessages(['status' => 'An archived sequence cannot be activated. Duplicate it instead.']);
        }

        if (! $sequence->activeSteps()->exists()) {
            throw ValidationException::withMessages(['status' => 'Add at least one active step before activating the sequence.']);
        }

        DB::transaction(function () use ($sequence) {
            $sequence->forceFill([
                'status' => SequenceStatus::Active,
                'activated_at' => $sequence->activated_at ?? now(),
            ])->save();

            $this->enrollments->activatePending($sequence);
        });

        return $sequence;
    }

    /** Pause: nothing is sent until resumed; enrollments keep their position. */
    public function pause(Sequence $sequence): Sequence
    {
        if ($sequence->status === SequenceStatus::Active) {
            $sequence->forceFill(['status' => SequenceStatus::Paused])->save();
        }

        return $sequence;
    }

    public function resume(Sequence $sequence): Sequence
    {
        return $sequence->status === SequenceStatus::Paused ? $this->activate($sequence) : $sequence;
    }

    public function archive(Sequence $sequence): Sequence
    {
        $sequence->forceFill(['status' => SequenceStatus::Archived])->save();

        return $sequence;
    }

    /** Copy settings and steps into a fresh draft. Enrollments are not copied. */
    public function duplicate(Sequence $sequence, ?User $creator = null): Sequence
    {
        return DB::transaction(function () use ($sequence, $creator) {
            $copy = $sequence->replicate(['status', 'activated_at']);
            $copy->name = mb_substr('Copy of '.$sequence->name, 0, 255);
            $copy->status = SequenceStatus::Draft;
            $copy->created_by = $creator?->id ?? $sequence->created_by;
            $copy->save();

            foreach ($sequence->steps as $step) {
                $new = $step->replicate();
                $new->sequence_id = $copy->id;
                $new->save();
            }

            return $copy;
        });
    }
}
