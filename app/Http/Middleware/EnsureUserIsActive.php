<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a user who has been deactivated since they signed in,
 * so switching an account off takes effect on their very next request.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(403, 'This account has been deactivated.');
            }

            return redirect()->route('login')
                ->withErrors(['login' => 'This account has been deactivated. Contact the administrator.']);
        }

        return $next($request);
    }
}
