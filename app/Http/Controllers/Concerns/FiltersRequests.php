<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Query-string filter helpers shared by the list screens.
 *
 * Every filter arrives from a URL the user can edit, so `?status[]=x` would
 * hand a controller an array where it expected a string - enough to turn a
 * type-juggling comparison into a TypeError. These helpers normalise a
 * parameter to a scalar before anything compares or interpolates it.
 */
trait FiltersRequests
{
    /**
     * A request parameter as a trimmed string; '' for anything non-scalar.
     *
     * Reads through input() rather than query() so the same helper serves the
     * GET filter bars and the POST forms (Clear history) that carry a filter.
     */
    protected function strParam(Request $request, string $key, string $default = ''): string
    {
        $value = $request->input($key, $default);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** A query parameter constrained to a whitelist; '' when it is not on it. */
    protected function enumParam(Request $request, string $key, array $allowed): string
    {
        $value = $this->strParam($request, $key);

        return in_array($value, $allowed, true) ? $value : '';
    }

    /** A positive integer query parameter, or null. */
    protected function intParam(Request $request, string $key): ?int
    {
        $value = $this->strParam($request, $key);

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * A LIKE pattern with the wildcards the user typed escaped, so a search for
     * "50%" does not match everything.
     */
    protected function likePattern(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }

    /** Rows per page, constrained to the sizes the UI offers. */
    protected function perPage(Request $request, int $default = 25): int
    {
        $value = (int) $this->strParam($request, 'per_page');

        return in_array($value, [10, 25, 50, 100], true) ? $value : $default;
    }
}
