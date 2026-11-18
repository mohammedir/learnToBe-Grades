<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show()
    {
        return view('student.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'national_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [], ['national_id' => 'رقم الهوية', 'password' => 'كلمة المرور']);

        $nid = preg_replace('/\D/', '', strtr($data['national_id'], ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));
        $key = 'student|'.$nid.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['national_id' => 'محاولات كثيرة. حاول بعد '.RateLimiter::availableIn($key).' ثانية.']);
        }

        // أول دخول: طالب بلا كلمة مرور يدخل بالكلمة الموحدة ثم يُجبر على تغييرها
        if ($data['password'] === \App\Support\StudentPassword::default()) {
            $fresh = \App\Models\Student::where('national_id', $nid)->where('is_active', true)->whereNull('password')->first();
            if ($fresh) {
                \App\Support\StudentPassword::reset($fresh);
            }
        }

        if (! Auth::guard('student')->attempt(['national_id' => $nid, 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            // كلمة صحيحة لكن الطالب غير منتظم (متوقف/منسحب/منقول): رسالة واضحة دون كشف شيء لغيره
            $off = \App\Models\Student::where('national_id', $nid)->where('is_active', false)->whereNotNull('password')->first();
            if ($off && \Illuminate\Support\Facades\Hash::check($data['password'], $off->password)) {
                throw ValidationException::withMessages(['national_id' => 'حسابك غير مفعّل لأن حالتك في المدرسة «'.$off->enrollment_label.'». إن عدت للدراسة تواصل مع إدارة المدرسة لإعادة تفعيله.']);
            }
            throw ValidationException::withMessages(['national_id' => 'رقم الهوية أو كلمة المرور غير صحيحة. في أول دخول استخدم كلمة المرور الموحدة التي أعلنتها المدرسة، وإن نسيت كلمتك تواصل مع الإدارة.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        Auth::guard('student')->user()->forceFill(['last_login_at' => now()])->save();

        cookie()->queue('portal', 'student', 60 * 24 * 365);

        return redirect()->route('student.home');
    }

    public function logout(Request $request)
    {
        Auth::guard('student')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('student.login');
    }

    public function editPassword()
    {
        return view('student.password', ['student' => Auth::guard('student')->user()]);
    }

    public function updatePassword(Request $request)
    {
        $student = Auth::guard('student')->user();
        $request->validate([
            'current_password' => ['required', 'current_password:student'],
            'password' => ['required', 'confirmed', Password::min(6), 'different:current_password', \Illuminate\Validation\Rule::notIn([\App\Support\StudentPassword::default()])],
        ], [
            'password.different' => 'اختر كلمة مرور مختلفة عن الحالية.',
            'password.not_in' => 'لا يمكن استخدام كلمة المرور الموحدة. اختر كلمة خاصة بك.',
        ], ['current_password' => 'كلمة المرور الحالية', 'password' => 'كلمة المرور الجديدة']);

        // كلمة الطالب الخاصة لا تُحفظ إلا مشفّرة، فلا تراها الإدارة
        $student->forceFill(['password' => $request->password, 'initial_password' => null, 'must_change_password' => false])->save();

        return redirect()->route('student.home')->with('status', 'تم حفظ كلمة المرور الجديدة.');
    }
}
