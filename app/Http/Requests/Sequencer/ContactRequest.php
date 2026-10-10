<?php

namespace App\Http\Requests\Sequencer;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;

/** A contact in the API is a lead: this validates the lead fields the API may set. */
class ContactRequest extends FormRequest
{
    use PartialRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }

        // "company" is accepted as an alias of company_name.
        if ($this->has('company') && ! $this->has('company_name')) {
            $this->merge(['company_name' => $this->input('company')]);
        }
    }

    public function rules(): array
    {
        return $this->partial([
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'country' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'custom_fields' => ['nullable', 'array', 'max:50'],
            'custom_fields.*' => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);
    }

    /** One lead per address: an existing lead holding this email blocks a second one. */
    public function after(): array
    {
        return [
            function ($validator) {
                $email = $this->input('email');
                if (! $email || $validator->errors()->has('email')) {
                    return;
                }

                $current = $this->route('contact');
                $exists = Lead::holdingAddress($email)
                    ->when($current instanceof Lead, fn ($q) => $q->whereKeyNot($current->id))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('email', 'A lead with this email already exists.');
                }
            },
        ];
    }
}
