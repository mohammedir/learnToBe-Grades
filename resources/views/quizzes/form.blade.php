@extends('layouts.app')
@section('title', $quiz->exists ? 'تعديل اختبار' : 'اختبار جديد')
@if($asAdmin) @section('module', 'quizzes') @endif
@section('content')
@php($qs = old('questions', $questions))
<div class="page-head">
    <div><h1>{{ $quiz->exists ? 'تعديل: '.$quiz->title : 'اختبار جديد' }}</h1>
        <div class="sub">أسئلة اختيار من متعدد، أو صح وخطأ، أو إجابة رقمية، أو إجابة نصية. أضف معادلات وصيغاً كيميائية وصوراً بضغطة زر.</div></div>
    <div class="spacer"></div>
    <a class="btn" href="{{ route($rp.'quizzes.index') }}">كل الاختبارات</a>
</div>
@if($locked)<div class="alert warn">بدأ طلاب بحل هذا الاختبار، فلا يمكن تعديل الأسئلة. تستطيع تعديل العنوان والمواعيد والنشر.</div>@endif

<form method="post" action="{{ $quiz->exists ? route($rp.'quizzes.update', $quiz) : route($rp.'quizzes.store') }}" id="quiz-form" enctype="multipart/form-data">
    @csrf
    @if($quiz->exists) @method('put') @endif
    <div class="grid2">
        <div class="panel">
            <h2>بيانات الاختبار</h2>
            <div class="form">
                <div class="field"><label for="ta">الكشف (المادة والشعبة{{ $asAdmin ? ' والمدرس' : '' }})</label>
                    <select id="ta" name="teaching_assignment_id" @disabled($locked) required>
                        @foreach($assignments as $a)<option value="{{ $a->id }}" @selected(old('teaching_assignment_id', $quiz->teaching_assignment_id) == $a->id)>{{ $a->subject->name }} – {{ $a->section->full_name }}@if($asAdmin) – أ. {{ $a->teacher->name }}@endif</option>@endforeach
                    </select>
                    @if($locked)<input type="hidden" name="teaching_assignment_id" value="{{ $quiz->teaching_assignment_id }}">@endif
                    @if($asAdmin)<span class="hint">تُنشئ الاختبار نيابة عن مدرس الكشف، ويظهر في حسابه أيضاً.</span>@endif
                </div>
                <div class="field"><label for="title">عنوان الاختبار</label><input type="text" id="title" name="title" value="{{ old('title', $quiz->title) }}" placeholder="مثال: اختبار قصير – الحركة في خط مستقيم" required></div>
                <div class="field"><label for="instructions">تعليمات للطالب (اختياري)</label><textarea id="instructions" name="instructions" rows="3" placeholder="مثال: استخدم g = 9.8 m/s² في جميع الأسئلة.">{{ old('instructions', $quiz->instructions) }}</textarea></div>
            </div>
        </div>
        <div class="panel">
            <h2>المواعيد والنشر</h2>
            <div class="form">
                <div class="inline">
                    <div class="field" style="flex:1"><label for="opens_at">يبدأ في (اختياري)</label><input type="datetime-local" id="opens_at" name="opens_at" value="{{ old('opens_at', $quiz->opens_at?->format('Y-m-d\TH:i')) }}"></div>
                    <div class="field" style="flex:1"><label for="closes_at">يغلق في (اختياري)</label><input type="datetime-local" id="closes_at" name="closes_at" value="{{ old('closes_at', $quiz->closes_at?->format('Y-m-d\TH:i')) }}"></div>
                </div>
                <div class="field"><label for="duration">مدة الحل بالدقائق (اختياري)</label><input type="number" id="duration" name="duration_minutes" min="1" max="300" value="{{ old('duration_minutes', $quiz->duration_minutes) }}" placeholder="مثال: 20" style="max-width:160px"><span class="hint">تبدأ عند ضغط الطالب «ابدأ»، ويُسلَّم الاختبار تلقائياً عند انتهائها.</span></div>
                <label class="switch"><input type="hidden" name="is_published" value="0"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $quiz->is_published))><span></span><b>نشر الاختبار للطلاب</b></label>
                <label class="switch"><input type="hidden" name="results_published" value="0"><input type="checkbox" name="results_published" value="1" @checked(old('results_published', $quiz->results_published))><span></span>نشر النتائج للطلاب فور التسليم</label>
                <label class="switch"><input type="hidden" name="show_answers" value="0"><input type="checkbox" name="show_answers" value="1" @checked(old('show_answers', $quiz->show_answers))><span></span>يرى الطالب الإجابات الصحيحة مع النتيجة</label>
                <span class="hint">إن تركت «نشر النتائج» مغلقاً، يرى الطالب «تم التسليم» فقط حتى تنشرها من صفحة النتائج بعد التصحيح.</span>
            </div>
        </div>
    </div>

    @unless($locked)
    <div class="panel">
        <div class="inline" style="margin-bottom:12px"><h2 style="margin:0">الأسئلة</h2><div class="spacer"></div><span id="q-total" class="pill none"></span></div>
        <div id="questions"></div>
        <div class="add-row">
            <span>إضافة سؤال:</span>
            <button class="btn" type="button" data-add="mcq">◉ اختيار من متعدد</button>
            <button class="btn" type="button" data-add="tf">✓✗ صح أو خطأ</button>
            <button class="btn" type="button" data-add="number">123 إجابة رقمية</button>
            <button class="btn" type="button" data-add="text">✎ إجابة نصية</button>
        </div>
    </div>
    @endunless

    <div class="inline sticky-actions">
        <button class="btn primary" type="submit">حفظ الاختبار</button>
        <a class="btn" href="{{ route($rp.'quizzes.index') }}">إلغاء</a>
    </div>
