<?php

namespace App\Http\Requests\Sequencer;

/**
 * API PATCH requests may send a subset of fields: every rule becomes "sometimes"
 * there. Web forms and PUT keep the full required rules.
 */
trait PartialRules
{
    /** @param  array<string, array>  $rules */
    protected function partial(array $rules): array
    {
        if (! $this->isMethod('PATCH')) {
            return $rules;
        }

        return array_map(
            fn (array $rule) => ['sometimes', ...array_values(array_filter($rule, fn ($r) => $r !== 'required'))],
            $rules
        );
    }

    /** HTML checkboxes are absent when unticked: treat that as false, but only for form posts. */
    protected function booleansFromForm(array $keys): void
    {
        if ($this->isJson() || $this->expectsJson()) {
            return;
        }

        $this->merge(collect($keys)->mapWithKeys(fn ($k) => [$k => $this->boolean($k)])->all());
    }
}
