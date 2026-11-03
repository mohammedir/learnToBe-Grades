<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** مثال: ->middleware('perm:teachers,edit') */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $module, string $level = 'view'): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermission($module, $level)) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'لا تملك صلاحية هذا الإجراء.'], 403);
            }
            abort(403, $level === 'edit' ? 'لديك صلاحية المشاهدة فقط في هذا القسم.' : 'لا تملك صلاحية الدخول إلى هذا القسم.');
        }

        return $next($request);
    }
}
