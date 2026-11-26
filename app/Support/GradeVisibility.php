<?php

namespace App\Support;

use App\Models\AssessmentComponent;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * ما يراه الطالب من درجاته في البوابة:
 * - إظهار/إخفاء المجموع من 100 والنسبة والتقدير والمعدل.
 * - عناصر الدرجة الظاهرة (مثلاً العلامات الشهرية فقط).
 */
class GradeVisibility
{
    public static function showTotal(): bool
    {
        return Setting::get('grades_show_total', '1') !== '0';
    }

    /** معرّفات العناصر الظاهرة للطالب؛ null = كل العناصر */
    public static function componentIds(): ?array
    {
        $ids = json_decode((string) Setting::get('grades_student_components', ''), true);

        return is_array($ids) && $ids ? array_map('intval', $ids) : null;
    }

    public static function visibleComponents(Collection $all): Collection
    {
        $ids = self::componentIds();

        return $ids === null ? $all : $all->whereIn('id', $ids)->values();
    }

    public static function save(bool $showTotal, array $componentIds): void
    {
        $all = AssessmentComponent::active()->pluck('id')->all();
        $ids = array_values(array_intersect($all, array_map('intval', $componentIds)));
        Setting::put('grades_show_total', $showTotal ? '1' : '0');
        // كل العناصر محددة = لا تقييد (فتظهر العناصر الجديدة تلقائياً)
        Setting::put('grades_student_components', count($ids) && count($ids) < count($all) ? json_encode($ids) : '');
    }
}
