<?php

namespace App\Http\Requests\Sequencer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Enroll leads in a sequence: explicit lead ids and/or every lead in a category. */
class EnrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The API also accepts the generic names contact_ids / list_id.
        $this->merge([
            'lead_ids' => $this->input('lead_ids', $this->input('contact_ids')),
            'category_id' => $this->input('category_id', $this->input('list_id')),
        ]);
    }

    public function rules(): array
    {
        return [
            'lead_ids' => ['nullable', 'array', 'max:100000'],
            'lead_ids.*' => ['integer'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'mail_setting_id' => ['nullable', 'integer', Rule::exists('mail_settings', 'id')],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if (empty($this->input('lead_ids')) && empty($this->input('category_id'))) {
                    $validator->errors()->add('lead_ids', 'Choose at least one lead or a category.');
                }
            },
        ];
    }
}
