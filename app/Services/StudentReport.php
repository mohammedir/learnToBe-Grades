<?php

namespace App\Services;

use App\Models\AssessmentComponent;
use App\Models\Mark;
use App\Models\Student;
use App\Models\TeachingAssignment;
use Illuminate\Support\Collection;

/** كشف درجات طالب واحد في كل مواده، مع النسبة والتقدير */
class StudentReport
{
    public Collection $components;

    /** @var array<int, array{subject:string, scores:array, total:?float, max:float, complete:bool, percent:?float, rating:?string}> */
    public array $subjects = [];

    /**
     * @param bool $detailed للإدارة: يضيف المدرس ومن رصد آخر درجة ووقتها، ومواد لها درجات من شعبة سابقة
     */
    public function __construct(public Student $student, bool $detailed = false)
    {
        $this->components = AssessmentComponent::active()->get();
        $max = (float) $this->components->sum('max_score');

        $assignments = TeachingAssignment::with(['subject', 'teacher'])->where('section_id', $student->section_id)->get()
            ->sortBy(fn ($a) => [$a->subject->sort_order, $a->subject->name]);

        $rawMarks = Mark::with($detailed ? ['enteredBy'] : [])->where('student_id', $student->id)->get();
        $marks = $rawMarks->groupBy('subject_id')->map(fn ($g) => $g->pluck('score', 'assessment_component_id'));

        if ($detailed) {
            // مواد لها درجات محفوظة لكنها ليست في شعبة الطالب الحالية (نُقل من شعبة أخرى)
            $missing = $rawMarks->pluck('subject_id')->unique()->diff($assignments->pluck('subject_id'));
            foreach (\App\Models\Subject::whereIn('id', $missing)->get() as $sub) {
                $assignments->push((new TeachingAssignment(['subject_id' => $sub->id]))->setRelation('subject', $sub)->setRelation('teacher', null));
            }
        }

        foreach ($assignments as $a) {
            $row = $marks[$a->subject_id] ?? collect();
            $scores = $this->components->mapWithKeys(fn ($c) => [$c->id => $row[$c->id] ?? null])->all();
            $entered = array_filter($scores, fn ($v) => $v !== null);
            $complete = $this->components->isNotEmpty() && count($entered) === $this->components->count();
            $total = $entered ? (float) array_sum($entered) : null;
            $percent = $complete && $max > 0 ? round($total / $max * 100, 1) : null;

            $this->subjects[] = [
                'subject' => $a->subject->name,
                'scores' => $scores,
                'total' => $total,
                'max' => $max,
                'complete' => $complete,
                'percent' => $percent,
                'rating' => $percent === null ? null : self::rating($percent),
            ] + ($detailed ? [
                'subject_id' => $a->subject_id,
                'teacher' => $a->teacher?->name,
                'current' => (bool) $a->exists,
                'last' => ($l = $rawMarks->where('subject_id', $a->subject_id)->sortByDesc('updated_at')->first())
                    ? ['at' => $l->updated_at, 'by' => $l->enteredBy?->name] : null,
            ] : []);
        }
    }

    /** المعدل = متوسط نسب المواد المكتملة فقط */
    public function average(): ?float
    {
        $p = array_filter(array_column($this->subjects, 'percent'), fn ($v) => $v !== null);

        return $p ? round(array_sum($p) / count($p), 1) : null;
    }

    public function completeCount(): int
    {
        return count(array_filter($this->subjects, fn ($s) => $s['complete']));
    }

    /** سلّم التقديرات المعتمد في المدارس الفلسطينية */
    public static function rating(float $percent): string
    {
        return match (true) {
            $percent >= 90 => 'ممتاز',
            $percent >= 80 => 'جيد جداً',
            $percent >= 65 => 'جيد',
            $percent >= 50 => 'مقبول',
            default => 'يحتاج إلى متابعة',
        };
    }

    /** لون التقدير في واجهة الطالب */
    public static function tone(?string $rating): string
    {
        return match ($rating) {
            'ممتاز' => 'excellent',
            'جيد جداً' => 'vgood',
            'جيد' => 'good',
            'مقبول' => 'pass',
            'يحتاج إلى متابعة' => 'weak',
            default => 'pending',
        };
    }

    /** أعلى مادة مكتملة */
    public function best(): ?array
    {
        $done = array_filter($this->subjects, fn ($s) => $s['percent'] !== null);
        usort($done, fn ($a, $b) => $b['percent'] <=> $a['percent']);

        return $done[0] ?? null;
    }

    /** مواد مكتملة نسبتها أقل من 65% (تحتاج اهتماماً) */
    public function needsAttention(): array
    {
        return array_values(array_filter($this->subjects, fn ($s) => $s['percent'] !== null && $s['percent'] < 65));
    }

    public static function ratingClass(?string $rating): string
    {
        return match ($rating) {
            'ممتاز', 'جيد جداً' => 'done',
            'جيد', 'مقبول' => 'part',
            'يحتاج إلى متابعة' => 'off',
            default => 'none',
        };
    }
}
