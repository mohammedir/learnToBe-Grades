<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\StudentReport;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function index()
    {
        $student = Auth::guard('student')->user()->load('section.gradeLevel');
        $published = (bool) Setting::get('grades_published', false);

        return view('student.home', [
            'student' => $student,
            'published' => $published,
            'title' => Setting::get('grades_title', 'نتائج الفصل الدراسي الأول'),
            'report' => $published ? new StudentReport($student) : null,
            'notes' => $student->notes()->with(['subject', 'author'])->limit(30)->get(),
            'quizzes' => \App\Services\StudentQuizzes::for($student),
        ]);
    }
}
