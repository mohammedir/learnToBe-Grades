@extends('layouts.app')
@section('title', 'نتائج '.$quiz->title)
@if($asAdmin) @section('module', 'quizzes') @endif
@section('content')
@php($fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'))
@php($max = $quiz->maxScore())
@php($canEdit = ! $asAdmin || auth()->user()->hasPermission('quizzes', 'edit'))
<div class="page-head">
    <div><h1>نتائج: {{ $quiz->title }}</h1><div class="sub">{{ $quiz->assignment->subject->name }} · {{ $quiz->assignment->section->full_name }}@if($asAdmin) · أ. {{ $quiz->assignment->teacher->name }}@endif · {{ $quiz->stateLabel() }}</div></div>
    <div class="spacer"></div>
    @if($canEdit)<a class="btn" href="{{ route($rp.'quizzes.edit', $quiz) }}">تعديل</a>@endif
    <a class="btn" href="{{ route($rp.'quizzes.index') }}">كل الاختبارات</a>
</div>

<div class="publish-bar {{ $quiz->results_published ? 'on' : 'off' }}">
    <div>
        <b>{{ $quiz->results_published ? 'النتائج منشورة للطلاب' : 'النتائج مخفية عن الطلاب' }}</b>
        <span>{{ $quiz->results_published ? 'يرى كل طالب درجته'.($quiz->show_answers ? ' والإجابات الصحيحة' : '').'.' : 'يرى الطالب «تم التسليم» فقط حتى تنشر النتائج.' }}</span>
    </div>
    @if($canEdit)
    <form method="post" action="{{ route($rp.'quizzes.publish', $quiz) }}">
        @csrf
        <input type="hidden" name="publish" value="{{ $quiz->results_published ? 0 : 1 }}">
        <button class="btn {{ $quiz->results_published ? '' : 'primary' }}" type="submit"
            @if(! $quiz->results_published && $stats['review']) onclick="return confirm('{{ $stats['review'] }} محاولة ما زالت تحتاج تصحيحاً. نشر النتائج الآن؟')" @endif>
            {{ $quiz->results_published ? 'إخفاء النتائج' : 'نشر النتائج للطلاب' }}</button>
    </form>
    @endif
</div>

<div class="stats">
    <div class="stat"><b>{{ $stats['submitted'] }}/{{ $students->count() }}</b><span>سلّموا الاختبار</span></div>
    <div class="stat"><b>{{ $stats['review'] }}</b><span>بانتظار التصحيح</span></div>
    <div class="stat"><b>{{ $fmt($stats['avg']) }}</b><span>المتوسط من {{ $fmt($max) }}</span></div>
    <div class="stat"><b>{{ $fmt($stats['max']) }} / {{ $fmt($stats['min']) }}</b><span>أعلى / أقل درجة</span></div>
</div>

@if($stats['review'] && $canEdit)
    @php($first = $attempts->first(fn ($a) => $a->needs_review))
    <div class="alert warn inline">✎ {{ $stats['review'] }} محاولة فيها إجابات نصية تحتاج تصحيحك. <div class="spacer"></div><a class="btn sm primary" href="{{ route($rp.'quizzes.review', [$quiz, $first]) }}">ابدأ التصحيح</a></div>
@endif

<div class="scroll">
<table>
    <thead><tr><th>الطالب</th><th class="c">الحالة</th><th class="c">الدرجة</th><th class="c">النسبة</th><th>وقت التسليم</th><th></th></tr></thead>
    <tbody>
    @foreach($students as $st)
        @php($a = $attempts[$st->id] ?? null)
        <tr>
            <td><b>{{ $st->name }}</b></td>
            <td class="c">
                @if(! $a)<span class="pill none">لم يبدأ</span>
                @elseif(! $a->submitted_at)<span class="pill part">يحل الآن / لم يسلّم</span>
                @elseif($a->needs_review)<span class="pill part">بانتظار التصحيح</span>
                @elseif($a->late)<span class="pill off">سلّم متأخراً</span>
                @else<span class="pill done">مصحّح</span>@endif
            </td>
            <td class="c"><b>{{ $a?->submitted_at ? $fmt($a->score).' / '.$fmt($a->max_score) : '—' }}</b>@if($a?->needs_review)<small style="color:var(--muted)"> (جزئي)</small>@endif</td>
            <td class="c">{{ $a?->submitted_at && $a->max_score > 0 ? round($a->score / $a->max_score * 100).'%' : '—' }}</td>
            <td class="muted">{{ $a?->submitted_at?->format('Y/m/d H:i') ?? '' }}</td>
            <td>@if($a?->submitted_at && $canEdit)<a class="btn sm {{ $a->needs_review ? 'primary' : '' }}" href="{{ route($rp.'quizzes.review', [$quiz, $a]) }}">{{ $a->needs_review ? 'صحّح' : 'عرض / تعديل' }}</a>@endif</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

@if($stats['submitted'] && $components->isNotEmpty() && $canEdit)
<div class="panel" style="margin-top:16px">
    <h2>نقل النتائج إلى كشف الدرجات</h2>
    <form class="inline" method="post" action="{{ route($rp.'quizzes.transfer', $quiz) }}" onsubmit="return confirm('نقل درجات الاختبار إلى الكشف؟')">
        @csrf
        <select name="component_id" required aria-label="عنصر الدرجة">@foreach($components as $c)<option value="{{ $c->id }}">{{ $c->name }} (من {{ $fmt($c->max_score) }})</option>@endforeach</select>
        <label class="check"><input type="checkbox" name="overwrite" value="1"> استبدال الدرجات المرصودة سابقاً</label>
        <button class="btn primary" type="submit">نقل</button>
    </form>
    <p style="color:var(--muted);font-size:13px;margin-bottom:0">تُحوَّل الدرجة بالتناسب لأقرب نصف درجة، مثلاً 8 من {{ $fmt($max) }} في عنصر من 20 = {{ $fmt($max > 0 ? round(8 / $max * 20 * 2) / 2 : 0) }}. المحاولات التي تحتاج تصحيحاً لا تُنقل حتى تصححها.</p>
</div>
@endif

@if($stats['submitted'])
<h2 style="margin-top:18px">صعوبة الأسئلة</h2>
<div class="scroll">
<table>
    <thead><tr><th class="c">م</th><th>السؤال</th><th class="c">النوع</th><th class="c">الإجابة الصحيحة</th><th style="width:30%">متوسط الدرجة</th></tr></thead>
    <tbody>
    @foreach($questionStats as $i => $qs)
        @php($p = $qs['percent'])
        <tr>
            <td class="c">{{ $i + 1 }}</td>
            <td class="math-cell">{{ \Illuminate\Support\Str::limit($qs['question']->body, 140) }}</td>
            <td class="c"><span class="pill none">{{ \App\Models\QuizQuestion::TYPES[$qs['question']->type] }}</span></td>
            <td class="c math-cell">{{ \Illuminate\Support\Str::limit($qs['question']->correctText(), 60) ?: '—' }}</td>
            <td><div class="inline" style="flex-wrap:nowrap"><div class="bar" style="flex:1"><i style="width:{{ $p ?? 0 }}%;background:{{ ($p ?? 0) < 50 ? 'var(--bad)' : 'var(--ok)' }}"></i></div><b style="min-width:42px">{{ $p === null ? '—' : $p.'%' }}</b></div></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endif
@endsection
@push('scripts')@include('partials.math')@endpush
