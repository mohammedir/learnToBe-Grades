<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use App\Services\StudentImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $grades = GradeLevel::orderBy('sort_order')->with('sections')->get();
        $section = $request->filled('section') ? Section::with('gradeLevel')->find($request->integer('section')) : null;
        $q = trim((string) $request->query('q', ''));

        $students = collect();
        $inactiveCount = Student::where('is_active', false)->count();
        // الطلاب غير المنتظمين (متوقف / منسحب / منقول)
        if ($request->query('show') === 'inactive') {
            $inactive = Student::with(['section.gradeLevel', 'statusChanger'])->withCount('marks')->where('is_active', false)
                ->when($request->query('status'), fn ($w, $v) => $w->where('enrollment_status', $v))
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('national_id', 'like', "%{$q}%")))
                ->orderByDesc('status_date')->orderBy('name')->get();

            return view('admin.students.inactive', compact('grades', 'inactive', 'q', 'inactiveCount'));
        }
        if ($section) {
            $students = $section->orderedStudents();
        } elseif ($q !== '') {
            // البحث يشمل غير المنتظمين أيضاً (مع إظهار حالتهم)
            $students = Student::with('section.gradeLevel')->orderByDesc('is_active')
                ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('national_id', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', '%'.(\App\Support\Phone::normalize($q) ?? $q).'%')->orWhere('phone2', 'like', "%{$q}%"))
                ->orderBy('name')->limit(100)->get();
        }

        return view('admin.students.index', compact('grades', 'section', 'students', 'q', 'inactiveCount'));
    }

    public function store(Request $request)
    {
        Student::create($this->validated($request));

        return back()->with('status', 'تمت إضافة الطالب.');
    }

    public function update(Request $request, Student $student)
    {
        $student->update($this->validated($request, $student));

        return back()->with('status', 'تم حفظ بيانات الطالب.');
    }

    /** كل درجات طالب واحد للإدارة (بغض النظر عن نشر الدرجات للطلاب) */
    public function grades(Student $student)
    {
        $student->load(['section.gradeLevel', 'statusChanger']);
        $report = new \App\Services\StudentReport($student, true);
        $quizzes = \App\Models\QuizAttempt::with('quiz.assignment.subject')->where('student_id', $student->id)
            ->whereNotNull('submitted_at')->latest('submitted_at')->get();

        return view('admin.students.grades', compact('student', 'report', 'quizzes'));
    }

    public function destroy(Request $request, Student $student)
    {
        // لا نحذف الطالب نهائياً حتى لا تضيع درجاته
        $student->setEnrollment('stopped', 'حُذف من قوائم الشعب', null, $request->user());

        return back()->with('status', 'أُخفي الطالب من قوائم الشعب. درجاته السابقة بقيت محفوظة، ويمكن إعادته من «الطلاب غير المنتظمين».');
    }

    /** تغيير حالة الطالب: متوقف / منسحب / منقول، أو إعادته منتظماً (مع اختيار الشعبة) */
    public function status(Request $request, Student $student)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Student::ENROLLMENT))],
            'reason' => ['nullable', 'string', 'max:500'],
            'date' => ['nullable', 'date'],
            'section_id' => ['nullable', 'exists:sections,id'],
        ], [], ['status' => 'حالة الطالب', 'reason' => 'السبب', 'date' => 'التاريخ', 'section_id' => 'الشعبة']);

        if ($data['status'] === 'active') {
            $section = Section::find($data['section_id'] ?? $student->section_id);
            if (! $section->accepts($student->gender)) {
                return back()->with('warning', 'الشعبة '.$section->full_name.' خاصة بـ'.$section->gender_label.'. اختر شعبة مناسبة للطالب.');
            }
            $student->setEnrollment('active', $data['reason'] ?? null, $data['date'] ?? null, $request->user(), $section->id);
            $marks = $student->marks()->count();

            return back()->with('status', "عاد {$student->name} منتظماً في {$section->full_name}، ويظهر الآن للمدرسين في كشوف الرصد".($marks ? " مع درجاته السابقة ({$marks} درجة)" : '').'.');
        }

        $student->setEnrollment($data['status'], $data['reason'] ?? null, $data['date'] ?? null, $request->user());

        return back()->with('status', "تم تغيير حالة {$student->name} إلى «{$student->enrollment_label}». لم يعد يظهر في كشوف الرصد ولا يُحسب في نسب الإكمال، ودرجاته محفوظة.");
    }

    /** نقل مجموعة طلاب إلى شعبة أخرى (لتقسيم الصف إلى شعب) */
    public function move(Request $request)
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'target_section_id' => ['required', 'exists:sections,id'],
        ], ['student_ids.required' => 'اختر طالباً واحداً على الأقل.', 'target_section_id.required' => 'اختر الشعبة التي تنقل إليها.']);

        $target = Section::with('gradeLevel')->find($data['target_section_id']);
        $query = Student::whereIn('id', $data['student_ids']);
        $skipped = $target->gender ? (clone $query)->where('gender', '!=', $target->gender)->count() : 0;
        if ($target->gender) {
            $query->where('gender', $target->gender);
        }
        $n = $query->update(['section_id' => $target->id]);

        $back = back()->with('status', "تم نقل {$n} طالب إلى {$target->full_name}.");

        return $skipped ? $back->with('warning', "لم يُنقل {$skipped} طالب لأن الشعبة خاصة بـ{$target->gender_label}.") : $back;
    }

    public function importForm()
    {
        return view('admin.students.import');
    }

    /** الخطوة 1: رفع الملف وعرض معاينة لما سيتغير، بدون حفظ */
    public function import(Request $request, StudentImporter $importer)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']], [], ['file' => 'ملف الطلاب']);
        $sync = $request->boolean('sync');
        $path = $request->file('file')->store('imports');
        $report = $importer->import(Storage::path($path), $sync, dryRun: true);
        session(['student_import' => ['path' => $path, 'sync' => $sync, 'name' => $request->file('file')->getClientOriginalName()]]);

        return view('admin.students.preview', ['report' => $report, 'sync' => $sync, 'fileName' => $request->file('file')->getClientOriginalName()]);
    }

    /** الخطوة 2: تأكيد الاستيراد بعد المعاينة */
    public function importConfirm(StudentImporter $importer)
    {
        $pending = session()->pull('student_import');
        if (! $pending || ! Storage::exists($pending['path'])) {
            return redirect()->route('admin.students.import')->with('warning', 'انتهت صلاحية المعاينة. ارفع الملف مرة أخرى.');
        }
        $r = $importer->import(Storage::path($pending['path']), $pending['sync']);
        Storage::delete($pending['path']);

        $msg = 'تم الاستيراد: '.count($r['created']).' طالب جديد، و'.$r['updated'].' طالب موجود حُدّثت بياناته'
            .($r['phones'] ? '، وأُضيف أو حُدّث جوال '.$r['phones'].' طالب' : '')
            .($r['corrected'] ? '، منهم '.count($r['corrected']).' صُحّح اسمه أو رقم هويته' : '')
            .($r['moved'] ? '، و'.count($r['moved']).' نُقل لشعبة أخرى' : '')
            .($r['deactivated'] ? '، و'.count($r['deactivated']).' أُوقف لعدم وجوده في الملف' : '').'.';

        return redirect()->route('admin.students.index')->with('status', $msg);
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'section_id' => ['required', 'exists:sections,id'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'national_id' => ['nullable', 'digits_between:5,12', Rule::unique('students')->ignore($student?->id)],
            'birth_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:30'],
            'phone2' => ['nullable', 'string', 'max:30'],
        ], [], ['phone' => 'رقم الجوال', 'phone2' => 'الجوال الإضافي', 'name' => 'اسم الطالب', 'section_id' => 'الشعبة', 'gender' => 'الجنس', 'national_id' => 'رقم الهوية', 'birth_date' => 'تاريخ الميلاد']);

        foreach (['phone', 'phone2'] as $f) {
            if (! empty($data[$f])) {
                $n = \App\Support\Phone::normalize($data[$f]);
                if (! $n) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$f => 'رقم الجوال غير صحيح. اكتبه 10 أرقام يبدأ بـ 05، مثل 0599123456.']);
                }
                $data[$f] = $n;
            }
        }

        $section = Section::find($data['section_id']);
        if (! $section->accepts($data['gender'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'section_id' => 'هذه الشعبة خاصة بـ'.$section->gender_label.'. اختر شعبة تناسب جنس الطالب.',
            ]);
        }

        return $data;
    }
}
