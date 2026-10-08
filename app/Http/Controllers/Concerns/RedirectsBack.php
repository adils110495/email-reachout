<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Keeps a list's filters across a row action.
 *
 * Every settings list (Email Templates, Platforms, Categories, Addresses)
 * filters by status through the query string, so add / edit / delete must send
 * the user back to the same filtered list rather than the top of an unfiltered
 * one. The forms carry the current query string in a _redirect_back hidden
 * input; this turns it back into route parameters. Same contract as Leads.
 */
trait RedirectsBack
{
    /**
     * @return array<string, mixed>
     */
    protected function redirectQuery(Request $request): array
    {
        $query = [];

        if ($back = $request->input('_redirect_back', '')) {
            parse_str(ltrim($back, '?'), $query);
        }

        return $query;
    }
}
