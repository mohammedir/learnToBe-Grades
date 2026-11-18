{{-- تعديل يوم مدرس: $r = نتيجة evaluate، $date --}}
@perm('attendance', 'edit')
@php($uid = 'd'.$r['user']->id.'-'.$date->format('md'))
<details class="day-edit">
    <summary class="btn sm">✎ تعديل</summary>
    <form class="form" method="post" action="{{ route('admin.attendance.day.update', $r['user']) }}">
        @csrf @method('put')
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        <div class="de-times">
            <label>الحضور<input type="time" name="in" value="{{ $r['in']?->occurred_at->format('H:i') }}"></label>
            <label>المغادرة<input type="time" name="out" value="{{ $r['out']?->occurred_at->format('H:i') }}"></label>
        </div>
        <span class="hint">إفراغ الخانة يحذف التسجيل. الوقت الأصلي يبقى محفوظاً ويظهر «عُدّل».</span>
        <label class="check de-exc"><input type="checkbox" name="excused" value="1" @checked($r['excuse']) data-toggle="#n-{{ $uid }}"> {{ $r['in'] ? 'بعذر (لا يُحتسب التأخير/الخروج المبكر)' : 'غائب بعذر (مجاز)' }}</label>
        <textarea id="n-{{ $uid }}" name="excuse_note" rows="2" maxlength="500" placeholder="العذر (اختياري)، مثل: إجازة مرضية بتقرير طبي" aria-label="ملاحظة العذر" @unless($r['excuse']) hidden @endunless>{{ $r['excuse']?->note }}</textarea>
        <input type="text" name="reason" maxlength="500" placeholder="سبب تعديل الوقت (اختياري)" aria-label="سبب التعديل">
        <div><button class="btn sm primary" type="submit">حفظ</button></div>
    </form>
</details>
@endperm
