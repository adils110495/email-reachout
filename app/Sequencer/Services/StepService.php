<?php

namespace App\Sequencer\Services;

use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\MailSetting;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Models\SequenceStep;
use App\Sequencer\Enums\EnrollmentStatus;
use App\Sequencer\Enums\StepStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages the ordered steps of a sequence.
 *
 * Enrollments remember progress as "the last step number completed", so
 * structural edits adjust that counter to keep every contact on the right step.
 */
class StepService
{
    public function __construct(
        private readonly TemplateRendererService $renderer,
        private readonly EmailComposer $composer,
    ) {}

    /** Append a step. When $template is given and subject/body are blank they are copied from it. */
    public function add(Sequence $sequence, array $data, ?EmailTemplate $template = null): SequenceStep
    {
        return DB::transaction(function () use ($sequence, $data, $template) {
            // Serialise concurrent adds so two requests cannot take the same number.
            Sequence::whereKey($sequence->id)->lockForUpdate()->first();

            $data = $this->normalise($data);

            if ($template) {
                $data['subject'] = ($data['subject'] ?? '') !== '' ? $data['subject'] : $template->subject;
                $data['body'] = ($data['body'] ?? '') !== '' ? $data['body'] : $template->body;
            }

            $step = new SequenceStep($data);
            $step->sequence_id = $sequence->id;
            $step->step_number = ((int) $sequence->steps()->max('step_number')) + 1;
            $step->status ??= StepStatus::Active;
            $step->save();

            return $step;
        });
    }

    public function update(SequenceStep $step, array $data): SequenceStep
    {
        $step->fill($this->normalise($data))->save();

        return $step;
    }

    /** Null / blank delays mean zero; a null status keeps the current one. */
    private function normalise(array $data): array
    {
        foreach (['delay_minutes', 'delay_hours', 'delay_days'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = (int) ($data[$key] ?? 0);
            }
        }

        if (array_key_exists('status', $data) && $data['status'] === null) {
            unset($data['status']);
        }

        return $data;
    }

    public function delete(SequenceStep $step): void
    {
        DB::transaction(function () use ($step) {
            $sequence = Sequence::whereKey($step->sequence_id)->lockForUpdate()->first();
            $number = $step->step_number;

            $step->delete();

            SequenceStep::where('sequence_id', $sequence->id)->where('step_number', '>', $number)->decrement('step_number');

            // Contacts that had already passed this step have one fewer step behind them.
            SequenceEnrollment::where('sequence_id', $sequence->id)->where('current_step', '>=', $number)->decrement('current_step');
        });
    }

    /**
     * Put steps in the order given by $orderedIds (all of the sequence's step ids).
     *
     * @param  list<int>  $orderedIds
     *
     * @throws ValidationException
     */
    public function reorder(Sequence $sequence, array $orderedIds): void
    {
        DB::transaction(function () use ($sequence, $orderedIds) {
            Sequence::whereKey($sequence->id)->lockForUpdate()->first();

            $current = $sequence->steps()->pluck('id')->all();
            $given = array_values(array_unique(array_map('intval', $orderedIds)));

            if (count($given) !== count($current) || array_diff($current, $given) !== []) {
                throw ValidationException::withMessages(['order' => 'The new order must list every step exactly once.']);
            }

            if ($given === $current) {
                return;
            }

            $inFlight = SequenceEnrollment::where('sequence_id', $sequence->id)
                ->whereIn('status', array_map(fn ($s) => $s->value, EnrollmentStatus::open()))
                ->where('current_step', '>', 0)
                ->exists();

            if ($inFlight) {
                throw ValidationException::withMessages(['order' => 'Steps cannot be reordered while contacts are part-way through the sequence. Duplicate the sequence to change the order.']);
            }

            foreach ($given as $position => $id) {
                SequenceStep::whereKey($id)->where('sequence_id', $sequence->id)->update(['step_number' => $position + 1]);
            }
        });
    }

    /** Insert a copy directly after $step. */
    public function duplicate(SequenceStep $step): SequenceStep
    {
        return DB::transaction(function () use ($step) {
            $sequence = Sequence::whereKey($step->sequence_id)->lockForUpdate()->first();
            $position = $step->step_number + 1;

            SequenceStep::where('sequence_id', $sequence->id)->where('step_number', '>=', $position)->increment('step_number');
            SequenceEnrollment::where('sequence_id', $sequence->id)->where('current_step', '>=', $position)->increment('current_step');

            $copy = $step->replicate();
            $copy->sequence_id = $sequence->id;
            $copy->step_number = $position;
            $copy->save();

            return $copy;
        });
    }

    /**
     * Render a step the way a recipient would see it, with a real lead's data or a sample.
     *
     * @return array{subject: string, html: string, unknown: list<string>}
     */
    public function preview(SequenceStep $step, ?Lead $lead = null, ?MailSetting $account = null): array
    {
        $variables = $lead
            ? $this->renderer->variablesFor($lead, $account)
            : $this->renderer->sampleVariables($account);

        return [
            'subject' => $this->renderer->renderSubject($step->subject, $variables),
            'html' => $this->composer->renderBody($step->body, $variables),
            'unknown' => array_values(array_unique([
                ...$this->renderer->unknownVariables($step->subject),
                ...$this->renderer->unknownVariables($step->body),
            ])),
        ];
    }
}
