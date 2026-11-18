@extends('layouts.app')
@section('title', 'حضور المدرسين')
@section('module', 'attendance')
@section('content')
@include('admin.attendance._tabs')
@php($st = \App\Services\AttendanceReport::STATUS)
<div class="page-head">
    <div><h1>حضور {{ $date->isToday() ? 'اليوم' : '' }} {{ $date->locale('ar')->translatedFormat('l j F Y') }}</h1>
        <div class="sub">الدوام {{ $settings['start'] }} – {{ $settings['end'] }} · سماح {{ $settings['grace'] }} دقائق@unless(\App\Services\AttendanceReport::isWorkday($date, $settings)) · <b>يوم عطلة</b>@endunless</div></div>
    <div class="spacer"></div>
    <form class="inline" method="get">
        <a class="btn sm" href="{{ route('admin.attendance.index', ['date' => $date->copy()->subDay()->toDateString()]) }}" aria-label="اليوم السابق">→</a>
        <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" onchange="this.form.submit()" aria-label="التاريخ">
        @unless($date->isToday())<a class="btn sm" href="{{ route('admin.attendance.index', ['date' => $date->copy()->addDay()->toDateString()]) }}" aria-label="اليوم التالي">←</a><a class="btn sm" href="{{ route('admin.attendance.index') }}">اليوم</a>@endunless
    </form>
</div>

@if(! $pointsCount)
    <div class="alert warn">لم يُنشأ أي رمز QR بعد. @perm('attendance', 'edit')<a href="{{ route('admin.attendance.points') }}">أنشئ رمزاً واطبعه</a> ليبدأ المدرسون التسجيل.@endperm</div>
@endif

<div class="stats att-stats">
    <div class="stat s-ok"><b>{{ ($counts['present'] ?? 0) + ($counts['late'] ?? 0) }}</b><span>حاضر من {{ $rows->count() }}</span></div>
    <div class="stat s-warn"><b>{{ $counts['late'] ?? 0 }}</b><span>متأخر</span></div>
    <div class="stat s-bad"><b>{{ $counts['absent'] ?? 0 }}</b><span>غائب</span></div>
    <div class="stat"><b>{{ $counts['waiting'] ?? 0 }}</b><span>لم يسجّل بعد</span></div>
    <div class="stat"><b>{{ $noOut }}</b><span>بلا تسجيل مغادرة</span></div>
    @if($flagged)<div class="stat s-warn"><b>{{ $flagged }}</b><span>تحتاج مراجعة</span></div>@endif
</div>

<div class="scroll"><table class="att-table">
    <thead><tr><th>الاسم</th><th class="c">الحضور</th><th class="c">المغادرة</th><th class="c">المدة</th><th class="c">الحالة</th><th>ملاحظات</th><th></th></tr></thead>
    <tbody>
    @foreach($rows as $r)
        <tr class="att-{{ $r['status'] }}">
            <td><a href="{{ route('admin.attendance.person', ['user' => $r['user'], 'month' => $date->format('Y-m')]) }}"><b>{{ $r['user']->name }}</b></a><div class="muted small">{{ $r['user']->role_label }}</div></td>
            <td class="c tnum">{{ $r['in']?->occurred_at->format('H:i') ?? '—' }}</td>
            <td class="c tnum">{{ $r['out']?->occurred_at->format('H:i') ?? ($r['no_out'] ? '' : '—') }}@if($r['no_out'])<span class="pill part">لم يسجّل</span>@endif</td>
            <td class="c tnum">{{ $r['minutes'] !== null ? intdiv($r['minutes'], 60).':'.str_pad($r['minutes'] % 60, 2, '0', STR_PAD_LEFT) : '—' }}</td>
            <td class="c"><span class="pill {{ $st[$r['status']][1] }}">{{ $st[$r['status']][0] }}@if($r['late']) {{ $r['late'] }} د@endif</span>@if($r['early'])<div class="small" style="color:var(--warn)">خرج مبكراً {{ $r['early'] }} د</div>@endif</td>
            <td>@include('admin.attendance._flags', ['flags' => $r['flags']])@if($r['records']->count() > 2)<span class="muted small">{{ $r['records']->count() }} تسجيلات</span>@endif</td>
            <td>@if($r['user']->phone && in_array($r['status'], ['absent', 'waiting']))<a class="wa-mini" target="_blank" rel="noopener" href="{{ \App\Support\Phone::whatsapp($r['user']->phone, 'السلام عليكم أ. '.$r['user']->name.'، لم يُسجَّل حضورك اليوم في تطبيق المدرسة.') }}">واتساب</a>@endif</td>
        </tr>
    @endforeach
    </tbody>
</table></div>

@perm('attendance', 'edit')
<details class="panel" style="margin-top:16px">
    <summary><b>تسجيل يدوي</b> <span class="muted">— لمن نسي جواله أو تعطّل جهازه</span></summary>
    <form class="inline" method="post" action="{{ route('admin.attendance.manual') }}" style="margin-top:10px">
        @csrf
        <select name="user_id" required aria-label="الاسم"><option value="">الاسم…</option>@foreach($people as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
        <select name="type" aria-label="النوع"><option value="in">حضور</option><option value="out">مغادرة</option></select>
        <input type="date" name="date" value="{{ $date->toDateString() }}" required aria-label="التاريخ">
        <input type="time" name="time" required aria-label="الوقت">
        <input type="text" name="note" required minlength="3" maxlength="500" placeholder="السبب" aria-label="السبب">
        <button class="btn primary" type="submit">تسجيل</button>
    </form>
</details>
@endperm
@endsection
