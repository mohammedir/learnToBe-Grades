<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TeachingAssignment;
use App\Services\GradeSheet;
use App\Services\MarkWriter;
use App\Services\MarksExporter;
use Illuminate\Http\Request;

class SheetController extends Controller
{
    public function show(Request $request, TeachingAssignment $assignment)
    {
        $this->authorizeOwner($request, $assignment);
        $assignment->load(['section.gradeLevel', 'subject']);

        return view('teacher.sheet', [
            'assignment' => $assignment,
            'sheet' => new GradeSheet($assignment->section, $assignment->subject),
        ]);
    }

    /** حفظ خانة واحدة (يُستدعى تلقائياً من الصفحة عند كتابة الدرجة) */
    public function saveMark(Request $request, TeachingAssignment $assignment, MarkWriter $writer)
    {
        $this->authorizeOwner($request, $assignment);

        return $writer->save($request, $assignment, $request->user());
    }

    public function export(Request $request, TeachingAssignment $assignment, MarksExporter $exporter)
    {
        $this->authorizeOwner($request, $assignment);
        $assignment->load(['section.gradeLevel', 'subject']);

        return $exporter->single($assignment->section, $assignment->subject);
    }

    /** المدرس لا يفتح إلا كشوفه هو */
    private function authorizeOwner(Request $request, TeachingAssignment $assignment): void
    {
        abort_unless((int) $assignment->teacher_id === (int) $request->user()->id, 403, 'هذا الكشف غير مكلّف به.');
    }
}
