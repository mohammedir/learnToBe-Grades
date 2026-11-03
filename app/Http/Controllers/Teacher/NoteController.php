<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\TeachingAssignment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** ملاحظات المدرس لطلاب كشفه (تظهر للطالب في بوابته) */
class NoteController extends Controller
{
    public function index(Request $request, TeachingAssignment $assignment)
    {
        $this->owns($request, $assignment);
        $assignment->load(['section.gradeLevel', 'subject']);
        $notes = StudentNote::with('student')
            ->where('subject_id', $assignment->subject_id)
            ->whereIn('student_id', $assignment->section->students()->pluck('students.id'))
            ->latest()->get();

        return view('teacher.notes', ['assignment' => $assignment, 'notes' => $notes, 'students' => $assignment->section->orderedStudents()]);
    }

    public function store(Request $request, TeachingAssignment $assignment)
    {
        $this->owns($request, $assignment);
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(array_keys(StudentNote::TYPES))],
            'body' => ['required', 'string', 'min:3', 'max:1000'],
        ], [], ['student_id' => 'الطالب', 'type' => 'نوع الملاحظة', 'body' => 'نص الملاحظة']);

        $student = Student::where('id', $data['student_id'])->where('section_id', $assignment->section_id)->firstOrFail();
        StudentNote::create([
            'student_id' => $student->id,
            'subject_id' => $assignment->subject_id,
            'author_id' => $request->user()->id,
            'type' => $data['type'],
            'body' => trim($data['body']),
        ]);

        return back()->with('status', "أُرسلت الملاحظة إلى {$student->name}، وتظهر الآن في بوابته.");
    }

    public function destroy(Request $request, StudentNote $note)
    {
        abort_unless((int) $note->author_id === (int) $request->user()->id, 403, 'تستطيع حذف ملاحظاتك فقط.');
        $note->delete();

        return back()->with('status', 'حُذفت الملاحظة من بوابة الطالب.');
    }

    private function owns(Request $request, TeachingAssignment $assignment): void
    {
        abort_unless((int) $assignment->teacher_id === (int) $request->user()->id, 403, 'هذا الكشف غير مكلّف به.');
    }
}
