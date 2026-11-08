<?php

namespace App\Services;

use App\Models\AssessmentComponent;
use App\Models\Mark;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Support\Collection;

/**
 * كشف درجات واحد = شعبة + مادة.
 * يجمع الطلاب وعناصر الدرجة والدرجات المرصودة في شكل جاهز للعرض والتصدير.
 */
class GradeSheet
{
    public Collection $students;

    public Collection $components;

    /** @var array<int, array<int, float>> student_id => [component_id => score] */
    public array $marks = [];

    /** @var array<int, \App\Models\MarkApproval> العناصر المعتمدة (المقفلة) في هذا الكشف */
    public array $locked = [];

    public function __construct(public Section $section, public Subject $subject)
    {
        $this->students = $section->orderedStudents();
        $this->components = AssessmentComponent::active()->get();
        $this->locked = \App\Models\MarkApproval::forSheet($section->id, $subject->id);

        Mark::query()
            ->where('subject_id', $subject->id)
            ->whereIn('student_id', $this->students->pluck('id'))
            ->get(['student_id', 'assessment_component_id', 'score'])
            ->each(function (Mark $m) {
                $this->marks[$m->student_id][$m->assessment_component_id] = $m->score;
            });
    }

    public function isLocked(int $componentId): bool
    {
        return isset($this->locked[$componentId]);
    }

    public function fullyApproved(): bool
    {
        return $this->components->isNotEmpty() && $this->components->every(fn ($c) => isset($this->locked[$c->id]));
    }

    public function maxTotal(): float
    {
        return (float) $this->components->sum('max_score');
    }

    public function score(int $studentId, int $componentId): ?float
    {
        return $this->marks[$studentId][$componentId] ?? null;
    }

    public function total(int $studentId): ?float
    {
        $row = $this->marks[$studentId] ?? [];
        $values = $this->components->map(fn ($c) => $row[$c->id] ?? null)->filter(fn ($v) => $v !== null);

        return $values->isEmpty() ? null : (float) $values->sum();
    }

    public function isComplete(int $studentId): bool
    {
        $row = $this->marks[$studentId] ?? [];

        return $this->components->isNotEmpty()
            && $this->components->every(fn ($c) => array_key_exists($c->id, $row));
    }

    public function completedCount(): int
    {
        return $this->students->filter(fn ($s) => $this->isComplete($s->id))->count();
    }

    /**
     * ملخص الرصد لكل التكليفات (للوحة الإدارة وصفحة المدرس) باستعلامين فقط.
     *
     * @return array<int, array{students:int, complete:int, any:bool}> keyed by assignment id
     */
    public static function progressFor(Collection $assignments, ?array $onlyComponents = null): array
    {
        $componentIds = AssessmentComponent::active()->pluck('id');
        if ($onlyComponents) {
            $componentIds = $componentIds->intersect(array_map('intval', $onlyComponents))->values();
        }
        $needed = $componentIds->count();
        $out = [];
        $approved = \App\Models\MarkApproval::whereIn('assessment_component_id', $componentIds)
            ->whereIn('section_id', $assignments->pluck('section_id')->unique())
            ->selectRaw('section_id, subject_id, COUNT(*) n')->groupBy('section_id', 'subject_id')->get()
            ->mapWithKeys(fn ($r) => [$r->section_id.'-'.$r->subject_id => (int) $r->n]);

        foreach ($assignments as $a) {
            $studentIds = $a->section->students()->pluck('students.id');
            $counts = $needed === 0 || $studentIds->isEmpty() ? collect() : Mark::query()
                ->where('subject_id', $a->subject_id)
                ->whereIn('student_id', $studentIds)
                ->whereIn('assessment_component_id', $componentIds)
                ->selectRaw('student_id, COUNT(*) as n')
                ->groupBy('student_id')
                ->pluck('n', 'student_id');

            $out[$a->id] = [
                'students' => $studentIds->count(),
                'complete' => $counts->filter(fn ($n) => (int) $n >= $needed)->count(),
                'any' => $counts->isNotEmpty(),
                'approved' => $approved[$a->section_id.'-'.$a->subject_id] ?? 0,
                'components' => $needed,
                'filled' => (int) $counts->sum(),
                'expected' => $studentIds->count() * $needed,
            ];
        }

        return $out;
    }

    /** هل يملك هذا المستخدم حق رصد هذا الكشف؟ */
    public static function teacherOwns(int $userId, int $sectionId, int $subjectId): bool
    {
        return TeachingAssignment::where([
            'teacher_id' => $userId, 'section_id' => $sectionId, 'subject_id' => $subjectId,
        ])->exists();
    }
}
