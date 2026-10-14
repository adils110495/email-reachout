<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `module:leads` on a route - the user must have that module switched on.
 * Several names (`module:bulks,verifier`) mean any one of them is enough.
 *
 * Hiding the sidebar link is only for looks; this is what actually stops a
 * typed-in URL or an AJAX call.
 */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $user = $request->user();

        foreach ($modules as $module) {
            if ($user?->can('module', $module)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have access to this module.');
    }
}
