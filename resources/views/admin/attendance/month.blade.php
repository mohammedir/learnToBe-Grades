@extends('layouts.app')
@section('title', 'التقرير الشهري للحضور')
@section('module', 'attendance')
@section('content')
@include('admin.attendance._tabs')
@php($sym = ['present' => ['✓', 'ok'], 'late' => ['ت', 'late'], 'absent' => ['غ', 'abs'], 'waiting' => ['', ''], 'off' => ['', 'off']])
<div class="page-head">
    <div><h1>حضور {{ $from->locale('ar')->translatedFormat('F Y') }}</h1><div class="sub">{{ $workdays }} يوم دوام حتى الآن · الرموز: ✓ حاضر · ت متأخر · غ غائب</div></div>
    <div class="spacer"></div>
    <form class="inline" method="get"><input type="month" name="month" value="{{ sprintf('%04d-%02d', $y, $m) }}" max="{{ now()->format('Y-m') }}" onchange="this.form.submit()" aria-label="الشهر"></form>
    <a class="btn" href="{{ route('admin.attendance.export', ['month' => sprintf('%04d-%02d', $y, $m)]) }}">⬇ Excel</a>
</div>
<div class="scroll"><table class="att-month">
    <thead><tr>
        <th class="sticky-name">الاسم</th><th class="c">الحضور</th><th class="c">النسبة</th><th class="c">تأخير</th><th class="c">غياب</th><th class="c">مبكر</th><th class="c">بلا مغادرة</th>
        @foreach($days as $d)<th class="c day @unless(\App\Services\AttendanceReport::isWorkday($d, $settings)) is-off @endunless" title="{{ $d->locale('ar')->translatedFormat('l j F') }}">{{ $d->day }}<small>{{ mb_substr(\App\Services\AttendanceReport::DAYS[$d->dayOfWeek], 2, 1) }}</small></th>@endforeach
    </tr></thead>
    <tbody>
    @foreach($rows as $t)
        <tr>
            <td class="sticky-name"><a href="{{ route('admin.attendance.person', ['user' => $t['user'], 'month' => sprintf('%04d-%02d', $y, $m)]) }}"><b>{{ $t['user']->name }}</b></a></td>
            <td class="c tnum">{{ $t['present'] }}/{{ $workdays }}</td>
            <td class="c"><span class="rate {{ $t['rate'] === null ? '' : ($t['rate'] >= 95 ? 'hi' : ($t['rate'] >= 80 ? 'mid' : 'lo')) }}">{{ $t['rate'] === null ? '—' : $t['rate'].'%' }}</span></td>
            <td class="c tnum">{{ $t['late'] }}@if($t['late_minutes'])<small class="muted"> ({{ $t['late_minutes'] }} د)</small>@endif</td>
            <td class="c tnum" @if($t['absent']) style="color:var(--bad);font-weight:700" @endif>{{ $t['absent'] }}</td>
            <td class="c tnum">{{ $t['early'] }}</td>
            <td class="c tnum">{{ $t['no_out'] }}</td>
            @foreach($days as $d)@php($c = $t['cells'][$d->day] ?? 'waiting')<td class="c cell {{ $sym[$c][1] }}">{{ $sym[$c][0] }}</td>@endforeach
        </tr>
    @endforeach
    </tbody>
</table></div>
@endsection
