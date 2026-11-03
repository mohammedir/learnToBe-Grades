<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\GradeSheet;

class DashboardController extends Controller
{
    public function index()
    {
        $sections = Section::ordered()->with('gradeLevel')->withCount('students')->get();
        $subjects = Subject::orderBy('sort_order')->orderBy('name')->get();
        $assignments = TeachingAssignment::with(['teacher', 'section', 'subject'])->get();
        $progress = GradeSheet::progressFor($assignments);

        // شبكة: شعبة × مادة => التكليف
        $grid = [];
        foreach ($assignments as $a) {
            $grid[$a->section_id][$a->subject_id] = $a;
        }

        $totals = ['students' => 0, 'complete' => 0];
        foreach ($progress as $p) {
            $totals['students'] += $p['students'];
            $totals['complete'] += $p['complete'];
        }

        // المواد التي لها تكليف واحد على الأقل فقط تظهر أعمدةً
        $usedSubjects = $subjects->filter(fn ($s) => $assignments->contains('subject_id', $s->id));

        return view('admin.dashboard', [
            'sections' => $sections,
            'subjects' => $usedSubjects,
            'grid' => $grid,
            'progress' => $progress,
            'stats' => [
                'teachers' => User::teachers()->count(),
                'students' => Student::where('is_active', true)->count(),
                'sections' => $sections->count(),
                'assignments' => $assignments->count(),
                'started' => collect($progress)->where('any', true)->count(),
                'percent' => $totals['students'] ? round($totals['complete'] / $totals['students'] * 100) : 0,
            ],
        ]);
    }
}
