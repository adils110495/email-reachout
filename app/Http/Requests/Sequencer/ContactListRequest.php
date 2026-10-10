<?php

namespace App\Http\Requests\Sequencer;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A list in the API is a category. */
class ContactListRequest extends FormRequest
{
    use PartialRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $list = $this->route('list');
        $ignore = $list instanceof Category ? $list->id : null;

        return $this->partial([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($ignore)],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }
}
