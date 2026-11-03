@extends('layouts.app')
@section('title', 'الاختبارات')
@if($asAdmin) @section('module', 'quizzes') @endif
@section('content')
<div class="page-head">
    <div><h1>الاختبارات الإلكترونية</h1><div class="sub">{{ $asAdmin ? 'كل اختبارات المدرسين. تستطيع إنشاء اختبار لأي كشف نيابة عن مدرسه، ومتابعة النتائج وتصحيحها ونشرها.' : 'أنشئ اختباراً لطلاب أحد كشوفك؛ يحلّه الطالب من بوابته ويُصحَّح تلقائياً.' }}</div></div>
    <div class="spacer"></div>
    @if(! $asAdmin || auth()->user()->hasPermission('quizzes', 'edit'))
        <a class="btn primary" href="{{ route($rp.'quizzes.create', array_filter(['assignment' => $filter])) }}">اختبار جديد</a>
    @endif
</div>
<div class="panel">
    <form class="inline" method="get">
        <select name="assignment" onchange="this.form.submit()" aria-label="الكشف">
            <option value="">{{ $asAdmin ? 'كل الكشوف' : 'كل كشوفي' }}</option>
            @foreach($assignments as $a)<option value="{{ $a->id }}" @selected($filter == $a->id)>{{ $a->subject->name }} – {{ $a->section->full_name }}@if($asAdmin) – {{ $a->teacher->name }}@endif</option>@endforeach
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
            <span class="meta">{{ $q->assignment->subject->name }} · {{ $q->assignment->section->full_name }}@if($asAdmin) · {{ $q->assignment->teacher->name }}@endif</span>
            <span class="meta">{{ $q->questions_count }} سؤال @if($q->duration_minutes)· {{ $q->duration_minutes }} دقيقة @endif @if($q->closes_at)· يغلق {{ $q->closes_at->format('Y/m/d H:i') }}@endif</span>
            <div class="inline" style="gap:6px">
                <span class="pill none">سلّم {{ $q->submitted_count }}</span>
                @if($q->review_count)<span class="pill part">{{ $q->review_count }} بانتظار التصحيح</span>@endif
                <span class="pill {{ $q->results_published ? 'done' : 'none' }}">{{ $q->results_published ? 'النتائج منشورة' : 'النتائج مخفية' }}</span>
            </div>
            <div class="inline">
                <a class="btn sm primary" href="{{ route($rp.'quizzes.results', $q) }}">النتائج والتصحيح</a>
                @if(! $asAdmin || auth()->user()->hasPermission('quizzes', 'edit'))<a class="btn sm" href="{{ route($rp.'quizzes.edit', $q) }}">تعديل</a>@endif
            </div>
        </div>
    @endforeach
</div>
@endif
@endsection
