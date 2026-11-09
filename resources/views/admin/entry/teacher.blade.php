@extends('layouts.app')
@section('title', 'كشوف '.$teacher->name)
@section('content')
<div class="page-head">
    <div><h1>كشوف {{ $teacher->name }}</h1><div class="sub">اختر الكشف لرصد درجاته نيابة عن المدرس.</div></div>
    <div class="spacer"></div>
    @if($assignments->isNotEmpty())<a class="btn primary" href="{{ route('admin.marks.export-teacher', $teacher) }}">⬇ تصدير كل كشوفه Excel</a>@endif
    <a class="btn" href="{{ route('admin.entry.index') }}">كل المدرسين</a>
</div>
@if($assignments->isEmpty())
    <div class="panel">هذا المدرس غير مكلّف بأي شعبة.@perm('teachers', 'edit') <a href="{{ route('admin.teachers.assignments', $teacher) }}">أضف شعبه وموادّه</a>.@endperm</div>
@else
<div class="cards">
    @foreach($assignments as $a)
        @php($p = $progress[$a->id])
        @php($pct = $p['students'] ? round($p['complete'] / $p['students'] * 100) : 0)
        <a class="card" href="{{ route('admin.marks.show', $a) }}">
            <b>{{ $a->subject->name }}</b>
            <span class="meta">{{ $a->section->full_name }} · {{ $p['students'] }} طالب</span>
            <div class="bar" aria-hidden="true"><i style="width:{{ $pct }}%"></i></div>
            <span class="meta">
                @if($p['students'] && $p['complete'] === $p['students'])<span class="pill done">مكتمل</span>
                @elseif($p['any'])<span class="pill part">رُصد {{ $p['complete'] }} من {{ $p['students'] }}</span>
                @else<span class="pill none">لم يبدأ الرصد</span>@endif
            </span>
        </a>
    @endforeach
</div>
@endif
@endsection
