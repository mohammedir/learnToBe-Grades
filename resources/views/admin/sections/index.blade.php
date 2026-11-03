@extends('layouts.app')
@section('title', 'الصفوف والشعب')
@section('module', 'sections')
@section('content')
<div class="page-head">
    <div><h1>الصفوف والشعب</h1><div class="sub">من البستان إلى الخامس: شعبة مختلطة. من السادس فما فوق: شعبة إناث صباحية وشعبة ذكور مسائية، ويوضع كل طالب جديد في شعبته تلقائياً حسب جنسه.</div></div>
    <div class="spacer"></div>
    @perm('students', 'edit')<a class="btn" href="{{ route('admin.students.import') }}">استيراد طلاب من Excel</a>@endperm
</div>
@perm('sections', 'edit')
<div class="grid2">
<div class="panel">
    <h2>إضافة شعبة</h2>
    <form class="form" method="post" action="{{ route('admin.sections.store') }}">
        @csrf
        <div class="field"><label for="grade_level_id">الصف</label>
            <select id="grade_level_id" name="grade_level_id" required>
                @foreach($grades as $g)<option value="{{ $g->id }}" @selected(old('grade_level_id') == $g->id)>{{ $g->name }}</option>@endforeach
            </select></div>
        <div class="inline">
            <div class="field"><label for="name">اسم/رقم الشعبة</label><input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="2" required style="width:120px"></div>
            <div class="field"><label for="gender">نوع الشعبة</label>
                <select id="gender" name="gender"><option value="">مختلطة</option><option value="female">إناث</option><option value="male">ذكور</option></select></div>
            <div class="field"><label for="period">الفترة</label>
                <select id="period" name="period"><option value="">—</option><option>صباحي</option><option>مسائي</option></select></div>
        </div>
        <div><button class="btn primary" type="submit">إضافة الشعبة</button></div>
    </form>
</div>
<div class="panel">
    <h2>إضافة صف جديد</h2>
    <form class="form" method="post" action="{{ route('admin.grades.store') }}">
        @csrf
        <div class="field"><label for="gname">اسم الصف</label><input type="text" id="gname" name="name" placeholder="مثال: الصف الأول" required></div>
        <div><button class="btn" type="submit">إضافة الصف (مع شعبة 1)</button></div>
    </form>
</div>
</div>
@endperm
<div class="scroll">
<table>
    <thead><tr><th>الصف</th><th>الشعبة · النوع · الفترة</th><th></th><th class="c">الطلاب</th><th class="c">المواد المكلّفة</th><th></th></tr></thead>
    <tbody>
    @foreach($grades as $g)
        @foreach($g->sections as $sec)
            <tr>
                <td><b>{{ $g->name }}</b></td>
                <td colspan="2">
                    @perm('sections', 'edit')
                    <form class="inline" method="post" action="{{ route('admin.sections.update', $sec) }}">
                        @csrf @method('put')
                        <input type="text" name="name" value="{{ $sec->name }}" style="width:80px" aria-label="اسم الشعبة">
                        <select name="gender" aria-label="نوع الشعبة"><option value="">مختلطة</option><option value="female" @selected($sec->gender === 'female')>إناث</option><option value="male" @selected($sec->gender === 'male')>ذكور</option></select>
                        <select name="period" aria-label="الفترة"><option value="">—</option><option @selected($sec->period === 'صباحي')>صباحي</option><option @selected($sec->period === 'مسائي')>مسائي</option></select>
                        <button class="btn sm" type="submit">حفظ</button>
                    </form>
                    @else
                    {{ $sec->short_name }} · {{ $sec->gender_label }}
                    @endperm
                </td>
                <td class="c">@perm('students')<a href="{{ route('admin.students.index', ['section' => $sec->id]) }}">{{ $sec->students_count }}</a>@else{{ $sec->students_count }}@endperm</td>
                <td class="c">{{ $sec->assignments_count }}</td>
                <td>
                    @if($sec->students_count == 0 && auth()->user()->hasPermission('sections', 'edit'))
                    <form method="post" action="{{ route('admin.sections.destroy', $sec) }}">@csrf @method('delete')<button class="btn sm danger" type="submit">حذف</button></form>
                    @endif
                </td>
            </tr>
        @endforeach
    @endforeach
    </tbody>
</table>
</div>
@endsection