</form>

@if($quiz->exists)
<div class="panel" style="margin-top:16px">
    <h2>حذف الاختبار</h2>
    <form method="post" action="{{ route($rp.'quizzes.destroy', $quiz) }}" onsubmit="return confirm('حذف الاختبار وكل نتائجه؟')">@csrf @method('delete')<button class="btn danger" type="submit">حذف</button></form>
</div>
@endif

{{-- نافذة المعادلات --}}
<dialog id="eq-dialog" class="dialog eq-dialog">
    <div class="inline" style="margin-bottom:10px"><h2 style="margin:0">إدراج معادلة</h2><div class="spacer"></div><button class="btn sm" type="button" data-close>إغلاق</button></div>
    <div class="eq-tabs" role="tablist">
        <button type="button" class="active" data-palette="math">رياضيات</button>
        <button type="button" data-palette="physics">فيزياء</button>
        <button type="button" data-palette="greek">رموز يونانية</button>
    </div>
    <div class="eq-palette" id="eq-palette"></div>
    <div class="eq-editor">
        <div id="eq-visual" class="eq-visual" dir="ltr"></div>
        <textarea id="eq-latex" dir="ltr" rows="2" class="no-math" placeholder="\frac{v^2}{2a}" aria-label="المعادلة بصيغة LaTeX"></textarea>
    </div>
    <div class="eq-preview-label">المعاينة:</div>
    <div id="eq-preview" class="eq-preview" dir="ltr"></div>
    <div class="inline" style="margin-top:12px">
        <label class="check"><input type="checkbox" id="eq-block"> في سطر مستقل (معادلة كبيرة)</label>
        <div class="spacer"></div>
        <button class="btn primary" type="button" id="eq-insert">إدراج</button>
    </div>
    <p class="hint" style="margin:8px 0 0">اكتب في المحرر كما تكتب على السبورة: اضغط زراً من الأعلى أو اكتب مباشرة، مثل <code dir="ltr">x^2</code> للأس و<code dir="ltr">/</code> للكسر.</p>
</dialog>

