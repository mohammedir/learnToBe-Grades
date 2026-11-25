@extends('layouts.app')
@section('title', 'الطلاب غير المنتظمين')
@section('module', 'students')
@section('content')
<div class="page-head">
    <div><h1>الطلاب غير المنتظمين</h1><div class="sub">المتوقفون عن الدراسة والمنسحبون والمنقولون. لا يظهرون للمدرسين في كشوف الرصد ولا يُحسبون في نسب الإكمال، وتبقى درجاتهم محفوظة كاملة؛ عند إعادتهم تعود درجاتهم كما كانت.</div></div>
    <div class="spacer"></div>
    <a class="btn" href="{{ route('admin.students.index') }}">كل الطلاب</a>
</div>

<form class="panel inline" method="get" action="{{ route('admin.students.index') }}">
    <input type="hidden" name="show" value="inactive">
    <select name="status" aria-label="الحالة" onchange="this.form.submit()">
        <option value="">كل الحالات ({{ $inactiveCount }})</option>
        @foreach(\App\Models\Student::ENROLLMENT as $k => $label)@continue($k === 'active')<option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>@endforeach
    </select>
    <input type="search" name="q" value="{{ $q }}" placeholder="بحث بالاسم أو رقم الهوية" aria-label="بحث">
    <button class="btn" type="submit">بحث</button>
</form>

<div class="scroll">
<table>
    <thead><tr><th>الطالب</th><th>آخر شعبة</th><th class="c">الحالة</th><th>منذ</th><th>السبب</th><th>غيّرها</th><th class="c">درجات محفوظة</th><th></th></tr></thead>
    <tbody>
    @forelse($inactive as $st)
        <tr>
            <td>@perm('marks')<a class="stu-link" href="{{ route('admin.students.grades', $st) }}"><b>{{ $st->name }}</b> <span class="muted small">📊</span></a>@else<b>{{ $st->name }}</b>@endperm<div class="muted small" dir="ltr" style="text-align:right">{{ $st->national_id }}</div></td>
            <td>{{ $st->section?->full_name }}</td>
            <td class="c"><span class="pill {{ $st->enrollment_status === 'withdrawn' ? 'off' : 'part' }}">{{ $st->enrollment_label }}</span></td>
            <td class="muted">{{ $st->status_date?->format('Y/m/d') ?? '—' }}</td>
            <td>{{ $st->status_reason ?: '—' }}</td>
            <td class="muted">{{ $st->statusChanger?->name ?? ($st->status_reason === 'لم يرد في ملف الاستيراد' ? 'الاستيراد' : '—') }}</td>
            <td class="c">{{ $st->marks_count }}</td>
            <td>@perm('students', 'edit')@include('admin.students._status', ['st' => $st])@endperm</td>
        </tr>
    @empty
        <tr><td colspan="8" class="c muted">لا يوجد طلاب غير منتظمين.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
