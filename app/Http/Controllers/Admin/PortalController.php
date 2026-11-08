<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use App\Services\StudentCredentials;
use Illuminate\Http\Request;

/** بوابة الطلاب: نشر الدرجات وإنشاء حسابات الدخول */
class PortalController extends Controller
{
    public function index(Request $request)
    {
        $sections = Section::ordered()->with('gradeLevel')->withCount([
            'students',
            'students as accounts_count' => fn ($q) => $q->whereNotNull('password')->whereNotNull('national_id'),
            'students as logged_count' => fn ($q) => $q->whereNotNull('last_login_at'),
            'students as no_id_count' => fn ($q) => $q->whereNull('national_id'),
        ])->get();

        $section = $request->filled('section') ? Section::with('gradeLevel')->find($request->integer('section')) : null;

        $suspended = Student::with('section.gradeLevel')->whereNotNull('suspended_at')->orderByDesc('suspended_at')->get();

        return view('admin.portal.index', [
            'suspended' => $suspended,
            'sections' => $sections,
            'section' => $section,
            'students' => $section ? $section->orderedStudents() : collect(),
            'published' => (bool) Setting::get('grades_published', false),
            'lock' => \App\Support\PortalLock::state(),
            'title' => Setting::get('grades_title', 'نتائج الفصل الدراسي الأول'),
            'totals' => [
                'students' => $sections->sum('students_count'),
                'accounts' => $sections->sum('accounts_count'),
                'logged' => $sections->sum('logged_count'),
                'no_id' => $sections->sum('no_id_count'),
            ],
        ]);
    }

