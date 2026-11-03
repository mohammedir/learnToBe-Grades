@extends('layouts.app')
@section('title', 'المستخدمون والصلاحيات')
@section('content')
<div class="page-head">
    <div><h1>المستخدمون والصلاحيات</h1><div class="sub">حسابات الإدارة: المدير العام يملك كل الصلاحيات، والمدير أو الموظف يرى ويعدّل الأقسام المحددة له فقط. حسابات المدرسين في صفحة «المدرسون».</div></div>
    <div class="spacer"></div>
    <a class="btn primary" href="{{ route('admin.users.create') }}">إضافة مدير أو موظف</a>
</div>
<div class="scroll">
<table>
    <thead>
    <tr>
        <th>الاسم</th><th>اسم المستخدم</th><th>النوع</th>
        @foreach(\App\Support\Permissions::MODULES as $key => $m)<th class="c">{{ $m[0] }}</th>@endforeach
        <th class="c">الحالة</th><th></th>
    </tr>
    </thead>
    <tbody>
    @foreach($users as $u)
        <tr>
            <td><b>{{ $u->name }}</b>@if($u->is(auth()->user())) <span class="pill none">أنت</span>@endif</td>
            <td dir="ltr" style="text-align:right">{{ $u->username }}</td>
            <td>{{ $u->role_label }}</td>
            @foreach(\App\Support\Permissions::MODULES as $key => $m)
                @php($lvl = $u->permissionLevel($key))
                <td class="c"><span class="pill {{ ['edit' => 'done', 'view' => 'part', 'none' => 'none'][$lvl] }}">{{ ['edit' => 'تعديل', 'view' => 'مشاهدة', 'none' => '—'][$lvl] }}</span></td>
            @endforeach
            <td class="c">@if($u->is_active)<span class="pill done">مفعّل</span>@else<span class="pill off">موقوف</span>@endif</td>
            <td><a class="btn sm" href="{{ route('admin.users.edit', $u) }}">تعديل</a></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection
