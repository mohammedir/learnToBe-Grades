<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** الطالب يغيّر كلمة المرور المؤقتة عند أول دخول قبل أن يرى درجاته */
class StudentPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = Auth::guard('student')->user();

        if (! $student || ! $student->is_active) {
            Auth::guard('student')->logout();

            return redirect()->route('student.login');
        }

        // الحساب موقوف من الإدارة: تظهر رسالة الإيقاف وسببه بدل أي صفحة
        if ($student->isSuspended()) {
            return response()->view('student.suspended', ['student' => $student], 403);
        }

        if ($student->must_change_password) {
            return redirect()->route('student.password')->with('warning', 'لأمان حسابك، اختر كلمة مرور جديدة خاصة بك قبل المتابعة.');
        }

        return $next($request);
    }
}
