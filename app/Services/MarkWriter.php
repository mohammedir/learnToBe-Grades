<?php

namespace App\Services;

use App\Models\AssessmentComponent;
use App\Models\Mark;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** حفظ خانة درجة واحدة في كشف (يستخدمه المدرس والإدارة) */
class MarkWriter
{
    public function save(Request $request, TeachingAssignment $assignment, User $by): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'component_id' => ['required', 'integer'],
            'score' => ['nullable', 'numeric', 'min:0'],
        ]);

        $student = Student::where('id', $data['student_id'])
            ->where('section_id', $assignment->section_id)->where('is_active', true)->first();
        $component = AssessmentComponent::where('id', $data['component_id'])->where('is_active', true)->first();
        if (! $student || ! $component) {
            return response()->json(['ok' => false, 'message' => 'الطالب أو العنصر غير موجود في هذا الكشف.'], 422);
        }
        if (\App\Models\MarkApproval::isLocked($assignment->section_id, $assignment->subject_id, $component->id)) {
            return response()->json(['ok' => false, 'locked' => true, 'message' => "درجات «{$component->name}» في هذا الكشف معتمدة من الإدارة، ولا يمكن تعديلها إلا بعد فك الاعتماد."], 423);
        }
        if ($data['score'] !== null && $data['score'] > $component->max_score) {
            return response()->json(['ok' => false, 'message' => "الدرجة يجب ألا تزيد على {$component->max_score}."], 422);
        }

        $key = ['student_id' => $student->id, 'subject_id' => $assignment->subject_id, 'assessment_component_id' => $component->id];
        if ($data['score'] === null) {
            Mark::where($key)->delete();
        } else {
            Mark::updateOrCreate($key, ['score' => $data['score'], 'entered_by' => $by->id]);
        }

        $assignment->loadMissing(['section', 'subject']);
        $sheet = new GradeSheet($assignment->section, $assignment->subject);

        return response()->json([
            'ok' => true,
            'total' => $sheet->total($student->id),
            'complete' => $sheet->isComplete($student->id),
            'completed_count' => $sheet->completedCount(),
            'saved_at' => now()->format('H:i'),
        ]);
    }
}
