<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AssessmentComponent;
use App\Models\Mark;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\TeachingAssignment;
use App\Services\QuizGrader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * الاختبارات الإلكترونية. نفس الشاشات تعمل للمدرس (كشوفه فقط)
 * وللإدارة تحت /admin (كل الكشوف، نيابة عن المدرس، حسب صلاحية «الاختبارات»).
 */
class QuizController extends Controller
{
    private const IMAGE_DIR = 'uploads/quizzes';

    public function index(Request $request)
    {
        $assignments = $this->assignments($request)->get();
        $quizzes = Quiz::with(['assignment.section.gradeLevel', 'assignment.subject', 'assignment.teacher'])
            ->withCount(['questions', 'attempts as submitted_count' => fn ($q) => $q->whereNotNull('submitted_at'),
                'attempts as review_count' => fn ($q) => $q->where('needs_review', true)])
            ->whereIn('teaching_assignment_id', $assignments->pluck('id'))
            ->when($request->integer('assignment'), fn ($q, $id) => $q->where('teaching_assignment_id', $id))
            ->latest()->get();

        return view('quizzes.index', $this->shared($request) + ['quizzes' => $quizzes, 'assignments' => $assignments, 'filter' => $request->integer('assignment')]);
    }

    public function create(Request $request)
    {
        $assignments = $this->assignments($request)->get();
        abort_if($assignments->isEmpty(), 403, 'لا توجد كشوف لإنشاء اختبار لها.');

        return view('quizzes.form', $this->shared($request) + [
            'quiz' => new Quiz(['teaching_assignment_id' => $request->integer('assignment') ?: $assignments->first()->id, 'show_answers' => true]),
            'assignments' => $assignments,
            'questions' => [],
            'locked' => false,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $quiz = DB::transaction(function () use ($data, $request) {
            $quiz = Quiz::create($data['quiz'] + ['created_by' => $request->user()->id]);
            $this->saveQuestions($quiz, $data['questions']);

            return $quiz;
        });

        return redirect()->route($this->rp($request).'quizzes.index')->with('status', $quiz->is_published
            ? "نُشر الاختبار «{$quiz->title}» ويظهر الآن للطلاب."
            : "حُفظ الاختبار «{$quiz->title}» كمسودة. انشره عندما يصبح جاهزاً.");
    }

    public function edit(Request $request, Quiz $quiz)
    {
        $this->owns($request, $quiz);

        return view('quizzes.form', $this->shared($request) + [
            'quiz' => $quiz,
            'assignments' => $this->assignments($request)->get(),
            'questions' => $quiz->questions->map(fn ($q) => [
                'type' => $q->type, 'body' => $q->body, 'options' => $q->options ?? [], 'correct' => $q->correct,
                'points' => $q->points, 'meta' => $q->meta ?? (object) [], 'image_path' => $q->image_path, 'image_url' => $q->imageUrl(),
            ])->all(),
            'locked' => $quiz->attempts()->exists(),
        ]);
    }

    public function update(Request $request, Quiz $quiz)
    {
        $this->owns($request, $quiz);
        $locked = $quiz->attempts()->exists();
        $data = $this->validated($request, $locked);
        $oldImages = $quiz->questions->pluck('image_path')->filter()->all();

        DB::transaction(function () use ($quiz, $data, $locked) {
            $quiz->update($locked ? collect($data['quiz'])->except('teaching_assignment_id')->all() : $data['quiz']);
            if (! $locked) {
                $quiz->questions()->delete();
                $this->saveQuestions($quiz, $data['questions']);
            }
        });
        if (! $locked) {
            $this->deleteImages(array_diff($oldImages, array_filter(array_column($data['questions'], 'image_path'))));
        }

        return redirect()->route($this->rp($request).'quizzes.index')->with('status', 'حُفظ الاختبار.'.($locked ? ' الأسئلة لم تتغير لأن طلاباً بدأوا حلّه.' : ''));
    }

    public function destroy(Request $request, Quiz $quiz)
    {
        $this->owns($request, $quiz);
        $this->deleteImages($quiz->questions->pluck('image_path')->filter()->all());
        $quiz->delete();

        return redirect()->route($this->rp($request).'quizzes.index')->with('status', 'حُذف الاختبار ونتائجه.');
    }

    public function results(Request $request, Quiz $quiz)
    {
        $this->owns($request, $quiz);
        $quiz->load(['assignment.section.gradeLevel', 'assignment.subject', 'assignment.teacher', 'questions']);
        $students = $quiz->assignment->section->orderedStudents();
        $attempts = $quiz->attempts()->get()->keyBy('student_id');
        $submitted = $attempts->whereNotNull('submitted_at');
        $graded = $submitted->where('needs_review', false);

        $questionStats = $quiz->questions->map(function ($q) use ($submitted) {
            $vals = $submitted->map(fn ($a) => $a->awarded[$q->id] ?? null)->filter(fn ($v) => $v !== null);

            return ['question' => $q, 'percent' => $vals->count() && $q->points > 0 ? round($vals->avg() / $q->points * 100) : null];
        });

        return view('quizzes.results', $this->shared($request) + [
            'quiz' => $quiz,
            'students' => $students,
            'attempts' => $attempts,
            'questionStats' => $questionStats,
            'components' => AssessmentComponent::active()->get(),
            'stats' => [
                'submitted' => $submitted->count(),
                'review' => $submitted->where('needs_review', true)->count(),
                'avg' => $graded->count() ? round($graded->avg('score'), 2) : null,
                'max' => $graded->max('score'),
                'min' => $graded->min('score'),
            ],
        ]);
    }

    /** صفحة تصحيح محاولة طالب (للإجابات النصية أو لتعديل أي درجة) */
    public function review(Request $request, Quiz $quiz, QuizAttempt $attempt)
    {
        $this->owns($request, $quiz);
        abort_unless((int) $attempt->quiz_id === (int) $quiz->id && $attempt->submitted_at, 404);
        $quiz->load(['questions', 'assignment.subject', 'assignment.section.gradeLevel']);
        $attempt->load('student');

        // التالي الذي يحتاج تصحيحاً
        $next = $quiz->attempts()->where('needs_review', true)->where('id', '!=', $attempt->id)->value('id');

        return view('quizzes.review', $this->shared($request) + compact('quiz', 'attempt', 'next'));
    }

    public function saveReview(Request $request, Quiz $quiz, QuizAttempt $attempt)
    {
        $this->owns($request, $quiz);
        abort_unless((int) $attempt->quiz_id === (int) $quiz->id && $attempt->submitted_at, 404);
        $quiz->load('questions');
        $input = (array) $request->input('points', []);
        $awarded = $attempt->awarded ?? [];
        foreach ($quiz->questions as $q) {
            if (! array_key_exists($q->id, $input)) {
                continue;
            }
            $v = trim((string) $input[$q->id]);
            if ($v === '') {
                $awarded[$q->id] = null;

                continue;
            }
            $n = QuizGrader::parseNumber($v);
            if ($n === null || $n < 0 || $n > $q->points) {
                throw ValidationException::withMessages(["points.{$q->id}" => 'درجة السؤال يجب أن تكون من 0 إلى '.QuizGrader::formatNumber($q->points).'.']);
            }
            $awarded[$q->id] = $n;
        }
        QuizGrader::finalize($attempt->setRelation('quiz', $quiz), $awarded, $request->user()->id);

        if ($request->filled('next')) {
            return redirect()->route($this->rp($request).'quizzes.review', [$quiz, $request->integer('next')])->with('status', 'حُفظ التصحيح.');
        }

        return redirect()->route($this->rp($request).'quizzes.results', $quiz)->with('status', "حُفظ تصحيح {$attempt->student->name}.");
    }

    /** نشر النتائج للطلاب أو إخفاؤها */
    public function publishResults(Request $request, Quiz $quiz)
    {
        $this->owns($request, $quiz);
        $publish = $request->boolean('publish');
        $quiz->update(['results_published' => $publish]);
        $pending = $quiz->attempts()->where('needs_review', true)->count();

        return back()->with('status', $publish
            ? 'نُشرت النتائج، ويرى كل طالب درجته الآن.'.($pending ? " تنبيه: {$pending} محاولة ما زالت تحتاج تصحيحاً وستظهر درجتها جزئية حتى تصححها." : '')
            : 'أُخفيت النتائج عن الطلاب.');
    }

    /** نقل نتائج الاختبار إلى عنصر درجة في الكشف (بالتناسب) */
    public function transfer(Request $request, Quiz $quiz)
    {
        $this->owns($request, $quiz);
        $data = $request->validate(['component_id' => ['required', 'exists:assessment_components,id'], 'overwrite' => ['nullable', 'boolean']], [], ['component_id' => 'عنصر الدرجة']);
        $component = AssessmentComponent::findOrFail($data['component_id']);
        $quiz->load('assignment');
        if (\App\Models\MarkApproval::isLocked($quiz->assignment->section_id, $quiz->assignment->subject_id, $component->id)) {
            return back()->with('warning', "درجات «{$component->name}» في هذا الكشف معتمدة من الإدارة، فلا يمكن نقل نتائج الاختبار إليها. اطلب فك الاعتماد أولاً.");
        }
        $n = $skipped = $pending = 0;
        foreach ($quiz->attempts()->whereNotNull('submitted_at')->get() as $a) {
            if ($a->needs_review) {
                $pending++;

                continue;
            }
            if ($a->max_score <= 0) {
                continue;
            }
            $key = ['student_id' => $a->student_id, 'subject_id' => $quiz->assignment->subject_id, 'assessment_component_id' => $component->id];
            if (! $request->boolean('overwrite') && Mark::where($key)->exists()) {
                $skipped++;

                continue;
            }
            Mark::updateOrCreate($key, ['score' => round($a->score / $a->max_score * $component->max_score * 2) / 2, 'entered_by' => $request->user()->id]);
            $n++;
        }

        return back()->with('status', "نُقلت درجات {$n} طالب إلى «{$component->name}».".($skipped ? " لم تُغيَّر {$skipped} درجة مرصودة سابقاً." : '').($pending ? " لم تُنقل {$pending} محاولة لأنها تحتاج تصحيحاً." : ''));
    }

    // ---------------------------------------------------------------

    private function isAdmin(Request $request): bool
    {
        return $request->routeIs('admin.*');
    }

    private function rp(Request $request): string
    {
        return $this->isAdmin($request) ? 'admin.' : 'teacher.';
    }

    private function shared(Request $request): array
    {
        return ['rp' => $this->rp($request), 'asAdmin' => $this->isAdmin($request)];
    }

    /** الكشوف المتاحة: كل الكشوف للإدارة، وكشوف المدرس له */
    private function assignments(Request $request)
    {
        $q = $this->isAdmin($request) ? TeachingAssignment::query() : $request->user()->assignments();

        return $q->with(['section.gradeLevel', 'subject', 'teacher']);
    }

    private function owns(Request $request, Quiz $quiz): void
    {
        if ($this->isAdmin($request)) {
            return; // الصلاحية يتحقق منها الوسيط perm:quizzes
        }
        abort_unless((int) $quiz->assignment()->value('teacher_id') === (int) $request->user()->id, 403, 'هذا الاختبار ليس لك.');
    }

    private function validated(Request $request, bool $locked = false): array
    {
        $rules = [
            'teaching_assignment_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:3000'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:300'],
        ];
        if (! $locked) {
            $rules += [
                'questions' => ['required', 'array', 'min:1', 'max:100'],
                'questions.*.type' => ['required', 'in:mcq,tf,number,text'],
                'questions.*.body' => ['required', 'string', 'max:5000'],
                'questions.*.options' => ['nullable', 'array', 'max:6'],
                'questions.*.options.*' => ['nullable', 'string', 'max:1000'],
                'questions.*.correct' => ['nullable', 'string', 'max:20'],
                'questions.*.points' => ['required', 'numeric', 'min:0.25', 'max:100'],
                'questions.*.value' => ['nullable', 'string', 'max:50'],
                'questions.*.tolerance' => ['nullable', 'string', 'max:50'],
                'questions.*.unit' => ['nullable', 'string', 'max:40'],
                'questions.*.accepted' => ['nullable', 'string', 'max:2000'],
                'questions.*.model' => ['nullable', 'string', 'max:3000'],
                'questions.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
                'questions.*.image_path' => ['nullable', 'string', 'max:255'],
                'questions.*.remove_image' => ['nullable', 'boolean'],
            ];
        }
        $v = $request->validate($rules, [
            'questions.required' => 'أضف سؤالاً واحداً على الأقل.',
            'questions.*.body.required' => 'اكتب نص كل سؤال.',
            'questions.*.image.image' => 'ملف الصورة يجب أن يكون صورة (jpg أو png أو webp).',
            'questions.*.image.max' => 'حجم الصورة يجب ألا يزيد على 4 ميغابايت.',
            'closes_at.after' => 'وقت الإغلاق يجب أن يكون بعد وقت الفتح.',
        ], ['title' => 'عنوان الاختبار', 'closes_at' => 'وقت الإغلاق', 'duration_minutes' => 'مدة الاختبار']);

        abort_unless($this->assignments($request)->whereKey($v['teaching_assignment_id'])->exists(), 403);

        $questions = [];
        foreach ($locked ? [] : $v['questions'] as $i => $q) {
            $n = count($questions) + 1;
            $row = ['type' => $q['type'], 'body' => trim($q['body']), 'points' => $q['points'], 'sort_order' => $n, 'options' => null, 'correct' => null, 'meta' => null];

            switch ($q['type']) {
                case 'mcq':
                    $opts = array_values(array_filter(array_map('trim', $q['options'] ?? []), fn ($o) => $o !== ''));
                    if (count($opts) < 2) {
                        throw ValidationException::withMessages(["questions.$i.options" => "السؤال {$n}: اكتب خيارين على الأقل."]);
                    }
                    $orig = isset($q['correct']) ? ($q['options'][(int) $q['correct']] ?? null) : null;
                    $correct = $orig === null ? false : array_search(trim((string) $orig), $opts, true);
                    if ($correct === false) {
                        throw ValidationException::withMessages(["questions.$i.correct" => "السؤال {$n}: اختر الإجابة الصحيحة."]);
                    }
                    $row['options'] = $opts;
                    $row['correct'] = (string) $correct;
                    break;

                case 'tf':
                    if (! in_array($q['correct'] ?? null, ['true', 'false'], true)) {
                        throw ValidationException::withMessages(["questions.$i.correct" => "السؤال {$n}: اختر صح أو خطأ."]);
                    }
                    $row['correct'] = $q['correct'];
                    break;

                case 'number':
                    $value = QuizGrader::parseNumber($q['value'] ?? null);
                    if ($value === null) {
                        throw ValidationException::withMessages(["questions.$i.value" => "السؤال {$n}: اكتب الإجابة الصحيحة رقماً، مثل 9.8 أو 3×10^8."]);
                    }
                    $row['meta'] = ['value' => $value, 'tolerance' => abs(QuizGrader::parseNumber($q['tolerance'] ?? null) ?? 0), 'unit' => trim($q['unit'] ?? '')];
                    break;

                case 'text':
                    $accepted = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $q['accepted'] ?? ''))));
                    $row['meta'] = ['accepted' => $accepted, 'model' => trim($q['model'] ?? '') ?: ($accepted[0] ?? '')];
                    break;
            }

