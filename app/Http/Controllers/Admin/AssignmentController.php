<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\GradeSheet;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index(User $teacher)
    {
        abort_unless($teacher->isTeacher(), 404);

        $assignments = $teacher->assignments()->with(['section.gradeLevel', 'subject'])->get()
            ->sortBy(fn ($a) => [$a->section->gradeLevel->sort_order, $a->section->name, $a->subject->sort_order]);
        $grades = GradeLevel::orderBy('sort_order')->with(['sections' => fn ($q) => $q->withCount('students')])->get();
        $subjects = Subject::orderBy('sort_order')->orderBy('name')->get();

        // الكشوف المكلّف بها مدرس آخر، لتنبيه الإدارة قبل التبديل
        $taken = TeachingAssignment::with('teacher')->where('teacher_id', '!=', $teacher->id)->get()
            ->mapWithKeys(fn ($a) => [$a->section_id.'-'.$a->subject_id => $a->teacher->name]);

        return view('admin.teachers.assignments', [
            'teacher' => $teacher,
            'assignments' => $assignments,
            'progress' => GradeSheet::progressFor($assignments),
            'grades' => $grades,
            'subjects' => $subjects,
            'taken' => $taken,
        ]);
    }

    public function store(Request $request, User $teacher)
    {
        abort_unless($teacher->isTeacher(), 404);
        $data = $request->validate([
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
            'section_ids' => ['required', 'array', 'min:1'],
            'section_ids.*' => ['integer', 'exists:sections,id'],
            'replace' => ['nullable', 'boolean'],
        ], [
            'subject_ids.required' => 'اختر مادة واحدة على الأقل.',
            'section_ids.required' => 'اختر شعبة واحدة على الأقل.',
        ]);

        $added = 0;
        $moved = 0;
        $skipped = [];
        foreach ($data['section_ids'] as $sectionId) {
            foreach ($data['subject_ids'] as $subjectId) {
                $existing = TeachingAssignment::with(['teacher', 'section.gradeLevel', 'subject'])
                    ->where(['section_id' => $sectionId, 'subject_id' => $subjectId])->first();

                if (! $existing) {
                    TeachingAssignment::create(['teacher_id' => $teacher->id, 'section_id' => $sectionId, 'subject_id' => $subjectId]);
                    $added++;
                } elseif ((int) $existing->teacher_id !== (int) $teacher->id) {
                    if ($request->boolean('replace')) {
                        $existing->update(['teacher_id' => $teacher->id]);
                        $moved++;
                    } else {
                        $skipped[] = $existing->subject->name.' / '.$existing->section->full_name.' (مع '.$existing->teacher->name.')';
                    }
                }
            }
        }

        $msg = "تمت إضافة {$added} كشف".($moved ? " ونقل {$moved} كشف من مدرس آخر" : '').'.';
        $back = redirect()->route('admin.teachers.assignments', $teacher)->with('status', $msg);

        return $skipped ? $back->with('warning', 'لم تُضف هذه الكشوف لأنها مكلّف بها مدرس آخر: '.implode('، ', $skipped).'. فعّل خيار «انقلها لهذا المدرس» إن أردت تبديله.') : $back;
    }

    public function destroy(TeachingAssignment $assignment)
    {
        $teacher = $assignment->teacher_id;
        $assignment->delete();

        return redirect()->route('admin.teachers.assignments', $teacher)->with('status', 'تم إلغاء التكليف. الدرجات المرصودة بقيت محفوظة.');
    }
}
