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

        // البوابة معطّلة كاملة من الإدارة
        if (\App\Support\PortalLock::closed()) {
            return response()->view('student.closed', ['lock' => \App\Support\PortalLock::state()]);
        }

        // ميزة الاختبارات معطّلة: لا يمكن فتح أي اختبار
        if ($request->routeIs('student.quiz*') && \App\Support\PortalLock::off('quizzes')) {
            return redirect()->route('student.home')->with('warning', 'الاختبارات الإلكترونية متوقفة مؤقتاً من إدارة المدرسة.');
        }

        if ($student->must_change_password) {
            return redirect()->route('student.password')->with('warning', 'لأمان حسابك، اختر كلمة مرور جديدة خاصة بك قبل المتابعة.');
        }

        return $next($request);
    }
}
