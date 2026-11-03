<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** مثال: ->middleware('role:admin,staff') */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            Auth::logout();

            return redirect()->route('login')->withErrors(['username' => 'الحساب غير مفعّل. راجع إدارة المدرسة.']);
        }

        if (! in_array($user->role, $roles, true)) {
            return redirect($user->homeUrl());
        }

        return $next($request);
    }
}
