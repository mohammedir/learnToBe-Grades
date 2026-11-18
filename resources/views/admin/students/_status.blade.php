{{-- تغيير حالة قيد الطالب. المتغير: $st --}}
@if($st->is_active)
<details class="st-status">
    <summary class="btn sm danger">إيقاف / انسحاب</summary>
    <form class="form" method="post" action="{{ route('admin.students.status', $st) }}" style="margin-top:8px;min-width:260px">
        @csrf @method('put')
        <div class="status-choices" role="radiogroup" aria-label="حالة الطالب">
            @foreach(\App\Models\Student::ENROLLMENT as $k => $label)
                @continue($k === 'active')
                <label><input type="radio" name="status" value="{{ $k }}" @checked($loop->index === 1) required> {{ $label }}</label>
            @endforeach
        </div>
        <input type="date" name="date" value="{{ now()->format('Y-m-d') }}" aria-label="تاريخ التوقف">
        <input type="text" name="reason" maxlength="500" placeholder="السبب (اختياري)، مثل: سافر مع أهله" aria-label="السبب">
        <p class="hint" style="margin:0">يختفي من كشوف المدرسين ولا يُحسب في نسبة الإكمال. درجاته تبقى محفوظة ويمكن إعادته في أي وقت.</p>
        <div><button class="btn sm danger" type="submit">تأكيد</button></div>
    </form>
</details>
@else
<details class="st-status">
    <summary class="btn sm primary">إعادة للدراسة</summary>
    <form class="form" method="post" action="{{ route('admin.students.status', $st) }}" style="margin-top:8px;min-width:260px">
        @csrf @method('put')
        <input type="hidden" name="status" value="active">
        <label class="hint" for="sec-{{ $st->id }}">الشعبة التي يعود إليها</label>
        <select id="sec-{{ $st->id }}" name="section_id">
            @foreach(once(fn () => \App\Models\GradeLevel::orderBy('sort_order')->with('sections')->get()) as $g)
                <optgroup label="{{ $g->name }}">@foreach($g->sections as $sec)@if($sec->accepts($st->gender))<option value="{{ $sec->id }}" @selected($st->section_id == $sec->id)>{{ $g->name }} – {{ $sec->short_name }}</option>@endif @endforeach</optgroup>
            @endforeach
        </select>
        <input type="date" name="date" value="{{ now()->format('Y-m-d') }}" aria-label="تاريخ العودة">
        <input type="text" name="reason" maxlength="500" placeholder="ملاحظة (اختياري)" aria-label="ملاحظة">
        <div><button class="btn sm primary" type="submit">إعادته منتظماً</button></div>
    </form>
</details>
@endif
