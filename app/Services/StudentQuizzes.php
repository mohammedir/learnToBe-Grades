<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use Illuminate\Support\Collection;

class StudentQuizzes
{
    /** الاختبارات المنشورة لشعبة الطالب مع محاولته */
    public static function for(Student $student): Collection
    {
        $quizzes = Quiz::with(['assignment.subject', 'questions'])
            ->where('is_published', true)
            ->whereHas('assignment', fn ($q) => $q->where('section_id', $student->section_id))
            ->orderByRaw('CASE WHEN closes_at IS NULL THEN 1 ELSE 0 END')->orderBy('closes_at')->latest('id')->get();
        $attempts = QuizAttempt::where('student_id', $student->id)->whereIn('quiz_id', $quizzes->pluck('id'))->get()->keyBy('quiz_id');

        return $quizzes->map(fn (Quiz $q) => ['quiz' => $q, 'attempt' => $attempts[$q->id] ?? null, 'state' => $q->state()]);
    }

    /** تصحيح محاولة وحفظ الدرجة */
    public static function grade(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        return QuizGrader::grade($attempt, $answers);
    }
}