{{-- نافذة الصيغ الكيميائية --}}
<dialog id="chem-dialog" class="dialog">
    <div class="inline" style="margin-bottom:10px"><h2 style="margin:0">صيغة كيميائية أو تفاعل</h2><div class="spacer"></div><button class="btn sm" type="button" data-close>إغلاق</button></div>
    <div class="eq-palette" id="chem-palette"></div>
    <input type="text" id="chem-input" dir="ltr" class="no-math" placeholder="C6H12O6 + 6O2 -> 6CO2 + 6H2O" style="width:100%" aria-label="الصيغة">
    <p class="hint">اكتب الأرقام عادية وستصبح صغيرة تلقائياً. السهم <code dir="ltr">-></code>، والتفاعل العكسي <code dir="ltr"><=></code>، والشحنة <code dir="ltr">Na+</code> أو <code dir="ltr">SO4^2-</code>.</p>
    <div class="eq-preview-label">المعاينة:</div>
    <div id="chem-preview" class="eq-preview" dir="ltr"></div>
    <div class="inline" style="margin-top:12px"><div class="spacer"></div><button class="btn primary" type="button" id="chem-insert">إدراج</button></div>
</dialog>

<template id="q-tpl">
    <div class="q-card">
        <div class="q-head">
            <span class="q-num"></span>
            <select class="q-type-select" aria-label="نوع السؤال">
                @foreach(\App\Models\QuizQuestion::TYPES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
            </select>
            <div class="spacer"></div>
            <label class="q-pts">الدرجة <input type="number" class="q-points" min="0.25" step="0.25" value="1"></label>
            <button class="icon-btn q-up" type="button" title="تحريك لأعلى" aria-label="تحريك لأعلى">↑</button>
            <button class="icon-btn q-down" type="button" title="تحريك لأسفل" aria-label="تحريك لأسفل">↓</button>
            <button class="icon-btn danger q-del" type="button" title="حذف السؤال" aria-label="حذف السؤال">✕</button>
        </div>
        <div class="q-toolbar">
            <button type="button" class="tb" data-tool="eq">∑ معادلة</button>
            <button type="button" class="tb" data-tool="chem">⚗ صيغة كيميائية</button>
            <button type="button" class="tb" data-tool="image">🖼 صورة</button>
            <span class="tb-sep"></span>
            <button type="button" class="tb sym" data-ins="×">×</button><button type="button" class="tb sym" data-ins="÷">÷</button>
            <button type="button" class="tb sym" data-ins="±">±</button><button type="button" class="tb sym" data-ins="√">√</button>
            <button type="button" class="tb sym" data-ins="²">x²</button><button type="button" class="tb sym" data-ins="³">x³</button>
            <button type="button" class="tb sym" data-ins="π">π</button><button type="button" class="tb sym" data-ins="°">°</button>
            <button type="button" class="tb sym" data-ins="→">→</button><button type="button" class="tb sym" data-ins="Δ">Δ</button>
            <button type="button" class="tb sym" data-ins="≤">≤</button><button type="button" class="tb sym" data-ins="≥">≥</button>
        </div>
        <textarea class="q-body" rows="2" placeholder="اكتب نص السؤال… ويمكنك إدراج معادلة أو صيغة من الأزرار أعلاه"></textarea>
        <div class="q-preview" aria-live="polite"></div>
        <div class="q-image" hidden>
            <img alt="صورة السؤال">
            <button type="button" class="btn sm danger q-img-remove">حذف الصورة</button>
        </div>
        <input type="file" class="q-file" accept="image/png,image/jpeg,image/webp,image/gif" hidden>
        <input type="hidden" class="q-image-path">
        <input type="hidden" class="q-remove-image" value="0">
        <div class="q-answer"></div>
    </div>
</template>
@endsection
@push('scripts')
@include('partials.math')
<script type="application/json" id="quiz-initial">@json(array_values($qs))</script>
<script src="{{ asset('js/quiz-builder.js') }}?v=1" defer></script>
@endpush
