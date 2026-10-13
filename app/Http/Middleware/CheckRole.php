<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'Your account has been deactivated.']);
        }

        // Expand any comma or pipe separated roles
        $allowed = [];
        foreach ($roles as $role) {
            foreach (explode('|', $role) as $r) {
                $allowed[] = trim($r);
            }
        }

        if (! in_array($user->role, $allowed, true)) {
            abort(403, 'Unauthorized. Your role does not have permission to access this financial resource.');
        }

        return $next($request);
    }
}
