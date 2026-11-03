@extends('layouts.app')
@section('title', 'المواد')
@section('module', 'subjects')
@section('content')
<div class="page-head"><div><h1>المواد الدراسية</h1><div class="sub">تظهر هذه المواد عند تكليف المدرسين. الترتيب يحدد ترتيب الأعمدة في المتابعة وملف Excel.</div></div></div>
@perm('subjects', 'edit')
<div class="panel">
    <form class="inline" method="post" action="{{ route('admin.subjects.store') }}">
        @csrf
        <input type="text" name="name" placeholder="اسم المادة الجديدة" required aria-label="اسم المادة">
        <button class="btn primary" type="submit">إضافة مادة</button>
    </form>
</div>
@endperm
<div class="scroll">
<table>
    <thead><tr><th>المادة</th><th class="c">الترتيب</th><th class="c">عدد الكشوف</th><th></th></tr></thead>
    <tbody>
    @foreach($subjects as $s)
        <tr>
            <td colspan="2">
                @perm('subjects', 'edit')
                <form class="inline" method="post" action="{{ route('admin.subjects.update', $s) }}">
                    @csrf @method('put')
                    <input type="text" name="name" value="{{ $s->name }}" aria-label="اسم المادة">
                    <input type="number" name="sort_order" value="{{ $s->sort_order }}" min="0" style="width:80px" aria-label="الترتيب">
                    <button class="btn sm" type="submit">حفظ</button>
                </form>
                @else
                <b>{{ $s->name }}</b>
                @endperm
            </td>
            <td class="c">{{ $s->assignments_count }}</td>
            <td>@if($s->assignments_count == 0 && auth()->user()->hasPermission('subjects', 'edit'))<form method="post" action="{{ route('admin.subjects.destroy', $s) }}">@csrf @method('delete')<button class="btn sm danger" type="submit">حذف</button></form>@endif</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection
