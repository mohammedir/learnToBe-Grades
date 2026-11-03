@extends('layouts.app')
@section('title', 'نتائج '.$quiz->title)
@section('content')
@php($fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'))
@php($max = $quiz->maxScore())
<div class="page-head">
    <div><h1>نتائج: {{ $quiz->title }}</h1><div class="sub">{{ $quiz->assignment->subject->name }} · {{ $quiz->assignment->section->full_name }} · {{ $quiz->stateLabel() }}</div></div>
    <div class="spacer"></div>
    <a class="btn" href="{{ route('teacher.quizzes.edit', $quiz) }}">تعديل</a>
    <a class="btn" href="{{ route('teacher.quizzes.index') }}">كل الاختبارات</a>
</div>
<div class="stats">
    <div class="stat"><b>{{ $stats['submitted'] }}/{{ $students->count() }}</b><span>سلّموا الاختبار</span></div>
    <div class="stat"><b>{{ $fmt($stats['avg']) }}</b><span>المتوسط من {{ $fmt($max) }}</span></div>
    <div class="stat"><b>{{ $fmt($stats['max']) }}</b><span>أعلى درجة</span></div>
    <div class="stat"><b>{{ $fmt($stats['min']) }}</b><span>أقل درجة</span></div>
</div>

@if($stats['submitted'] && $components->isNotEmpty())
<div class="panel">
    <h2>نقل النتائج إلى كشف الدرجات</h2>
    <form class="inline" method="post" action="{{ route('teacher.quizzes.transfer', $quiz) }}" onsubmit="return confirm('نقل درجات الاختبار إلى الكشف؟')">
        @csrf
        <select name="component_id" required aria-label="عنصر الدرجة">@foreach($components as $c)<option value="{{ $c->id }}">{{ $c->name }} (من {{ $fmt($c->max_score) }})</option>@endforeach</select>
        <label class="check"><input type="checkbox" name="overwrite" value="1"> استبدال الدرجات المرصودة سابقاً</label>
        <button class="btn primary" type="submit">نقل</button>
    </form>
    <p style="color:var(--muted);font-size:13px;margin-bottom:0">تُحوَّل الدرجة بالتناسب: مثلاً 8 من {{ $fmt($max) }} في عنصر من 20 = {{ $fmt($max > 0 ? round(8 / $max * 20 * 2) / 2 : 0) }}، لأقرب نصف درجة. لن يُغيَّر من لم يسلّم.</p>
</div>
@endif

<div class="scroll">
<table>
    <thead><tr><th>الطالب</th><th class="c">الحالة</th><th class="c">الدرجة</th><th class="c">النسبة</th><th>وقت التسليم</th></tr></thead>
    <tbody>
    @foreach($students as $st)
        @php($a = $attempts[$st->id] ?? null)
        <tr>
            <td><b>{{ $st->name }}</b></td>
            <td class="c">
                @if(! $a)<span class="pill none">لم يبدأ</span>
                @elseif(! $a->submitted_at)<span class="pill part">بدأ ولم يسلّم</span>
                @elseif($a->late)<span class="pill off">سلّم متأخراً</span>
                @else<span class="pill done">سلّم</span>@endif
            </td>
            <td class="c"><b>{{ $a?->submitted_at ? $fmt($a->score).' / '.$fmt($a->max_score) : '—' }}</b></td>
            <td class="c">{{ $a?->submitted_at && $a->max_score > 0 ? round($a->score / $a->max_score * 100).'%' : '—' }}</td>
            <td class="muted">{{ $a?->submitted_at?->format('Y/m/d H:i') ?? '' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

@if($stats['submitted'])
<h2 style="margin-top:18px">صعوبة الأسئلة</h2>
<div class="scroll">
<table>
    <thead><tr><th class="c">م</th><th>السؤال</th><th class="c">الإجابة الصحيحة</th><th style="width:34%">أجاب إجابة صحيحة</th></tr></thead>
    <tbody>
    @foreach($questionStats as $i => $qs)
        <tr>
            <td class="c">{{ $i + 1 }}</td>
            <td>{{ \Illuminate\Support\Str::limit($qs['question']->body, 90) }}</td>
            <td class="c">{{ $qs['question']->choices()[$qs['question']->correct] ?? '' }}</td>
            <td><div class="inline" style="flex-wrap:nowrap"><div class="bar" style="flex:1"><i style="width:{{ $qs['percent'] ?? 0 }}%;background:{{ ($qs['percent'] ?? 0) < 50 ? 'var(--bad)' : 'var(--ok)' }}"></i></div><b style="min-width:42px">{{ $qs['percent'] }}%</b></div></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endif
@endsection
