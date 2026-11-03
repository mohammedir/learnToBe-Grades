@extends('layouts.app')
@section('title', $quiz->exists ? 'تعديل اختبار' : 'اختبار جديد')
@section('content')
@php($qs = old('questions', $questions))
<div class="page-head">
    <div><h1>{{ $quiz->exists ? 'تعديل: '.$quiz->title : 'اختبار جديد' }}</h1>
        <div class="sub">أسئلة اختيار من متعدد أو صح وخطأ، تُصحَّح تلقائياً عند التسليم.</div></div>
    <div class="spacer"></div>
    <a class="btn" href="{{ route('teacher.quizzes.index') }}">كل الاختبارات</a>
</div>
@if($locked)<div class="alert warn">بدأ طلاب بحل هذا الاختبار، فلا يمكن تعديل الأسئلة. تستطيع تعديل العنوان والمواعيد والنشر.</div>@endif

<form method="post" action="{{ $quiz->exists ? route('teacher.quizzes.update', $quiz) : route('teacher.quizzes.store') }}" id="quiz-form">
    @csrf
    @if($quiz->exists) @method('put') @endif
    <div class="grid2">
        <div class="panel">
            <h2>بيانات الاختبار</h2>
            <div class="form">
                <div class="field"><label for="ta">الكشف (المادة والشعبة)</label>
                    <select id="ta" name="teaching_assignment_id" @disabled($locked) required>
                        @foreach($assignments as $a)<option value="{{ $a->id }}" @selected(old('teaching_assignment_id', $quiz->teaching_assignment_id) == $a->id)>{{ $a->subject->name }} – {{ $a->section->full_name }}</option>@endforeach
                    </select>
                    @if($locked)<input type="hidden" name="teaching_assignment_id" value="{{ $quiz->teaching_assignment_id }}">@endif
                </div>
                <div class="field"><label for="title">عنوان الاختبار</label><input type="text" id="title" name="title" value="{{ old('title', $quiz->title) }}" placeholder="مثال: اختبار قصير – الوحدة الأولى" required></div>
                <div class="field"><label for="instructions">تعليمات للطالب (اختياري)</label><textarea id="instructions" name="instructions" rows="3">{{ old('instructions', $quiz->instructions) }}</textarea></div>
            </div>
        </div>
        <div class="panel">
            <h2>المواعيد والنشر</h2>
            <div class="form">
                <div class="field"><label for="opens_at">يبدأ في (اختياري)</label><input type="datetime-local" id="opens_at" name="opens_at" value="{{ old('opens_at', $quiz->opens_at?->format('Y-m-d\TH:i')) }}"></div>
                <div class="field"><label for="closes_at">يغلق في (اختياري)</label><input type="datetime-local" id="closes_at" name="closes_at" value="{{ old('closes_at', $quiz->closes_at?->format('Y-m-d\TH:i')) }}"></div>
                <div class="field"><label for="duration">مدة الحل بالدقائق (اختياري)</label><input type="number" id="duration" name="duration_minutes" min="1" max="300" value="{{ old('duration_minutes', $quiz->duration_minutes) }}" placeholder="مثال: 20"><span class="hint">تبدأ المدة عندما يضغط الطالب «ابدأ»، ويُسلَّم الاختبار تلقائياً عند انتهائها.</span></div>
                <label class="check"><input type="hidden" name="show_answers" value="0"><input type="checkbox" name="show_answers" value="1" @checked(old('show_answers', $quiz->show_answers))> يرى الطالب الإجابات الصحيحة بعد التسليم</label>
                <label class="check"><input type="hidden" name="is_published" value="0"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $quiz->is_published))> <b>نشر الاختبار للطلاب</b></label>
            </div>
        </div>
    </div>

    @unless($locked)
    <div class="panel">
        <div class="inline" style="margin-bottom:10px"><h2 style="margin:0">الأسئلة</h2><div class="spacer"></div><span id="q-total" style="color:var(--muted);font-size:13px"></span></div>
        <div id="questions"></div>
        <div class="inline">
            <button class="btn" type="button" data-add="mcq">+ سؤال اختيار من متعدد</button>
            <button class="btn" type="button" data-add="tf">+ سؤال صح أو خطأ</button>
        </div>
    </div>
    @endunless

    <div class="inline">
        <button class="btn primary" type="submit">حفظ الاختبار</button>
        <a class="btn" href="{{ route('teacher.quizzes.index') }}">إلغاء</a>
    </div>
</form>

@if($quiz->exists)
<div class="panel" style="margin-top:16px">
    <h2>حذف الاختبار</h2>
    <form method="post" action="{{ route('teacher.quizzes.destroy', $quiz) }}" onsubmit="return confirm('حذف الاختبار وكل نتائجه؟')">@csrf @method('delete')<button class="btn danger" type="submit">حذف</button></form>
</div>
@endif

