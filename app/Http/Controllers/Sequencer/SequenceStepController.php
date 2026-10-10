<?php

namespace App\Http\Controllers\Sequencer;

use App\Http\Requests\Sequencer\SequenceStepRequest;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\MailSetting;
use App\Models\Sequence;
use App\Models\SequenceStep;
use App\Sequencer\Services\StepService;
use App\Sequencer\Services\TemplateRendererService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SequenceStepController extends SequencerController
{
    public function __construct(private readonly StepService $steps) {}

    public function create(Sequence $sequence): View
    {
        return view('sequencer.steps.form', $this->formData($sequence, new SequenceStep(['delay_days' => $sequence->steps()->exists() ? 2 : 0])));
    }

    public function store(SequenceStepRequest $request, Sequence $sequence): RedirectResponse
    {
        // "Start from a template" copies an Email Template (Settings > Email Templates) into the step.
        $template = $request->filled('template_id') ? EmailTemplate::where('status', 'active')->find($request->input('template_id')) : null;

        $this->steps->add($sequence, $request->safe()->except('template_id'), $template);

        return redirect()->route('outreach.sequences.show', $sequence)->with('success', 'Step added.');
    }

    public function edit(SequenceStep $step): View
    {
        return view('sequencer.steps.form', $this->formData($step->sequence, $step));
    }

    public function update(SequenceStepRequest $request, SequenceStep $step): RedirectResponse
    {
        $this->steps->update($step, $request->safe()->except('template_id'));

        return redirect()->route('outreach.sequences.show', $step->sequence_id)->with('success', 'Step updated.');
    }

    public function destroy(SequenceStep $step): RedirectResponse
    {
        $sequenceId = $step->sequence_id;
        $this->steps->delete($step);

        return redirect()->route('outreach.sequences.show', $sequenceId)->with('success', 'Step deleted.');
    }

    public function duplicate(SequenceStep $step): RedirectResponse
    {
        $this->steps->duplicate($step);

        return redirect()->route('outreach.sequences.show', $step->sequence_id)->with('success', 'Step duplicated.');
    }

    /** Move a step one position up or down. */
    public function move(Request $request, SequenceStep $step): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $ids = $step->sequence->steps()->pluck('id')->all();
        $index = array_search($step->id, $ids, true);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if (isset($ids[$swap])) {
            [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];

            try {
                $this->steps->reorder($step->sequence, $ids);
            } catch (ValidationException $e) {
                return back()->with('error', $e->validator->errors()->first());
            }
        }

        return back();
    }

    /** Render a step with sample data (or a chosen lead's) exactly as a recipient would see it. */
    public function preview(Request $request, SequenceStep $step): View
    {
        $lead = $request->integer('lead') ? Lead::find($request->integer('lead')) : null;
        $account = $step->sequence->mailSetting ?? MailSetting::defaultAccount();

        return view('sequencer.steps.preview', [
            'step' => $step->load('sequence'),
            'preview' => $this->steps->preview($step, $lead, $account),
            'lead' => $lead,
        ]);
    }

    private function formData(Sequence $sequence, SequenceStep $step): array
    {
        return [
            'sequence' => $sequence,
            'step' => $step,
            'templates' => EmailTemplate::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'variables' => TemplateRendererService::VARIABLES,
        ];
    }
}