    public function publish(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'published' => ['required', 'boolean'],
        ], [], ['title' => 'عنوان النتائج']);
        Setting::put('grades_title', $data['title']);
        Setting::put('grades_published', $data['published'] ? '1' : '0');

        return back()->with('status', $data['published'] ? 'تم نشر الدرجات. يرى الطلاب الآن درجاتهم عند الدخول.' : 'تم إخفاء الدرجات عن الطلاب.');
    }

    /** تعطيل البوابة كاملة أو مزايا محددة مع السبب، أو إعادة تشغيلها */
    public function lock(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', 'in:open,all,partial'],
            'features' => ['array', 'required_if:mode,partial'],
            'features.*' => ['in:'.implode(',', array_keys(\App\Support\PortalLock::FEATURES))],
            'reason' => ['nullable', 'required_unless:mode,open', 'string', 'max:500'],
            'until' => ['nullable', 'date'],
        ], [
            'features.required_if' => 'حدّد ميزة واحدة على الأقل لتعطيلها.',
            'reason.required_unless' => 'اكتب سبب التعطيل ليظهر للطلاب.',
        ], ['reason' => 'سبب التعطيل', 'until' => 'تاريخ العودة']);

        if ($data['mode'] === 'open') {
            \App\Support\PortalLock::clear();

            return back()->with('status', 'تم تشغيل بوابة الطالب بكامل مزاياها.');
        }

        \App\Support\PortalLock::save($data['mode'] === 'all', $data['features'] ?? [], $data['reason'], $data['until'] ?? null);

        return back()->with('status', $data['mode'] === 'all'
            ? 'تم تعطيل بوابة الطالب كاملة. يرى الطالب صفحة «البوابة متوقفة» مع السبب.'
            : 'تم تعطيل: '.implode('، ', \App\Support\PortalLock::offLabels()).'.');
    }

    /** إنشاء كلمات مرور لشعبة (الجدد فقط أو الكل) وتنزيلها فوراً */
    public function issueSection(Request $request, Section $section, StudentCredentials $credentials)
    {
        $mode = $request->validate(['mode' => ['required', 'in:new,all']])['mode'];
        $section->load('gradeLevel');
        $students = $section->orderedStudents()->load('section.gradeLevel');
        if ($mode === 'new') {
            $students = $students->filter(fn ($s) => ! $s->password);
        }
        $issued = $credentials->issue($students);
        if ($issued->isEmpty()) {
            return back()->with('warning', 'لا يوجد طلاب بحاجة لكلمة مرور في هذه الشعبة، أو ليس لهم رقم هوية.');
        }

        return $credentials->download($issued, $section->full_name);
    }

    /** تعيين كلمة مرور يكتبها الموظف بنفسه لطالب */
    public function setPassword(Request $request, Student $student)
    {
        if (! $student->national_id) {
            return back()->with('warning', 'لا يمكن تعيين كلمة مرور لطالب بدون رقم هوية.');
        }
        $data = $request->validate([
            'password' => ['required', 'string', 'min:6', 'max:64'],
            'must_change' => ['nullable', 'boolean'],
        ], [], ['password' => 'كلمة المرور']);

        $student->forceFill([
            'password' => $data['password'],
            'initial_password' => $data['password'],
            'must_change_password' => $request->boolean('must_change'),
            'password_issued_at' => now(),
        ])->save();

        return back()->with('status', "تم تعيين كلمة مرور جديدة للطالب {$student->name}."
            .($request->boolean('must_change') ? ' سيُطلب منه تغييرها عند الدخول.' : ''));
    }

    /** صفحة بيانات دخول الطلاب: الأسماء وأرقام الهوية وكلمات المرور */
    public function credentials(Request $request)
    {
        $students = $this->credentialsQuery($request)->get();

        return view('admin.portal.credentials', [
            'students' => $students,
            'grades' => \App\Models\GradeLevel::orderBy('sort_order')->with('sections')->get(),
            'f' => $request->only(['grade', 'section', 'q', 'status']),
            'canSee' => $request->user()->hasPermission('portal', 'edit'),
            'counts' => [
                'all' => $students->count(),
                'temp' => $students->filter(fn ($s) => $s->initial_password)->count(),
                'own' => $students->filter(fn ($s) => $s->password && ! $s->initial_password && ! $s->must_change_password)->count(),
                'none' => $students->filter(fn ($s) => ! $s->password)->count(),
            ],
        ]);
    }

    /** تنزيل بيانات الدخول الحالية (بدون إنشاء كلمات جديدة) */
    public function exportCredentials(Request $request, StudentCredentials $credentials)
    {
        $rows = $this->credentialsQuery($request)->get()->filter(fn ($s) => $s->national_id)->map(fn ($s) => [
            'student' => $s,
            'password' => $s->initial_password ?: ($s->password ? 'اختارها الطالب' : 'لا يوجد حساب'),
        ])->values();
        abort_if($rows->isEmpty(), 404, 'لا يوجد طلاب بهذه التصفية.');

        return $credentials->download($rows, 'بيانات الدخول');
    }

    /** بطاقات دخول للطباعة وتوزيعها على الطلاب */
    public function printCredentials(Request $request)
    {
        $students = $this->credentialsQuery($request)->get()->filter(fn ($s) => $s->national_id && $s->initial_password)->values();

        return view('admin.portal.print', ['students' => $students]);
    }

    private function credentialsQuery(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        return Student::with('section.gradeLevel')->where('students.is_active', true)
            ->when($request->integer('section'), fn ($w, $v) => $w->where('students.section_id', $v))
            ->when($request->integer('grade'), fn ($w, $v) => $w->whereHas('section', fn ($s) => $s->where('grade_level_id', $v)))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('students.name', 'like', "%{$q}%")->orWhere('students.national_id', 'like', "%{$q}%")->orWhere('students.phone', 'like', "%{$q}%")))
            ->when($request->query('status') === 'temp', fn ($w) => $w->whereNotNull('students.initial_password'))
            ->when($request->query('status') === 'own', fn ($w) => $w->whereNotNull('students.password')->whereNull('students.initial_password'))
            ->when($request->query('status') === 'none', fn ($w) => $w->whereNull('students.password'))
            ->join('sections', 'sections.id', '=', 'students.section_id')
            ->join('grade_levels', 'grade_levels.id', '=', 'sections.grade_level_id')
            ->orderBy('grade_levels.sort_order')->orderBy('sections.name')
            ->orderByRaw("CASE WHEN students.gender = 'male' THEN 0 ELSE 1 END")->orderBy('students.name')
            ->select('students.*');
    }

    /** إيقاف حساب الطالب في البوابة مع ذكر السبب */
    public function suspend(Request $request, Student $student)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']], [], ['reason' => 'سبب الإيقاف']);
        $student->forceFill(['suspended_at' => now(), 'suspension_reason' => trim($data['reason']), 'suspended_by' => $request->user()->id])->save();

        return back()->with('status', "أُوقف حساب {$student->name}. سيرى رسالة الإيقاف والسبب عند الدخول.");
    }

    public function unsuspend(Student $student)
    {
        $student->forceFill(['suspended_at' => null, 'suspension_reason' => null, 'suspended_by' => null])->save();

        return back()->with('status', "أُعيد تفعيل حساب {$student->name}.");
    }

    /** إعادة تعيين كلمة مرور طالب واحد (مثلاً إن نسيها) */
    public function issueStudent(Student $student, StudentCredentials $credentials)
    {
        $student->load('section.gradeLevel');
        if (! $student->national_id) {
            return back()->with('warning', 'لا يمكن إنشاء حساب لطالب بدون رقم هوية. أضف رقم الهوية من صفحة الطلاب.');
        }

        return $credentials->download($credentials->issue(collect([$student])), $student->name);
    }
}
