<?php

namespace App\Support;

/**
 * أقسام لوحة الإدارة ومستويات الصلاحية.
 * none = لا يرى القسم | view = مشاهدة فقط | edit = مشاهدة وتعديل
 */
class Permissions
{
    public const LEVELS = ['none' => 'بدون', 'view' => 'مشاهدة فقط', 'edit' => 'مشاهدة وتعديل'];

    /** القسم => [الاسم، ماذا تعني المشاهدة، ماذا يعني التعديل، المسار الرئيسي] */
    public const MODULES = [
        'marks' => ['متابعة الرصد والدرجات', 'يرى نسبة الرصد وكل الكشوف وينزّلها Excel', 'يعدّل الدرجات المرصودة', 'admin.dashboard'],
        'approvals' => ['اعتماد الدرجات', 'يرى ما اعتُمد من الدرجات', 'يعتمد الدرجات ويفك اعتمادها', 'admin.approvals.index'],
        'teachers' => ['المدرسون وتكليفاتهم', 'يرى قائمة المدرسين وشعبهم وموادهم', 'يضيف المدرسين ويعدّلهم ويغيّر كلمات مرورهم وتكليفاتهم', 'admin.teachers.index'],
        'students' => ['الطلاب', 'يرى الطلاب ويبحث فيهم', 'يضيف الطلاب ويعدّلهم وينقلهم ويستوردهم من Excel', 'admin.students.index'],
        'sections' => ['الصفوف والشعب', 'يرى الصفوف والشعب', 'يضيف الشعب ويعدّلها ويحذفها', 'admin.sections.index'],
        'subjects' => ['المواد', 'يرى المواد', 'يضيف المواد ويعدّلها ويحذفها', 'admin.subjects.index'],
        'quizzes' => ['الاختبارات الإلكترونية', 'يرى كل الاختبارات ونتائجها', 'ينشئ الاختبارات نيابة عن المدرسين ويصححها وينشر نتائجها', 'admin.quizzes.index'],
        'portal' => ['بوابة الطلاب', 'يرى حالة حسابات الطلاب ودخولهم', 'ينشئ كلمات مرور الطلاب، وينشر الدرجات، ويوقف حسابات الطلاب', 'admin.portal.index'],
        'components' => ['عناصر الدرجة', 'يرى عناصر الدرجة', 'يضيف العناصر ويغيّر درجاتها العظمى', 'admin.components.index'],
    ];

    public static function rank(string $level): int
    {
        return ['none' => 0, 'view' => 1, 'edit' => 2][$level] ?? 0;
    }

    /** تنظيف مصفوفة الصلاحيات القادمة من النموذج */
    public static function clean(array $input): array
    {
        $out = [];
        foreach (array_keys(self::MODULES) as $m) {
            $level = $input[$m] ?? 'none';
            $out[$m] = array_key_exists($level, self::LEVELS) ? $level : 'none';
        }

        return $out;
    }
}
