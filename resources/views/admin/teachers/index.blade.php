@extends('layouts.app')
@section('title', 'المدرسون')
@section('module', 'teachers')
@section('content')
<div class="page-head">
    <div><h1>المدرسون</h1><div class="sub">لكل مدرس اسم مستخدم وكلمة مرور خاصة به، ولا يرى إلا الشعب والمواد المكلّف بها.</div></div>
    <div class="spacer"></div>
    @perm('teachers', 'edit')<a class="btn primary" href="{{ route('admin.teachers.create') }}">إضافة مدرس</a>@endperm
</div>
<div class="scroll">
<table>
    <thead><tr><th>الاسم</th><th>اسم المستخدم</th><th>الجوال</th><th class="c">الكشوف</th><th class="c">الحالة</th><th>آخر دخول</th><th></th></tr></thead>
    <tbody>
    @forelse($teachers as $t)
        <tr>
            <td><b>{{ $t->name }}</b></td>
            <td dir="ltr" style="text-align:right">{{ $t->username }}</td>
            <td class="phones">@if($t->phone)<span class="phone"><a href="tel:{{ $t->phone }}" dir="ltr">{{ $t->phone }}</a><a class="wa" href="{{ \App\Support\Phone::whatsapp($t->phone) }}" target="_blank" rel="noopener">واتساب</a></span>@if(! $t->show_phone_to_students)<small style="color:var(--muted)">مخفي عن الطلاب</small>@endif @else<span style="color:var(--muted)">—</span>@endif</td>
            <td class="c">{{ $t->assignments_count }}</td>
            <td class="c">@if($t->is_active)<span class="pill done">مفعّل</span>@else<span class="pill off">موقوف</span>@endif</td>
            <td class="muted">{{ $t->last_login_at?->format('Y-m-d H:i') ?? 'لم يدخل بعد' }}</td>
            <td class="inline" style="justify-content:flex-end">
                <a class="btn sm primary" href="{{ route('admin.teachers.assignments', $t) }}">الشعب والمواد</a>
                @perm('teachers', 'edit')<a class="btn sm" href="{{ route('admin.teachers.edit', $t) }}">تعديل</a>@endperm
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="c muted">لا يوجد مدرسون بعد. اضغط «إضافة مدرس» للبدء.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
