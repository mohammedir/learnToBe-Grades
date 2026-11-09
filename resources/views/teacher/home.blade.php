@extends('layouts.app')
@section('title', 'كشوفي')
@section('content')
<div class="page-head">
    <div><h1>أهلاً {{ auth()->user()->name }}</h1><div class="sub">هذه الشعب والمواد المكلّف برصد درجاتها. اختر كشفاً للبدء.</div></div>
    @if($assignments->isNotEmpty())<div class="spacer"></div><a class="btn" href="{{ route('teacher.export-all') }}">⬇ تصدير كل كشوفي Excel</a>@endif
</div>
@if($assignments->isEmpty())
    <div class="panel">لم تُكلَّف بأي شعبة بعد. تواصل مع إدارة المدرسة لإضافة شعبك وموادك.</div>
@else
<div class="cards">
    @foreach($assignments as $a)
        @php($p = $progress[$a->id])
        @php($pct = $p['students'] ? round($p['complete'] / $p['students'] * 100) : 0)
        <a class="card" href="{{ route('teacher.sheet', $a) }}">
            <b>{{ $a->subject->name }}</b>
            <span class="meta">{{ $a->section->full_name }} · {{ $p['students'] }} طالب</span>
            <div class="bar" aria-hidden="true"><i style="width:{{ $pct }}%"></i></div>
            @if($p['approved'])<span class="meta" style="color:var(--navy);font-weight:700">🔒 {{ $p['approved'] >= $p['components'] ? 'كل الدرجات معتمدة' : 'معتمد '.$p['approved'].' من '.$p['components'].' عناصر' }}</span>@endif
            <span class="meta">
                @if($p['complete'] === $p['students'] && $p['students'] > 0)<span class="pill done">مكتمل</span>
                @elseif($p['any'])<span class="pill part">رُصد {{ $p['complete'] }} من {{ $p['students'] }}</span>
                @else<span class="pill none">لم يبدأ الرصد</span>@endif
            </span>
        </a>
    @endforeach
</div>
@endif
@endsection
