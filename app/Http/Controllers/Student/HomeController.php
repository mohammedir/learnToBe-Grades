<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\StudentReport;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /** مدرسو الطالب فقط: مدرسو المواد المكلّفون بشعبته */
    public static function teachersOf($student): \Illuminate\Support\Collection
    {
        return \App\Models\TeachingAssignment::with(['teacher', 'subject'])
            ->where('section_id', $student->section_id)
            ->whereHas('teacher', fn ($q) => $q->where('is_active', true))
            ->get()
            ->groupBy('teacher_id')
            ->map(function ($rows) use ($student) {
                $t = $rows->first()->teacher;
                $subjects = $rows->pluck('subject')->sortBy('sort_order')->pluck('name');
                $canShow = $t->show_phone_to_students && $t->phone;
                $msg = "السلام عليكم أستاذ/ة {$t->name}،\nأنا الطالب/ة {$student->name} من {$student->section->full_name}، بخصوص مادة ".$subjects->first().'.';

                return [
                    'name' => $t->name,
                    'subjects' => $subjects->all(),
                    'whatsapp' => $canShow ? \App\Support\Phone::whatsapp($t->phone, $msg) : null,
                    'initials' => mb_substr(preg_replace('/^(ال)/u', '', $t->name), 0, 1),
                ];
            })
            ->sortBy(fn ($x) => $x['subjects'][0] ?? '')->values();
    }

    public function index()
    {
        $student = Auth::guard('student')->user()->load('section.gradeLevel');
        $lock = \App\Support\PortalLock::state();
        $off = fn ($f) => $lock['all'] || in_array($f, $lock['features'], true);
        $published = (bool) Setting::get('grades_published', false) && ! $off('grades');

        return view('student.home', [
            'student' => $student,
            'published' => $published,
            'title' => Setting::get('grades_title', 'نتائج الفصل الدراسي الأول'),
            'report' => $published ? new StudentReport($student) : null,
            'notes' => $off('notes') ? collect() : $student->notes()->with(['subject', 'author'])->limit(30)->get(),
            'quizzes' => $off('quizzes') ? collect() : \App\Services\StudentQuizzes::for($student),
            'teachers' => $off('teachers') ? collect() : self::teachersOf($student),
            'lock' => $lock,
            'gradesOff' => $off('grades'),
            'offLabels' => \App\Support\PortalLock::offLabels(),
        ]);
    }
}
