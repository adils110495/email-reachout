<?php

namespace App\Http\Requests\Sequencer;

use App\Sequencer\Enums\StepStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SequenceStepRequest extends FormRequest
{
    use PartialRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Blank delay boxes mean zero.
        $merge = [];
        foreach (['delay_minutes', 'delay_hours', 'delay_days'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $merge[$key] = 0;
            }
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        // When an Email Template is chosen, a blank subject / body is filled from it.
        $presence = $this->filled('template_id') ? 'nullable' : 'required';

        return $this->partial([
            'subject' => [$presence, 'string', 'max:998'],
            'body' => [$presence, 'string', 'max:200000'],
            'delay_minutes' => ['nullable', 'integer', 'between:0,59'],
            'delay_hours' => ['nullable', 'integer', 'between:0,23'],
            'delay_days' => ['nullable', 'integer', 'between:0,365'],
            'status' => ['nullable', Rule::in([StepStatus::Active->value, StepStatus::Inactive->value])],
            'template_id' => ['nullable', 'integer', Rule::exists('email_templates', 'id')->where('status', 'active')],
        ]);
    }
}
