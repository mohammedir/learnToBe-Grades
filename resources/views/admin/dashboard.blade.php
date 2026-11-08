@extends('layouts.app')
@section('title', 'متابعة الرصد')
@section('module', 'marks')
@section('content')
<div class="page-head">
    <div><h1>متابعة رصد الدرجات</h1><div class="sub">حالة كل كشف حسب الشعبة والمادة، واسم المدرس المكلّف به.</div></div>
    <div class="spacer"></div>
    <a class="btn" href="{{ route('admin.analytics', array_filter($f + ['components' => $chosen])) }}">📊 الرسوم البيانية</a>
    <a class="btn primary" href="{{ route('admin.marks.export-all') }}">تنزيل كل الدرجات (Excel)</a>
</div>

<form class="panel filter-panel" method="get" action="{{ route('admin.dashboard') }}">
    <div class="filter-row">
        <span class="filter-label">عناصر الدرجة:</span>
        <div class="chips">
            @foreach($allComponents as $c)
                <label @class(['on' => in_array($c->id, $componentIds)])><input type="checkbox" name="components[]" value="{{ $c->id }}" @checked(in_array($c->id, $chosen)) onchange="this.form.submit()"> {{ $c->name }}</label>
            @endforeach
        </div>
        @if($chosen)<span class="hint">المتابعة الآن للعناصر المحددة فقط</span>@else<span class="hint">بدون تحديد = كل العناصر</span>@endif
    </div>
    <div class="filter-row">
        <select name="grade" aria-label="الصف" onchange="this.form.submit()"><option value="">كل الصفوف</option>@foreach($grades as $g)<option value="{{ $g->id }}" @selected($f['grade'] == $g->id)>{{ $g->name }}</option>@endforeach</select>
        <select name="subject" aria-label="المادة" onchange="this.form.submit()"><option value="">كل المواد</option>@foreach($allSubjects as $s)<option value="{{ $s->id }}" @selected($f['subject'] == $s->id)>{{ $s->name }}</option>@endforeach</select>
        <select name="teacher" aria-label="المدرس" onchange="this.form.submit()"><option value="">كل المدرسين</option>@foreach($teachers as $t)<option value="{{ $t->id }}" @selected($f['teacher'] == $t->id)>{{ $t->name }}</option>@endforeach</select>
        @if($chosen || array_filter($f))<a class="btn sm" href="{{ route('admin.dashboard') }}">إلغاء التصفية</a>@endif
        <noscript><button class="btn sm" type="submit">تطبيق</button></noscript>
    </div>
</form>

<div class="stats">
    <div class="stat"><b>{{ $stats['cells'] }}%</b><span>نسبة إكمال الإدخال</span></div>
    <div class="stat"><b>{{ $stats['percent'] }}%</b><span>طلاب اكتملت درجاتهم</span></div>
    <div class="stat"><b>{{ number_format($stats['missing']) }}</b><span>درجة لم تُرصد بعد</span></div>
    <div class="stat"><b>{{ $stats['started'] }}/{{ $stats['assignments'] }}</b><span>كشوف بدأ رصدها</span></div>
</div>

@if($byTeacher->isNotEmpty())
<details class="panel teacher-progress" open>
    <summary><h2 style="display:inline">نسبة إكمال الإدخال لكل مدرس</h2> <span class="hint">· الأقل إكمالاً أولاً</span></summary>
    <div class="scroll" style="margin-top:10px">
    <table>
        <thead><tr><th>المدرس</th><th class="c">الكشوف</th><th class="c">مكتملة</th><th style="width:36%">نسبة الإدخال</th><th class="c">درجات ناقصة</th><th></th></tr></thead>
        <tbody>
        @foreach($byTeacher as $row)
            <tr>
                <td><b>{{ $row['teacher']->name }}</b>@if($row['teacher']->phone)<a class="wa-mini" href="{{ \App\Support\Phone::whatsapp($row['teacher']->phone, 'السلام عليكم أستاذ/ة '.$row['teacher']->name.'، تذكير بإكمال رصد الدرجات. نسبة الإدخال الحالية '.$row['percent'].'%.') }}" target="_blank" rel="noopener" title="تذكير عبر واتساب">واتساب</a>@endif</td>
                <td class="c">{{ $row['sheets'] }}</td>
                <td class="c">{{ $row['done'] }}</td>
                <td><div class="inline" style="flex-wrap:nowrap"><div class="bar" style="flex:1"><i style="width:{{ $row['percent'] }}%;background:{{ $row['percent'] >= 100 ? 'var(--ok)' : ($row['percent'] >= 50 ? 'var(--accent)' : 'var(--warn)') }}"></i></div><b style="min-width:44px">{{ $row['percent'] }}%</b></div></td>
                <td class="c">{{ number_format($row['missing']) }}</td>
                <td>@perm('marks', 'edit')<a class="btn sm" href="{{ route('admin.entry.teacher', $row['teacher']) }}">كشوفه</a>@endperm</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</details>
@endif
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
