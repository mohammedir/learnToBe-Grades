@extends('layouts.app')
@section('title', 'التقرير الشهري للحضور')
@section('module', 'attendance')
@section('content')
@include('admin.attendance._tabs')
@php($sym = ['present' => ['✓', 'ok'], 'late' => ['ت', 'late'], 'absent' => ['غ', 'abs'], 'excused' => ['ع', 'exc'], 'waiting' => ['', ''], 'off' => ['', 'off']])
<div class="page-head">
    <div><h1>حضور {{ $from->locale('ar')->translatedFormat('F Y') }}</h1><div class="sub">{{ $workdays }} يوم دوام حتى الآن · الرموز: ✓ حاضر · ت متأخر · غ غائب · ع غائب بعذر (مجاز)</div></div>
    <div class="spacer"></div>
    <form class="inline" method="get"><input type="month" name="month" value="{{ sprintf('%04d-%02d', $y, $m) }}" max="{{ now()->format('Y-m') }}" onchange="this.form.submit()" aria-label="الشهر"></form>
    <a class="btn" href="{{ route('admin.attendance.export', ['month' => sprintf('%04d-%02d', $y, $m)]) }}">⬇ Excel</a>
</div>
<div class="scroll"><table class="att-month">
    <thead><tr>
        <th class="sticky-name">الاسم</th><th class="c">الحضور</th><th class="c">النسبة</th><th class="c">تأخير</th><th class="c">غياب</th><th class="c">بعذر</th><th class="c">مبكر</th><th class="c">بلا مغادرة</th>
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
            <td class="c tnum">{{ $t['excused'] }}</td>
            <td class="c tnum">{{ $t['early'] }}</td>
            <td class="c tnum">{{ $t['no_out'] }}</td>
            @foreach($days as $d)@php($c = $t['cells'][$d->day] ?? 'waiting')<td class="c cell {{ $sym[$c][1] }}">{{ $sym[$c][0] }}</td>@endforeach
        </tr>
    @endforeach
    </tbody>
</table></div>

<section class="panel" style="margin-top:16px">
    <h2>الأعذار والإجازات هذا الشهر ({{ $excuses->count() }})</h2>
    <div class="scroll"><table>
        <thead><tr><th>التاريخ</th><th>الاسم</th><th>العذر</th><th>سجّله</th><th></th></tr></thead>
        <tbody>
        @forelse($excuses as $e)
            <tr>
                <td>{{ $e->date->locale('ar')->translatedFormat('l j/n') }}</td>
                <td><a href="{{ route('admin.attendance.person', ['user' => $e->user_id, 'month' => sprintf('%04d-%02d', $y, $m)]) }}">{{ $e->user?->name }}</a></td>
                <td>{{ $e->note ?: 'غائب بعذر (مجاز)' }}</td>
                <td class="muted">{{ $e->creator?->name ?? '—' }} <small>{{ $e->created_at->format('Y/m/d') }}</small></td>
                <td>@perm('attendance', 'edit')<form method="post" action="{{ route('admin.attendance.excuses.destroy', $e) }}" onsubmit="return confirm('إلغاء هذا العذر؟')">@csrf @method('delete')<button class="linkbtn danger-link" type="submit">إلغاء</button></form>@endperm</td>
            </tr>
        @empty
            <tr><td colspan="5" class="c muted">لا توجد أعذار مسجّلة هذا الشهر.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
