<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\StudentQuizzes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** الطالب يحل اختبارات شعبته */
class QuizController extends Controller
{
    public function show(Quiz $quiz)
    {
        $student = $this->student();
        $this->allowed($quiz);
        $quiz->load(['assignment.subject', 'questions']);
        $attempt = QuizAttempt::where(['quiz_id' => $quiz->id, 'student_id' => $student->id])->first();

        // انتهى الوقت ولم يسلّم: يُسلَّم تلقائياً بما حفظه (لا شيء)
        if ($attempt && ! $attempt->submitted_at && ($d = $attempt->deadline()) && now()->gt($d->copy()->addMinutes(2))) {
            $attempt = StudentQuizzes::grade($attempt->setRelation('quiz', $quiz), []);
        }

        return view('student.quiz', ['quiz' => $quiz, 'attempt' => $attempt, 'state' => $quiz->state(), 'student' => $student]);
    }

    public function start(Quiz $quiz)
    {
        $student = $this->student();
        $this->allowed($quiz);
        if ($quiz->state() !== 'open') {
            return back()->with('warning', 'هذا الاختبار غير متاح الآن.');
        }
        QuizAttempt::firstOrCreate(['quiz_id' => $quiz->id, 'student_id' => $student->id], ['started_at' => now(), 'max_score' => $quiz->questions()->sum('points')]);

        return redirect()->route('student.quiz', $quiz);
    }

    public function submit(Request $request, Quiz $quiz)
    {
        $student = $this->student();
        $this->allowed($quiz);
        $attempt = QuizAttempt::where(['quiz_id' => $quiz->id, 'student_id' => $student->id])->firstOrFail();
        if ($attempt->submitted_at) {
            return redirect()->route('student.quiz', $quiz)->with('warning', 'سلّمت هذا الاختبار من قبل.');
        }
        StudentQuizzes::grade($attempt->setRelation('quiz', $quiz->load('questions')), (array) $request->input('answers', []));

        return redirect()->route('student.quiz', $quiz)->with('status', 'تم تسليم الاختبار.');
    }

    private function student()
    {
        return Auth::guard('student')->user();
    }

    /** الطالب لا يفتح إلا اختبارات شعبته المنشورة */
    private function allowed(Quiz $quiz): void
    {
        $quiz->loadMissing('assignment');
        abort_unless($quiz->is_published && (int) $quiz->assignment->section_id === (int) $this->student()->section_id, 404);
    }
}
