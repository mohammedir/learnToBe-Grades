@extends('layouts.app')
@section('title', 'شعب ومواد '.$teacher->name)
@section('module', 'teachers')
@section('content')
<div class="page-head">
    <div><h1>شعب ومواد: {{ $teacher->name }}</h1><div class="sub">اسم المستخدم: <span dir="ltr">{{ $teacher->username }}</span></div></div>
    <div class="spacer"></div>
    @perm('marks', 'edit')<a class="btn primary" href="{{ route('admin.entry.teacher', $teacher) }}">رصد درجاته</a>@endperm
    <a class="btn" href="{{ route('admin.teachers.index') }}">كل المدرسين</a>
</div>

<div class="grid2">
<div class="panel">
    <h2>الكشوف الحالية ({{ $assignments->count() }})</h2>
    @if($assignments->isEmpty())
        <p style="color:var(--muted)">لم يُكلَّف بأي شعبة بعد. اختر من النموذج المجاور.</p>
    @else
    <div class="scroll"><table>
        <thead><tr><th>المادة</th><th>الشعبة</th><th class="c">الرصد</th><th></th></tr></thead>
        <tbody>
        @foreach($assignments as $a)
            @php($p = $progress[$a->id])
            <tr>
                <td><b>{{ $a->subject->name }}</b></td>
                <td>{{ $a->section->full_name }}</td>
                <td class="c">@perm('marks')<a class="pill {{ $p['students'] && $p['complete'] === $p['students'] ? 'done' : ($p['any'] ? 'part' : 'none') }}" href="{{ route('admin.marks.show', $a) }}">{{ $p['complete'] }}/{{ $p['students'] }}</a>@else<span class="pill {{ $p['students'] && $p['complete'] === $p['students'] ? 'done' : ($p['any'] ? 'part' : 'none') }}">{{ $p['complete'] }}/{{ $p['students'] }}</span>@endperm</td>
 <td>
                    @perm('teachers', 'edit')
                    <form method="post" action="{{ route('admin.assignments.destroy', $a) }}" onsubmit="return confirm('إلغاء تكليف {{ $a->subject->name }} – {{ $a->section->full_name }}؟')">
                        @csrf @method('delete')<button class="btn sm danger" type="submit">إلغاء</button>
                    </form>
                    @endperm
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</div>

@perm('teachers', 'edit')
<div class="panel">
    <h2>إضافة شعب ومواد</h2>
    <form method="post" action="{{ route('admin.teachers.assignments.store', $teacher) }}">
        @csrf
        <div class="field" style="margin-bottom:12px">
            <label>المادة (يمكن اختيار أكثر من مادة)</label>
            <div class="chips">
                @foreach($subjects as $s)
                    <label><input type="checkbox" name="subject_ids[]" value="{{ $s->id }}" @checked(in_array($s->id, old('subject_ids', [])))> {{ $s->name }}</label>
                @endforeach
            </div>
        </div>
        <div class="field">
            <label>الشعب</label>
            <span class="hint">الشعب مأخوذة تلقائياً من كشف الطلاب. لإضافة شعبة جديدة اذهب إلى «الصفوف والشعب».</span>
            @foreach($grades as $g)
                <div class="gradebox">
                    <strong>{{ $g->name }}</strong>
                    <div class="chips">
                        @foreach($g->sections as $sec)
                            <label>
                                <input type="checkbox" name="section_ids[]" value="{{ $sec->id }}" @checked(in_array($sec->id, old('section_ids', [])))>
                                {{ $sec->short_name }} <span style="color:var(--muted)">· {{ $sec->students_count }}</span>
                            </label>
                        @endforeach
                    </div>
                    @php($busy = $subjects->filter(fn ($s) => $g->sections->contains(fn ($sec) => isset($taken[$sec->id.'-'.$s->id]))))
                    @if($busy->isNotEmpty())
                        <div class="taken">
                            مكلّف بها مدرسون آخرون:
                            @foreach($g->sections as $sec)@foreach($subjects as $s)@if(isset($taken[$sec->id.'-'.$s->id])){{ $s->name }} {{ $sec->short_name }} ({{ $taken[$sec->id.'-'.$s->id] }})، @endif @endforeach @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <label class="check" style="margin:8px 0 12px"><input type="checkbox" name="replace" value="1"> إن كان الكشف مكلّفاً به مدرس آخر، انقله لهذا المدرس</label>
        <button class="btn primary" type="submit">إضافة الكشوف المختارة</button>
    </form>
</div>
@endperm
</div>
@endsection
