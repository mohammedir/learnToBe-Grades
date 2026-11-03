@extends('layouts.app')
@section('title', 'الطلاب')
@section('module', 'students')
@section('content')
<div class="page-head">
    <div><h1>الطلاب</h1><div class="sub">اختر شعبة لعرض طلابها، أو ابحث بالاسم أو رقم الهوية.</div></div>
    <div class="spacer"></div>
    @perm('students', 'edit')<a class="btn" href="{{ route('admin.students.import') }}">استيراد من Excel</a>@endperm
</div>
<div class="panel">
    <form class="inline" method="get" action="{{ route('admin.students.index') }}">
        <select name="section" aria-label="الشعبة" onchange="this.form.submit()">
            <option value="">اختر الشعبة</option>
            @foreach($grades as $g)
                <optgroup label="{{ $g->name }}">
                    @foreach($g->sections as $sec)<option value="{{ $sec->id }}" @selected($section?->id == $sec->id)>{{ $g->name }} – {{ $sec->short_name }}</option>@endforeach
                </optgroup>
            @endforeach
        </select>
        <span style="color:var(--muted)">أو</span>
        <input type="search" name="q" value="{{ $q }}" placeholder="بحث بالاسم أو الهوية أو الجوال" aria-label="بحث">
        <button class="btn" type="submit">بحث</button>
    </form>
</div>

@if($section || $q !== '')
@php($canEdit = auth()->user()->hasPermission('students', 'edit'))
@if($canEdit)
<form method="post" action="{{ route('admin.students.move') }}" id="move-form">
    @csrf
    <div class="panel inline">
        <b>{{ $section ? $section->full_name : 'نتائج البحث' }}: {{ $students->count() }} طالب</b>
        <div class="spacer"></div>
        <span>نقل المحدَّدين إلى</span>
        <select name="target_section_id" aria-label="الشعبة الهدف">
            <option value="">اختر الشعبة</option>
            @foreach($grades as $g)<optgroup label="{{ $g->name }}">@foreach($g->sections as $sec)<option value="{{ $sec->id }}">{{ $g->name }} – {{ $sec->short_name }}</option>@endforeach</optgroup>@endforeach
        </select>
        <button class="btn primary" type="submit">نقل</button>
    </div>
</form>
@else
<div class="panel"><b>{{ $section ? $section->full_name : 'نتائج البحث' }}: {{ $students->count() }} طالب</b></div>
@endif
<div class="scroll">
<table>
    <thead><tr><th class="c">@if($canEdit)<input type="checkbox" aria-label="تحديد الكل" onclick="document.querySelectorAll('.pick').forEach(c => c.checked = this.checked)">@endif</th><th>اسم الطالب</th><th>الشعبة</th><th class="c">الجنس</th><th>رقم الهوية</th><th>الجوال</th><th>تاريخ الميلاد</th><th></th></tr></thead>
    <tbody>
    @forelse($students as $st)
        <tr>
            <td class="c">@if($canEdit)<input type="checkbox" class="pick" name="student_ids[]" value="{{ $st->id }}" form="move-form" aria-label="{{ $st->name }}">@endif</td>
            <td><b>{{ $st->name }}</b></td>
            <td>{{ $st->section->full_name }}</td>
            <td class="c">{{ $st->gender_label }}</td>
            <td dir="ltr" style="text-align:right">{{ $st->national_id }}</td>
            <td class="phones">
                @foreach(array_filter([$st->phone, $st->phone2]) as $ph)
                    <span class="phone"><a href="tel:{{ $ph }}" dir="ltr">{{ $ph }}</a><a class="wa" href="{{ \App\Support\Phone::whatsapp($ph) }}" target="_blank" rel="noopener" title="واتساب" aria-label="واتساب {{ $ph }}">واتساب</a></span>
                @endforeach
                @if(! $st->phone)<span style="color:var(--muted)">—</span>@endif
            </td>
            <td>{{ $st->birth_date?->format('Y-m-d') }}</td>
            <td>
                @if($canEdit)
                <details>
                    <summary class="btn sm">تعديل</summary>
                    <form class="form" method="post" action="{{ route('admin.students.update', $st) }}" style="margin-top:8px;min-width:260px">
                        @csrf @method('put')
                        <input type="text" name="name" value="{{ $st->name }}" aria-label="الاسم" required>
                        <select name="section_id" aria-label="الشعبة">@foreach($grades as $g)@foreach($g->sections as $sec)<option value="{{ $sec->id }}" @selected($st->section_id == $sec->id)>{{ $g->name }} – {{ $sec->short_name }}</option>@endforeach @endforeach</select>
                        <select name="gender" aria-label="الجنس"><option value="male" @selected($st->gender === 'male')>ذكر</option><option value="female" @selected($st->gender === 'female')>أنثى</option></select>
                        <input type="text" name="national_id" value="{{ $st->national_id }}" dir="ltr" aria-label="رقم الهوية">
                        <input type="tel" name="phone" value="{{ $st->phone }}" dir="ltr" placeholder="الجوال 05xxxxxxxx" aria-label="رقم الجوال">
                        <input type="tel" name="phone2" value="{{ $st->phone2 }}" dir="ltr" placeholder="جوال إضافي (اختياري)" aria-label="الجوال الإضافي">
                        <input type="date" name="birth_date" value="{{ $st->birth_date?->format('Y-m-d') }}" aria-label="تاريخ الميلاد">
                        <div class="inline"><button class="btn sm primary" type="submit">حفظ</button></div>
                    </form>
                    <form method="post" action="{{ route('admin.students.destroy', $st) }}" style="margin-top:6px" onsubmit="return confirm('حذف الطالب من القوائم؟')">@csrf @method('delete')<button class="btn sm danger" type="submit">حذف الطالب</button></form>
                </details>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="8" class="c muted">لا يوجد طلاب.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endif

@perm('students', 'edit')
<div class="panel" style="margin-top:16px">
    <h2>إضافة طالب</h2>
    <form class="inline" method="post" action="{{ route('admin.students.store') }}">
        @csrf
        <input type="text" name="name" placeholder="الاسم الرباعي" required aria-label="الاسم">
        <select name="section_id" required aria-label="الشعبة">@foreach($grades as $g)<optgroup label="{{ $g->name }}">@foreach($g->sections as $sec)<option value="{{ $sec->id }}" @selected($section?->id == $sec->id)>{{ $g->name }} – {{ $sec->short_name }}</option>@endforeach</optgroup>@endforeach</select>
        <select name="gender" aria-label="الجنس"><option value="male">ذكر</option><option value="female">أنثى</option></select>
        <input type="text" name="national_id" placeholder="رقم الهوية" dir="ltr" aria-label="رقم الهوية">
        <input type="tel" name="phone" placeholder="الجوال 05xxxxxxxx" dir="ltr" aria-label="رقم الجوال">
        <input type="date" name="birth_date" aria-label="تاريخ الميلاد">
        <button class="btn primary" type="submit">إضافة</button>
    </form>
</div>
@endperm
@endsection
