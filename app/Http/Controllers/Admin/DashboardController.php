<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentComponent;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\GradeSheet;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $allComponents = AssessmentComponent::active()->get();
        // العناصر المختارة للمتابعة (افتراضياً كلها)
        $chosen = array_values(array_intersect(array_map('intval', (array) $request->input('components', [])), $allComponents->pluck('id')->all()));
        $componentIds = $chosen ?: $allComponents->pluck('id')->all();

        $f = [
            'grade' => $request->integer('grade') ?: null,
            'subject' => $request->integer('subject') ?: null,
            'teacher' => $request->integer('teacher') ?: null,
        ];

        $sections = Section::ordered()->with('gradeLevel')->withCount('students')
            ->when($f['grade'], fn ($q, $v) => $q->where('sections.grade_level_id', $v))->get();
        $subjects = Subject::orderBy('sort_order')->orderBy('name')->get();
        $assignments = TeachingAssignment::with(['teacher', 'section', 'subject'])
            ->whereIn('section_id', $sections->pluck('id'))
            ->when($f['subject'], fn ($q, $v) => $q->where('subject_id', $v))
            ->when($f['teacher'], fn ($q, $v) => $q->where('teacher_id', $v))
            ->get();
        $progress = GradeSheet::progressFor($assignments, $componentIds);

        $grid = [];
        foreach ($assignments as $a) {
            $grid[$a->section_id][$a->subject_id] = $a;
        }

        $totals = ['students' => 0, 'complete' => 0, 'filled' => 0, 'expected' => 0];
        foreach ($progress as $p) {
            foreach ($totals as $k => $_) {
                $totals[$k] += $p[$k];
            }
        }

        // نسبة إكمال الإدخال لكل مدرس (للعناصر المختارة)
        $byTeacher = $assignments->groupBy('teacher_id')->map(function ($list) use ($progress) {
            $t = $list->first()->teacher;
            $filled = $list->sum(fn ($a) => $progress[$a->id]['filled']);
            $expected = $list->sum(fn ($a) => $progress[$a->id]['expected']);

            return [
                'teacher' => $t,
                'sheets' => $list->count(),
                'done' => $list->filter(fn ($a) => $progress[$a->id]['students'] > 0 && $progress[$a->id]['complete'] === $progress[$a->id]['students'])->count(),
                'filled' => $filled,
                'expected' => $expected,
                'percent' => $expected ? round($filled / $expected * 100) : 0,
                'missing' => max(0, $expected - $filled),
            ];
        })->sortBy('percent')->values();

        $usedSubjects = $subjects->filter(fn ($s) => $assignments->contains('subject_id', $s->id));

        return view('admin.dashboard', [
            'sections' => $sections->filter(fn ($s) => $assignments->contains('section_id', $s->id) || ! $f['subject'] && ! $f['teacher']),
            'subjects' => $usedSubjects,
            'grid' => $grid,
            'progress' => $progress,
            'byTeacher' => $byTeacher,
            'allComponents' => $allComponents,
            'componentIds' => $componentIds,
            'chosen' => $chosen,
            'f' => $f,
            'grades' => GradeLevel::orderBy('sort_order')->get(),
            'allSubjects' => $subjects,
            'teachers' => User::teachers()->orderBy('name')->get(),
            'stats' => [
                'teachers' => $byTeacher->count(),
                'students' => Student::where('is_active', true)->whereIn('section_id', $sections->pluck('id'))->count(),
                'sections' => $sections->count(),
                'assignments' => $assignments->count(),
                'started' => collect($progress)->where('any', true)->count(),
                'percent' => $totals['students'] ? round($totals['complete'] / $totals['students'] * 100) : 0,
                'cells' => $totals['expected'] ? round($totals['filled'] / $totals['expected'] * 100) : 0,
                'missing' => max(0, $totals['expected'] - $totals['filled']),
            ],
        ]);
    }
}
