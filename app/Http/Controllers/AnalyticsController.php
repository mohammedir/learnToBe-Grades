<?php

namespace App\Http\Controllers;

use App\Models\AssessmentComponent;
use App\Models\GradeLevel;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\GradeAnalytics;
use Illuminate\Http\Request;

/** الرسوم البيانية للدرجات: الإدارة (كل المدرسة مع تصفية) والمدرس (كشوفه فقط) */
class AnalyticsController extends Controller
{
    public function admin(Request $request)
    {
        $f = [
            'grade' => $request->integer('grade') ?: null,
            'section' => $request->integer('section') ?: null,
            'subject' => $request->integer('subject') ?: null,
            'teacher' => $request->integer('teacher') ?: null,
        ];
        $assignments = TeachingAssignment::with(['section.gradeLevel', 'subject', 'teacher'])
            ->when($f['section'], fn ($q, $v) => $q->where('section_id', $v))
            ->when($f['grade'], fn ($q, $v) => $q->whereHas('section', fn ($s) => $s->where('grade_level_id', $v)))
            ->when($f['subject'], fn ($q, $v) => $q->where('subject_id', $v))
            ->when($f['teacher'], fn ($q, $v) => $q->where('teacher_id', $v))
            ->get();

        return $this->render($request, $assignments, [
            'mode' => 'admin', 'f' => $f,
            'grades' => GradeLevel::orderBy('sort_order')->with('sections')->get(),
            'subjects' => Subject::orderBy('sort_order')->get(),
            'teachers' => User::teachers()->orderBy('name')->get(),
            'scopeLabel' => array_filter($f) ? 'حسب التصفية' : 'مستوى المدرسة كاملة',
        ]);
    }

    public function teacher(Request $request)
    {
        $mine = $request->user()->assignments()->with(['section.gradeLevel', 'subject', 'teacher'])->get();
        $id = $request->integer('assignment');
        $assignments = $id ? $mine->where('id', $id)->values() : $mine;

        return $this->render($request, $assignments, [
            'mode' => 'teacher', 'mine' => $mine, 'assignmentId' => $id,
            'scopeLabel' => $id && $assignments->first() ? $assignments->first()->subject->name.' – '.$assignments->first()->section->full_name : 'كل كشوفي',
        ]);
    }

    private function render(Request $request, $assignments, array $extra)
    {
        $all = AssessmentComponent::active()->get();
        $chosen = array_values(array_intersect(array_map('intval', (array) $request->input('components', [])), $all->pluck('id')->all()));
        $ids = $chosen ?: $all->pluck('id')->all();

        $data = GradeAnalytics::compute($assignments, $ids);
        $rows = fn ($c) => collect($c)->map(fn ($v, $k) => ['label' => $k, 'avg' => $v['avg'], 'n' => $v['n']])->values();

        return view('analytics.show', $extra + [
            'data' => $data,
            'chartData' => [
                'bins' => array_values($data['bins']),
                'ratings' => $data['ratings'],
                'comp' => array_values(collect($data['byComponent'])->all()),
                'subject' => $rows($data['bySubject']),
                'section' => $rows($data['bySection']),
                'gender' => $data['byGender'],
            ],
            'allComponents' => $all,
            'chosen' => $chosen,
            'componentIds' => $ids,
            'sheets' => $assignments->count(),
        ]);
    }
}
