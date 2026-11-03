<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GradeSheet;

/** الإدارة ترصد الدرجات نيابة عن المدرس: اختيار المدرس ثم كشوفه */
class MarkEntryController extends Controller
{
    public function index()
    {
        $teachers = User::teachers()->with(['assignments.section.gradeLevel', 'assignments.subject'])->orderBy('name')->get();
        $all = $teachers->flatMap->assignments;
        $progress = GradeSheet::progressFor($all);

        $rows = $teachers->map(function ($t) use ($progress) {
            $p = collect($t->assignments)->map(fn ($a) => $progress[$a->id]);

            return [
                'teacher' => $t,
                'sheets' => $t->assignments->count(),
                'done' => $p->filter(fn ($x) => $x['students'] > 0 && $x['complete'] === $x['students'])->count(),
                'started' => $p->where('any', true)->count(),
            ];
        });

        return view('admin.entry.index', compact('rows'));
    }

    public function teacher(User $teacher)
    {
        abort_unless($teacher->isTeacher(), 404);
        $assignments = $teacher->assignments()->with(['section.gradeLevel', 'subject'])->get()
            ->sortBy(fn ($a) => [$a->section->gradeLevel->sort_order, $a->section->name, $a->subject->sort_order]);

        return view('admin.entry.teacher', [
            'teacher' => $teacher,
            'assignments' => $assignments,
            'progress' => GradeSheet::progressFor($assignments),
        ]);
    }
}
