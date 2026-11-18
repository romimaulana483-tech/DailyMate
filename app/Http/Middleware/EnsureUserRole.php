<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Restrict a route group to one or more roles and block inactive accounts.
     * Usage: ->middleware('role:admin') or ->middleware('role:user')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // An account can be deactivated while a session is still open.
        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been deactivated. Please contact the administrator.']);
        }

        if (! in_array($user->role, $roles, true)) {
            return $user->isAdmin()
                ? redirect()->route('admin.dashboard')->with('error', 'You do not have access to that page.')
                : redirect()->route('user.dashboard')->with('error', 'You do not have access to that page.');
        }

        return $next($request);
    }
}