            // الصورة: جديدة، أو الحالية، أو حذفها
            $path = $q['image_path'] ?? null;
            if ($path && ! str_starts_with($path, self::IMAGE_DIR.'/')) {
                $path = null;
            }
            if (! empty($q['remove_image'])) {
                $path = null;
            }
            if ($file = $request->file("questions.$i.image")) {
                File::ensureDirectoryExists(public_path(self::IMAGE_DIR));
                $name = Str::random(24).'.'.strtolower($file->getClientOriginalExtension() ?: 'png');
                $file->move(public_path(self::IMAGE_DIR), $name);
                $path = self::IMAGE_DIR.'/'.$name;
            }
            $row['image_path'] = $path;

            $questions[] = $row;
        }

        return [
            'quiz' => [
                'teaching_assignment_id' => $v['teaching_assignment_id'],
                'title' => $v['title'],
                'instructions' => $v['instructions'] ?? null,
                'opens_at' => $v['opens_at'] ?? null,
                'closes_at' => $v['closes_at'] ?? null,
                'duration_minutes' => $v['duration_minutes'] ?? null,
                'show_answers' => $request->boolean('show_answers'),
                'is_published' => $request->boolean('is_published'),
                'results_published' => $request->boolean('results_published'),
            ],
            'questions' => $questions,
        ];
    }

    private function saveQuestions(Quiz $quiz, array $questions): void
    {
        foreach ($questions as $q) {
            $quiz->questions()->create($q);
        }
    }

    private function deleteImages(array $paths): void
    {
        foreach ($paths as $p) {
            if ($p && str_starts_with($p, self::IMAGE_DIR.'/')) {
                File::delete(public_path($p));
            }
        }
    }
}