<template id="q-tpl">
    <div class="q-card">
        <div class="inline q-head"><b class="q-num"></b><span class="q-kind pill none"></span><div class="spacer"></div>
            <label class="check">الدرجة <input type="number" class="q-points" min="0.25" step="0.25" value="1" style="width:80px"></label>
            <button class="btn sm ghost q-up" type="button" title="تحريك لأعلى">↑</button>
            <button class="btn sm danger q-del" type="button">حذف</button></div>
        <textarea class="q-body" rows="2" placeholder="نص السؤال" required></textarea>
        <div class="q-options"></div>
    </div>
</template>
@endsection
@push('scripts')
<script>
(function () {
    const box = document.getElementById('questions');
    if (!box) return;
    const tpl = document.getElementById('q-tpl');
    const initial = @json(array_values($qs));

    function optionRow(card, text, checked) {
        const i = card.querySelectorAll('.q-opt').length;
        const row = document.createElement('label');
        row.className = 'q-opt';
        row.innerHTML = '<input type="radio" class="q-correct" title="الإجابة الصحيحة"><input type="text" class="q-opt-text" placeholder="الخيار ' + (i + 1) + '">';
        row.querySelector('.q-opt-text').value = text || '';
        row.querySelector('.q-correct').checked = !!checked;
        card.querySelector('.q-opt-list').appendChild(row);
    }

    function add(q) {
        const card = tpl.content.firstElementChild.cloneNode(true);
        card.dataset.type = q.type;
        card.querySelector('.q-kind').textContent = q.type === 'tf' ? 'صح أو خطأ' : 'اختيار من متعدد';
        card.querySelector('.q-body').value = q.body || '';
        card.querySelector('.q-points').value = q.points || 1;
        const opts = card.querySelector('.q-options');
        if (q.type === 'tf') {
            opts.innerHTML = '<div class="chips"><label><input type="radio" class="q-correct" value="true"> صح</label><label><input type="radio" class="q-correct" value="false"> خطأ</label></div><span class="hint">اختر الإجابة الصحيحة.</span>';
            opts.querySelectorAll('.q-correct').forEach(r => r.checked = r.value === String(q.correct));
        } else {
            opts.innerHTML = '<div class="q-opt-list"></div><div class="inline"><button class="btn sm q-add-opt" type="button">+ خيار</button><span class="hint">ضع علامة على الإجابة الصحيحة.</span></div>';
            const list = q.options && q.options.length ? q.options : ['', '', '', ''];
            list.forEach((t, i) => optionRow(card, t, String(i) === String(q.correct)));
            opts.querySelector('.q-add-opt').addEventListener('click', () => { if (card.querySelectorAll('.q-opt').length < 6) { optionRow(card, '', false); rename(); } });
        }
        card.querySelector('.q-del').addEventListener('click', () => { card.remove(); rename(); });
        card.querySelector('.q-up').addEventListener('click', () => { if (card.previousElementSibling) { box.insertBefore(card, card.previousElementSibling); rename(); } });
        card.querySelector('.q-points').addEventListener('input', total);
        box.appendChild(card);
        rename();
    }

    // أسماء الحقول تُبنى حسب الترتيب الحالي قبل الإرسال
    function rename() {
        box.querySelectorAll('.q-card').forEach((card, i) => {
            const p = 'questions[' + i + ']';
            card.querySelector('.q-num').textContent = 'السؤال ' + (i + 1);
            card.querySelector('.q-body').name = p + '[body]';
            card.querySelector('.q-points').name = p + '[points]';
            let hidden = card.querySelector('.q-type');
            if (!hidden) { hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.className = 'q-type'; card.appendChild(hidden); }
            hidden.name = p + '[type]'; hidden.value = card.dataset.type;
            card.querySelectorAll('.q-correct').forEach((r, j) => {
                r.name = p + '[correct]';
                if (card.dataset.type === 'mcq') r.value = String(j);
            });
            card.querySelectorAll('.q-opt-text').forEach(t => t.name = p + '[options][]');
        });
        total();
    }
    function total() {
        const pts = [...box.querySelectorAll('.q-points')].reduce((s, i) => s + (parseFloat(i.value) || 0), 0);
        const n = box.querySelectorAll('.q-card').length;
        document.getElementById('q-total').textContent = n + ' سؤال · المجموع ' + pts + ' درجة';
    }

    document.querySelectorAll('[data-add]').forEach(b => b.addEventListener('click', () => add({ type: b.dataset.add })));
    (initial.length ? initial : [{ type: 'mcq' }]).forEach(add);

    document.getElementById('quiz-form').addEventListener('submit', (e) => {
        const missing = [...box.querySelectorAll('.q-card')].findIndex(c => !c.querySelector('.q-correct:checked'));
        if (missing >= 0) { e.preventDefault(); alert('حدّد الإجابة الصحيحة في السؤال ' + (missing + 1)); }
    });
})();
</script>
@endpush
