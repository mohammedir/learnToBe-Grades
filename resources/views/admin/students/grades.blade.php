@extends('layouts.app')
@section('title', 'درجات '.$student->name)
@section('module', 'marks')
@section('content')
@php($R = \App\Services\StudentReport::class)
@php($fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'))
@php($avg = $report->average())
<div class="page-head no-print">
    <div><a class="muted small" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('admin.students.index', ['section' => $student->section_id]) }}">→ رجوع</a></div>
    <div class="spacer"></div>
    <button class="btn" type="button" onclick="window.print()">🖨 طباعة</button>
    @perm('marks', 'edit')<a class="btn" href="{{ route('admin.students.index', ['section' => $student->section_id]) }}">طلاب الشعبة</a>@endperm
</div>

<section class="sg-head">
    <div class="sg-id">
        <span class="print-only sg-school">مدرسة تعلم لتكون الخاصة — كشف درجات طالب</span>
        <h1>{{ $student->name }}</h1>
        <div class="sg-meta">
            <span>{{ $student->section?->full_name }}</span>
            @if($student->national_id)<span dir="ltr">{{ $student->national_id }}</span>@endif
            <span>{{ $student->gender === 'female' ? 'أنثى' : 'ذكر' }}</span>
            @unless($student->is_active)<span class="pill off">{{ $student->enrollment_label }}@if($student->status_date) منذ {{ $student->status_date->format('Y/m/d') }}@endif</span>@endunless
            <span class="muted">نشر الدرجات للطلاب: {{ \App\Models\Setting::get('grades_published') ? 'منشورة' : 'مخفية' }}</span>
        </div>
    </div>
    <div class="sg-avg tone-{{ $R::tone($avg === null ? null : $R::rating($avg)) }}">
        <b>{{ $avg === null ? '—' : $fmt($avg).'%' }}</b>
        <span>{{ $avg === null ? 'لا توجد مادة مكتملة' : $R::rating($avg) }}</span>
        <small>{{ $report->completeCount() }} من {{ count($report->subjects) }} مواد مكتملة</small>
    </div>
</section>

@if(! count($report->subjects))
    <div class="panel empty-state">لا توجد مواد مسندة لشعبة الطالب ولا درجات محفوظة له.</div>
@else
<div class="scroll"><table class="sg-table">
    <thead><tr>
        <th>المادة</th>
        @foreach($report->components as $c)<th class="c">{{ $c->name }}<small>من {{ $fmt($c->max_score) }}</small></th>@endforeach
        <th class="c">المجموع<small>من {{ $fmt($report->subjects[0]['max']) }}</small></th>
        <th class="c">النسبة</th><th class="c">التقدير</th>
        <th class="no-print">المدرس / آخر رصد</th>
    </tr></thead>
    <tbody>
    @foreach($report->subjects as $s)
        <tr @class(['sg-old' => ! $s['current']])>
            <td><b>{{ $s['subject'] }}</b>@unless($s['current'])<div class="muted small">من شعبة سابقة</div>@endunless</td>
            @foreach($report->components as $c)
                @php($v = $s['scores'][$c->id])
                <td class="c tnum @if($v === null) sg-miss @endif">{{ $fmt($v) }}</td>
            @endforeach
            <td class="c tnum"><b>{{ $fmt($s['total']) }}</b>@if(! $s['complete'] && $s['total'] !== null)<div class="muted small">غير مكتمل</div>@endif</td>
            <td class="c tnum">{{ $s['percent'] === null ? '—' : $fmt($s['percent']).'%' }}</td>
            <td class="c">@if($s['rating'])<span class="pill {{ $R::ratingClass($s['rating']) }}">{{ $s['rating'] }}</span>@else<span class="muted">—</span>@endif</td>
            <td class="no-print small">{{ $s['teacher'] ?? '—' }}@if($s['last'])<div class="muted">آخر رصد {{ $s['last']['at']->format('Y/m/d') }}@if($s['last']['by']) · {{ $s['last']['by'] }}@endif</div>@endif</td>
        </tr>
    @endforeach
    </tbody>
</table></div>
<p class="muted small">«—» = لم تُرصد بعد. النسبة والتقدير يُحسبان عند اكتمال كل عناصر المادة، والمعدل = متوسط نسب المواد المكتملة.</p>
@endif

@if($quizzes->isNotEmpty())
<section class="panel sg-quizzes">
    <h2>الاختبارات القصيرة ({{ $quizzes->count() }})</h2>
    <div class="scroll"><table>
        <thead><tr><th>الاختبار</th><th>المادة</th><th class="c">الدرجة</th><th class="c">النسبة</th><th class="c no-print">الخروج من الصفحة</th><th>التاريخ</th></tr></thead>
        <tbody>
        @foreach($quizzes as $a)
            @php($pct = $a->max_score > 0 && $a->score !== null ? round($a->score / $a->max_score * 100) : null)
            <tr>
                <td>{{ $a->quiz?->title }}</td>
                <td>{{ $a->quiz?->assignment?->subject?->name }}</td>
                <td class="c tnum">@if($a->needs_review)<span class="pill part">بانتظار التصحيح</span>@else{{ $fmt($a->score) }} / {{ $fmt($a->max_score) }}@endif</td>
                <td class="c">{{ $pct === null || $a->needs_review ? '—' : $pct.'%' }}</td>
                <td class="c no-print">@if($a->leave_count)<span class="integ {{ $a->leave_count <= 2 ? 'mid' : 'high' }}">⚠ {{ $a->leave_count }}</span>@else<span class="integ ok">✓ 0</span>@endif</td>
                <td class="muted">{{ $a->submitted_at->format('Y/m/d') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</section>
@endif
<p class="print-only muted small">طُبع في {{ now()->format('Y/m/d H:i') }} — {{ auth()->user()->name }}</p>
@endsection
