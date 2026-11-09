<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeachingAssignment;
use App\Services\GradeSheet;
use App\Services\MarkWriter;
use App\Services\MarksExporter;
use Illuminate\Http\Request;

class MarkController extends Controller
{
    /** الإدارة تطّلع على أي كشف، ومن يملك صلاحية التعديل يعدّل الدرجات */
    public function show(TeachingAssignment $assignment)
    {
        $assignment->load(['teacher', 'section.gradeLevel', 'subject']);
        $sheet = new GradeSheet($assignment->section, $assignment->subject);

        return view('admin.marks.show', compact('assignment', 'sheet'));
    }

    public function saveMark(Request $request, TeachingAssignment $assignment, MarkWriter $writer)
    {
        return $writer->save($request, $assignment, $request->user());
    }

    public function export(TeachingAssignment $assignment, MarksExporter $exporter)
    {
        $assignment->load(['section.gradeLevel', 'subject']);

        return $exporter->single($assignment->section, $assignment->subject);
    }

    /** كل كشوف مدرس واحد في ملف Excel، فوق كل كشف ترويسة الصف والمادة والمدرس */
    public function exportTeacher(\App\Models\User $teacher, MarksExporter $exporter)
    {
        abort_unless($teacher->role === 'teacher' || $teacher->assignments()->exists(), 404);

        return $exporter->teacher($teacher);
    }

    public function exportAll(MarksExporter $exporter)
    {
        return $exporter->all();
    }
}
