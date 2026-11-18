@extends('layouts.app')
@section('title', 'حضور '.$user->name)
@section('module', 'attendance')
@section('content')
@include('admin.attendance._tabs')
@php($st = \App\Services\AttendanceReport::STATUS)
<div class="page-head">
    <div><h1>{{ $user->name }}</h1><div class="sub">سجل الحضور · {{ $from->locale('ar')->translatedFormat('F Y') }}</div></div>
    <div class="spacer"></div>
    <form class="inline" method="get"><input type="month" name="month" value="{{ sprintf('%04d-%02d', $y, $m) }}" max="{{ now()->format('Y-m') }}" onchange="this.form.submit()" aria-label="الشهر"></form>
</div>
<div class="scroll"><table class="att-table">
    <thead><tr><th>اليوم</th><th class="c">الحالة</th><th>التسجيلات</th></tr></thead>
    <tbody>
    @foreach($days as $d)
        <tr class="att-{{ $d['status'] }}">
            <td>{{ $d['date']->locale('ar')->translatedFormat('l j/n') }}</td>
            <td class="c"><span class="pill {{ $st[$d['status']][1] }}">{{ $st[$d['status']][0] }}@if($d['late']) {{ $d['late'] }} د@endif</span></td>
            <td>
                @foreach($d['records'] as $r)
                    <div class="rec-line">
                        <b>{{ $r->type === 'in' ? 'حضور' : 'مغادرة' }} <span class="tnum">{{ $r->occurred_at->format('H:i') }}</span></b>
                        <span class="muted small">{{ $r->point?->name }}@if($r->device_at && $r->device_at->format('H:i') !== $r->occurred_at->format('H:i')) · ساعة الجهاز {{ $r->device_at->format('H:i') }}@endif @if($r->note) · {{ $r->note }}@endif</span>
                        @include('admin.attendance._flags', ['flags' => $r->flags()])
                        @perm('attendance', 'edit')<form method="post" action="{{ route('admin.attendance.records.destroy', $r) }}" class="inline-form" onsubmit="return confirm('حذف هذا التسجيل؟')">@csrf @method('delete')<button class="linkbtn danger-link" type="submit">حذف</button></form>@endperm
                    </div>
                @endforeach
            </td>
        </tr>
    @endforeach
    </tbody>
</table></div>
@endsection
