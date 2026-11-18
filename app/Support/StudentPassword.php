<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Student;

/**
 * كلمة المرور الموحدة لأول دخول للطلاب (الافتراضية 123456789).
 * كل طالب له رقم هوية يدخل بها أول مرة، ثم يُجبر على تغييرها.
 */
class StudentPassword
{
    public const FALLBACK = '123456789';

    public static function default(): string
    {
        return (string) (Setting::get('student_default_password') ?: self::FALLBACK);
    }

    /** إعادة الطالب إلى كلمة المرور الموحدة مع إجباره على تغييرها عند الدخول */
    public static function reset(Student $student): void
    {
        $p = self::default();
        $student->forceFill([
            'password' => $p,
            'initial_password' => $p,
            'must_change_password' => true,
            'password_issued_at' => now(),
        ])->save();
    }
}
