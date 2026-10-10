<?php

namespace App\Http\Requests\Sequencer;

use App\Sequencer\Support\Tz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SequenceRequest extends FormRequest
{
    use PartialRules;

    public function authorize(): bool
    {
        return true;   // any signed-in team member; the route is behind auth
    }

    protected function prepareForValidation(): void
    {
        $this->booleansFromForm(['track_opens', 'track_clicks']);

        if (is_array($this->input('sending_days'))) {
            $this->merge(['sending_days' => array_values(array_unique(array_map('intval', $this->input('sending_days'))))]);
        }
    }

    public function rules(): array
    {
        return $this->partial([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'timezone' => ['required', 'string', Rule::in(Tz::all())],
            'sending_start_time' => ['required', 'date_format:H:i'],
            'sending_end_time' => ['required', 'date_format:H:i', 'after:sending_start_time'],
            'sending_days' => ['required', 'array', 'min:1', 'max:7'],
            'sending_days.*' => ['integer', 'between:1,7'],
            'daily_limit' => ['required', 'integer', 'min:1', 'max:1000000'],
            // The sending account, from Settings > Mail Settings.
            'mail_setting_id' => ['nullable', 'integer', Rule::exists('mail_settings', 'id')],
            'track_opens' => ['boolean'],
            'track_clicks' => ['boolean'],
        ]);
    }
}
