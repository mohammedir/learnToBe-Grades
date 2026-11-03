<?php

namespace App\Services;

use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\TeachingAssignment;
use Illuminate\Support\Facades\DB;

/**
 * تقسيم الشعب حسب نظام المدرسة:
 * - البستان والتمهيدي ومن الأول إلى الخامس: شعبة مختلطة.
 * - من السادس فما فوق: شعبة إناث (صباحي) وشعبة ذكور (مسائي).
 */
class SectionSplitter
{
    public const MIXED_GRADES = ['بستان', 'تمهيدي', 'الصف الأول', 'الصف الثاني', 'الصف الثالث', 'الصف الرابع', 'الصف الخامس'];

    public const FEMALE = ['name' => 'إناث', 'gender' => 'female', 'period' => 'صباحي'];

    public const MALE = ['name' => 'ذكور', 'gender' => 'male', 'period' => 'مسائي'];

    public array $log = [];

    public function apply(): void
    {
        DB::transaction(function () {
            foreach (GradeLevel::orderBy('sort_order')->get() as $grade) {
                in_array($grade->name, self::MIXED_GRADES, true) ? $this->makeMixed($grade) : $this->makeSplit($grade);
            }
        });
    }

    /** الشعبة المناسبة لطالب جديد في هذا الصف */
    public static function sectionFor(GradeLevel $grade, string $gender): Section
    {
        if ($grade->split_by_gender) {
            $def = $gender === 'female' ? self::FEMALE : self::MALE;

            return Section::firstOrCreate(['grade_level_id' => $grade->id, 'gender' => $def['gender']],
                ['name' => $def['name'], 'period' => $def['period']]);
        }

        return Section::where('grade_level_id', $grade->id)->whereNull('gender')->orderBy('name')->first()
            ?? Section::create(['grade_level_id' => $grade->id, 'name' => '1']);
    }

    private function makeMixed(GradeLevel $grade): void
    {
        $grade->update(['split_by_gender' => false]);
        if (! $grade->sections()->exists()) {
            $grade->sections()->create(['name' => '1']);
        }
        $this->log[] = "{$grade->name}: مختلط";
    }

    private function makeSplit(GradeLevel $grade): void
    {
        $grade->update(['split_by_gender' => true]);

        $targets = [];
        foreach ([self::FEMALE, self::MALE] as $def) {
            $targets[$def['gender']] = Section::updateOrCreate(
                ['grade_level_id' => $grade->id, 'gender' => $def['gender']],
                ['name' => $def['name'], 'period' => $def['period']]
            );
        }

        // الشعب القديمة غير المقسومة في هذا الصف (مثل «شعبة 1»)
        $old = Section::where('grade_level_id', $grade->id)->whereNull('gender')->get();
        $moved = 0;
        foreach ($old as $section) {
            // نقل الطلاب حسب الجنس
            foreach (['female', 'male'] as $g) {
                $moved += \App\Models\Student::where('section_id', $section->id)->where('gender', $g)
                    ->update(['section_id' => $targets[$g]->id]);
            }
            // تكليفات المدرسين تُنسخ للشعبتين الجديدتين بنفس المدرس
            foreach (TeachingAssignment::where('section_id', $section->id)->get() as $a) {
                foreach ($targets as $target) {
                    TeachingAssignment::firstOrCreate(
                        ['section_id' => $target->id, 'subject_id' => $a->subject_id],
                        ['teacher_id' => $a->teacher_id]
                    );
                }
                $a->delete();
            }
            if (! \App\Models\Student::where('section_id', $section->id)->exists()) {
                $section->delete();
            }
        }

        $f = $targets['female']->students()->count();
        $m = $targets['male']->students()->count();
        $this->log[] = "{$grade->name}: إناث صباحي {$f} · ذكور مسائي {$m}".($moved ? " (نُقل {$moved} طالب)" : '');
    }
}
