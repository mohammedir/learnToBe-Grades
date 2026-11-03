@extends('layouts.app')
@section('title', 'متابعة الرصد')
@section('module', 'marks')
@section('content')
<div class="page-head">
    <div><h1>متابعة رصد الدرجات</h1><div class="sub">حالة كل كشف حسب الشعبة والمادة، واسم المدرس المكلّف به.</div></div>
    <div class="spacer"></div>
    <a class="btn primary" href="{{ route('admin.marks.export-all') }}">تنزيل كل الدرجات (Excel)</a>
</div>
<div class="stats">
    <div class="stat"><b>{{ $stats['percent'] }}%</b><span>نسبة الرصد المكتمل</span></div>
    <div class="stat"><b>{{ $stats['started'] }}/{{ $stats['assignments'] }}</b><span>كشوف بدأ رصدها</span></div>
    <div class="stat"><b>{{ $stats['teachers'] }}</b><span>مدرس</span></div>
    <div class="stat"><b>{{ $stats['students'] }}</b><span>طالب في {{ $stats['sections'] }} شعبة</span></div>
</div>
@if($subjects->isEmpty())
    <div class="panel">لا توجد تكليفات بعد. @perm('teachers', 'edit')ابدأ من <a href="{{ route('admin.teachers.create') }}">إضافة مدرس</a> ثم اختر شعبه وموادّه.@endperm</div>
@else
<div class="legend">
    <span><span class="pill done">مكتمل</span> كل الطلاب مرصودون</span>
    <span><span class="pill part">جزئي</span> بعض الدرجات ناقصة</span>
    <span><span class="pill none">لم يبدأ</span></span>
    <span>— لا يوجد مدرس مكلّف</span>
</div>
<div class="scroll">
<table>
    <thead><tr><th>الشعبة</th>@foreach($subjects as $s)<th class="c">{{ $s->name }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach($sections as $sec)
        <tr>
            <td style="white-space:nowrap"><b>{{ $sec->full_name }}</b> <span class="muted" style="color:var(--muted);font-size:12px">({{ $sec->students_count }})</span></td>
            @foreach($subjects as $s)
                <td class="c">
                    @if($a = $grid[$sec->id][$s->id] ?? null)
                        @php($p = $progress[$a->id])
                        @php($cls = $p['students'] && $p['complete'] === $p['students'] ? 'done' : ($p['any'] ? 'part' : 'none'))
                        <a class="pill {{ $cls }}" href="{{ route('admin.marks.show', $a) }}" title="{{ $a->teacher->name }}">{{ $p['complete'] }}/{{ $p['students'] }}</a>
                        <div style="font-size:11.5px;color:var(--muted);white-space:nowrap">@if($p['approved'])<span title="معتمد {{ $p['approved'] }} من {{ $p['components'] }}">🔒</span> @endif{{ $a->teacher->name }}</div>
                    @else
                        <span style="color:var(--rule)">—</span>
                    @endif
                </td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
</div>
<p style="color:var(--muted);font-size:13px">الرقم = عدد الطلاب الذين رُصدت كل عناصر درجتهم من عدد طلاب الشعبة. اضغط عليه لفتح الكشف.</p>
@endif
@endsection
