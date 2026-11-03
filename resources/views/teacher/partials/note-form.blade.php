{{-- نموذج ملاحظة: $assignment، $students، $fixed (طالب محدد أو null) --}}
<form class="form" method="post" action="{{ route('teacher.notes.store', $assignment) }}" style="max-width:none">
    @csrf
    @if($fixed)
        <input type="hidden" name="student_id" value="{{ $fixed->id }}">
    @else
        <div class="field"><label for="note-student">الطالب</label>
            <select id="note-student" name="student_id" required>
                <option value="">اختر الطالب</option>
                @foreach($students as $st)<option value="{{ $st->id }}" @selected(old('student_id') == $st->id)>{{ $st->name }}</option>@endforeach
            </select></div>
    @endif
    <div class="field"><label>نوع الملاحظة</label>
        <div class="chips">
            @foreach(\App\Models\StudentNote::TYPES as $key => [$label, $hint])
                <label class="tone-note-{{ $key }}"><input type="radio" name="type" value="{{ $key }}" @checked(old('type', 'praise') === $key) required> {{ $label }} <span style="color:var(--muted);font-size:12px">· {{ $hint }}</span></label>
            @endforeach
        </div></div>
    <div class="field"><label for="note-body-{{ $fixed?->id ?? 'any' }}">نص الملاحظة</label>
        <textarea id="note-body-{{ $fixed?->id ?? 'any' }}" name="body" rows="3" maxlength="1000" required placeholder="مثال: أحسنت في مشاركتك هذا الأسبوع، استمر!">{{ old('body') }}</textarea></div>
    <div><button class="btn primary" type="submit">إرسال للطالب</button></div>
</form>
