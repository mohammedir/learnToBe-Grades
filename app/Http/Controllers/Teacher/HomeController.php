<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\GradeSheet;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        // المدرس يرى تكليفاته فقط
        $assignments = $request->user()->assignments()->with(['section.gradeLevel', 'subject'])->get()
            ->sortBy(fn ($a) => [$a->section->gradeLevel->sort_order, $a->section->name, $a->subject->sort_order]);

        return view('teacher.home', [
            'assignments' => $assignments,
            'progress' => GradeSheet::progressFor($assignments),
        ]);
    }
}
