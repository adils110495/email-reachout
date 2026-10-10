<?php

namespace App\Http\Requests\Sequencer;

use App\Sequencer\Support\Tz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    use PartialRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'timezone' => ['required', 'string', Rule::in(Tz::all())],
        ];
    }
}
