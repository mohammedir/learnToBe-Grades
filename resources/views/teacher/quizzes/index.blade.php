@extends('layouts.app')
@section('title', 'الاختبارات')
@section('content')
<div class="page-head">
    <div><h1>الاختبارات الإلكترونية</h1><div class="sub">أنشئ اختباراً لطلاب أحد كشوفك؛ يحلّه الطالب من بوابته ويُصحَّح تلقائياً.</div></div>
    <div class="spacer"></div>
    <a class="btn primary" href="{{ route('teacher.quizzes.create', array_filter(['assignment' => $filter])) }}">اختبار جديد</a>
</div>
<div class="panel">
    <form class="inline" method="get">
        <select name="assignment" onchange="this.form.submit()" aria-label="الكشف">
            <option value="">كل كشوفي</option>
            @foreach($assignments as $a)<option value="{{ $a->id }}" @selected($filter == $a->id)>{{ $a->subject->name }} – {{ $a->section->full_name }}</option>@endforeach
        </select>
    </form>
</div>
@if($quizzes->isEmpty())
    <div class="panel" style="text-align:center;color:var(--muted);padding-block:28px">لا توجد اختبارات بعد. اضغط «اختبار جديد» لإنشاء أول اختبار.</div>
@else
<div class="cards">
    @foreach($quizzes as $q)
        @php($state = $q->state())
        <div class="card">
            <div class="inline"><b>{{ $q->title }}</b><div class="spacer"></div>
                <span class="pill {{ ['draft' => 'none', 'upcoming' => 'part', 'open' => 'done', 'closed' => 'off'][$state] }}">{{ $q->stateLabel() }}</span></div>
            <span class="meta">{{ $q->assignment->subject->name }} · {{ $q->assignment->section->full_name }}</span>
            <span class="meta">{{ $q->questions_count }} سؤال @if($q->duration_minutes)· {{ $q->duration_minutes }} دقيقة @endif @if($q->closes_at)· يغلق {{ $q->closes_at->format('Y/m/d H:i') }}@endif</span>
            <span class="meta">سلّم {{ $q->submitted_count }} من {{ $q->assignment->section->students()->count() }} طالب</span>
            <div class="inline">
                <a class="btn sm primary" href="{{ route('teacher.quizzes.results', $q) }}">النتائج</a>
                <a class="btn sm" href="{{ route('teacher.quizzes.edit', $q) }}">تعديل</a>
            </div>
        </div>
    @endforeach
</div>
@endif
@endsection
