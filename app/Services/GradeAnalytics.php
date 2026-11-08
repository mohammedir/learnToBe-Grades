<?php

namespace App\Services;

use App\Models\AssessmentComponent;
use App\Models\Mark;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * إحصائيات الدرجات لمجموعة كشوف وعناصر مختارة:
 * توزيع النسب (المنحنى)، التقديرات، متوسط كل مادة/شعبة/عنصر، والطلاب الذين يحتاجون متابعة.
 * تُحسب نسبة الطالب في المادة من العناصر المختارة فقط، وتدخل إذا رُصدت كل هذه العناصر له.
 */
class GradeAnalytics
{
    public static function compute(Collection $assignments, array $componentIds): array
    {
        $components = AssessmentComponent::active()->whereIn('id', $componentIds)->get()->keyBy('id');
        $max = (float) $components->sum('max_score');
        $sectionIds = $assignments->pluck('section_id')->unique();
        $students = Student::where('is_active', true)->whereIn('section_id', $sectionIds)->get(['id', 'name', 'gender', 'section_id'])->groupBy('section_id');
        $marks = $components->isEmpty() ? collect() : Mark::whereIn('subject_id', $assignments->pluck('subject_id')->unique())
            ->whereIn('assessment_component_id', $components->keys())
            ->whereIn('student_id', $students->flatten()->pluck('id'))
            ->get(['student_id', 'subject_id', 'assessment_component_id', 'score'])
            ->groupBy(fn ($m) => $m->student_id.'-'.$m->subject_id);

        $records = [];
        $compSum = array_fill_keys($components->keys()->all(), [0.0, 0]);
        $pending = 0;
        foreach ($assignments as $a) {
            foreach ($students[$a->section_id] ?? [] as $st) {
                $row = ($marks[$st->id.'-'.$a->subject_id] ?? collect())->keyBy('assessment_component_id');
                foreach ($row as $cid => $m) {
                    $compSum[$cid][0] += $m->score / max($components[$cid]->max_score, 0.0001) * 100;
                    $compSum[$cid][1]++;
                }
                if ($max <= 0 || $row->count() < $components->count()) {
                    $pending++;

                    continue;
                }
                $records[] = [
                    'percent' => round($row->sum('score') / $max * 100, 1),
                    'student' => $st->name,
                    'gender' => $st->gender,
                    'subject' => $a->subject->name,
                    'section' => $a->section->full_name,
                    'grade' => $a->section->gradeLevel->name,
                    'teacher' => $a->teacher->name ?? '',
                ];
            }
        }

        $p = collect($records)->pluck('percent')->sort()->values();
        $n = $p->count();
        $bins = array_fill(0, 10, 0);
        foreach ($p as $v) {
            $bins[min(9, (int) floor($v / 10))]++;
        }
        $ratings = ['ممتاز' => 0, 'جيد جداً' => 0, 'جيد' => 0, 'مقبول' => 0, 'يحتاج إلى متابعة' => 0];
        foreach ($p as $v) {
            $ratings[StudentReport::rating($v)]++;
        }
        $avgBy = fn (string $key) => collect($records)->groupBy($key)->map(fn ($g) => ['avg' => round($g->avg('percent'), 1), 'n' => $g->count()])->sortByDesc('avg');
        $mean = $n ? round($p->avg(), 1) : null;

        return [
            'n' => $n,
            'pending' => $pending,
            'mean' => $mean,
            'median' => $n ? round($n % 2 ? $p[intdiv($n, 2)] : ($p[intdiv($n, 2) - 1] + $p[intdiv($n, 2)]) / 2, 1) : null,
            'stdev' => $n > 1 ? round(sqrt($p->map(fn ($v) => ($v - $mean) ** 2)->sum() / ($n - 1)), 1) : null,
            'pass' => $n ? round($p->filter(fn ($v) => $v >= 50)->count() / $n * 100) : null,
            'excellent' => $n ? round($p->filter(fn ($v) => $v >= 90)->count() / $n * 100) : null,
            'max' => $p->last(),
            'min' => $p->first(),
            'bins' => $bins,
            'ratings' => $ratings,
            'bySubject' => $avgBy('subject'),
            'bySection' => $avgBy('section'),
            'byGender' => collect($records)->whereIn('gender', ['male', 'female'])->groupBy('gender')->map(fn ($g) => round($g->avg('percent'), 1)),
            'byComponent' => $components->map(fn ($c) => ['name' => $c->name, 'avg' => $compSum[$c->id][1] ? round($compSum[$c->id][0] / $compSum[$c->id][1], 1) : null, 'n' => $compSum[$c->id][1]])->values(),
            'weak' => collect($records)->filter(fn ($r) => $r['percent'] < 50)->sortBy('percent')->take(30)->values(),
            'componentsLabel' => $components->pluck('name')->implode('، '),
            'max_score' => $max,
        ];
    }
}
