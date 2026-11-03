<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentComponent;
use App\Models\GradeLevel;
use App\Models\Mark;
use App\Models\MarkApproval;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** اعتماد الدرجات وفك اعتمادها: لكل خانة (كشف × عنصر)، أو لعمود، أو لكشف، أو للكل */
class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $f = $this->filters($request);
        $assignments = $this->filtered($f)->get()
            ->sortBy(fn ($a) => [$a->section->gradeLevel->sort_order, $a->section->gender === 'female' ? 0 : 1, $a->section->name, $a->subject->sort_order])->values();
        $components = AssessmentComponent::active()->get();

        // عدد الطلاب في كل شعبة، وعدد الدرجات المرصودة لكل (شعبة، مادة، عنصر)
        $sectionIds = $assignments->pluck('section_id')->unique();
        $studentCounts = Student::where('is_active', true)->whereIn('section_id', $sectionIds)
            ->selectRaw('section_id, COUNT(*) n')->groupBy('section_id')->pluck('n', 'section_id');
        $filled = Mark::join('students', 'students.id', '=', 'marks.student_id')
            ->where('students.is_active', true)->whereIn('students.section_id', $sectionIds)
            ->selectRaw('students.section_id, marks.subject_id, marks.assessment_component_id, COUNT(*) n')
            ->groupBy('students.section_id', 'marks.subject_id', 'marks.assessment_component_id')->get()
            ->mapWithKeys(fn ($r) => ["{$r->section_id}-{$r->subject_id}-{$r->assessment_component_id}" => (int) $r->n]);
        $approvals = MarkApproval::with('approver')->whereIn('section_id', $sectionIds)->get()
            ->keyBy(fn ($a) => "{$a->section_id}-{$a->subject_id}-{$a->assessment_component_id}");

        $totalCells = $assignments->count() * $components->count();

        return view('admin.approvals.index', [
            'assignments' => $assignments,
            'components' => $components,
            'studentCounts' => $studentCounts,
            'filled' => $filled,
            'approvals' => $approvals,
            'f' => $f,
            'stats' => ['cells' => $totalCells, 'approved' => $approvals->filter(fn ($a, $k) => $assignments->contains(fn ($x) => str_starts_with($k, "{$x->section_id}-{$x->subject_id}-")))->count()],
            'grades' => GradeLevel::orderBy('sort_order')->with('sections')->get(),
            'subjects' => Subject::orderBy('sort_order')->get(),
            'teachers' => User::teachers()->orderBy('name')->get(),
        ]);
    }

    /** تطبيق الاعتماد أو فكه */
    public function apply(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,unapprove'],
            'scope' => ['required', 'in:cells,filtered'],
            'cells' => ['array'],
            'cells.*' => ['string', 'regex:/^\d+-\d+-\d+$/'],
            'component_ids' => ['array'],
            'component_ids.*' => ['integer', 'exists:assessment_components,id'],
        ]);

        // الخانات المستهدفة: [section, subject, component]
        $targets = [];
        if ($data['scope'] === 'cells') {
            foreach ($data['cells'] ?? [] as $c) {
                [$sec, $sub, $comp] = array_map('intval', explode('-', $c));
                $targets[] = [$sec, $sub, $comp];
            }
        } else {
            $components = $data['component_ids'] ?? [];
            if (! $components) {
                return back()->with('warning', 'اختر عنصر درجة واحداً على الأقل (مثل «الاختبار الشهري الأول»).');
            }
            foreach ($this->filtered($this->filters($request))->get() as $a) {
                foreach ($components as $comp) {
                    $targets[] = [(int) $a->section_id, (int) $a->subject_id, (int) $comp];
                }
            }
        }
        if (! $targets) {
            return back()->with('warning', 'لم تحدد أي خانة. اختر الخانات أو العمود أو الكشف ثم أعد المحاولة.');
        }

        $n = 0;
        DB::transaction(function () use ($targets, $data, $request, &$n) {
            foreach ($targets as [$sec, $sub, $comp]) {
                $key = ['section_id' => $sec, 'subject_id' => $sub, 'assessment_component_id' => $comp];
                if ($data['action'] === 'approve') {
                    $row = MarkApproval::firstOrCreate($key, ['approved_by' => $request->user()->id, 'approved_at' => now()]);
                    $n += $row->wasRecentlyCreated ? 1 : 0;
                } else {
                    $n += MarkApproval::where($key)->delete();
                }
            }
        });

        return back()->with('status', $data['action'] === 'approve'
            ? "اعتُمدت {$n} خانة جديدة. لا يستطيع المدرسون تعديلها حتى يُفك اعتمادها."
            : "فُك اعتماد {$n} خانة، وأصبح المدرسون يستطيعون تعديلها.");
    }

    private function filters(Request $request): array
    {
        return [
            'grade' => $request->integer('grade') ?: null,
            'section' => $request->integer('section') ?: null,
            'subject' => $request->integer('subject') ?: null,
            'teacher' => $request->integer('teacher') ?: null,
        ];
    }

    private function filtered(array $f)
    {
        return TeachingAssignment::with(['section.gradeLevel', 'subject', 'teacher'])
            ->when($f['section'], fn ($q, $v) => $q->where('section_id', $v))
            ->when($f['grade'], fn ($q, $v) => $q->whereHas('section', fn ($s) => $s->where('grade_level_id', $v)))
            ->when($f['subject'], fn ($q, $v) => $q->where('subject_id', $v))
            ->when($f['teacher'], fn ($q, $v) => $q->where('teacher_id', $v));
    }
}
