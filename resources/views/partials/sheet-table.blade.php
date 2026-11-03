{{-- كشف الدرجات: $sheet (GradeSheet)، $editable (bool)، $saveUrl (عند التعديل) --}}
<div class="scroll">
<table @if($editable) data-sheet data-save-url="{{ $saveUrl }}" @endif>
    <thead>
    <tr>
        <th class="c" style="width:48px">م</th>
        <th>اسم الطالب</th>
        @foreach($sheet->components as $c)
            <th @class(['c', 'locked' => $sheet->isLocked($c->id)])>{{ $c->name }}<small>من {{ rtrim(rtrim(number_format($c->max_score, 2, '.', ''), '0'), '.') }}</small>@if($sheet->isLocked($c->id))<span class="lock-note" title="اعتمدها {{ $sheet->locked[$c->id]->approver?->name }} في {{ $sheet->locked[$c->id]->approved_at->format('Y/m/d') }}">🔒 معتمد</span>@endif</th>
        @endforeach
        <th class="c">المجموع<small>من {{ rtrim(rtrim(number_format($sheet->maxTotal(), 2, '.', ''), '0'), '.') }}</small></th>
        @if($noteable ?? false)<th class="c">ملاحظة</th>@endif
    </tr>
    </thead>
    <tbody>
    @php($lastGender = null)
    @forelse($sheet->students as $i => $st)
        @if($st->gender !== $lastGender)
            @php($lastGender = $st->gender)
            <tr class="sep"><td colspan="{{ $sheet->components->count() + (($noteable ?? false) ? 4 : 3) }}">{{ $st->gender === 'male' ? 'الذكور' : 'الإناث' }}</td></tr>
        @endif
        <tr>
            <td class="c muted">{{ $i + 1 }}</td>
            <td>{{ $st->name }}</td>
            @foreach($sheet->components as $c)
                @php($v = $sheet->score($st->id, $c->id))
                <td @class(['mark', 'locked' => $sheet->isLocked($c->id)])>
                    @if($editable && $sheet->isLocked($c->id))
                        <input type="text" value="{{ $v === null ? '' : $v + 0 }}" disabled aria-label="{{ $st->name }} – {{ $c->name }} (معتمد)">
                    @elseif($editable)
                        <input type="text" inputmode="decimal" autocomplete="off"
                               data-student="{{ $st->id }}" data-component="{{ $c->id }}" data-max="{{ $c->max_score }}"
                               value="{{ $v === null ? '' : $v + 0 }}" aria-label="{{ $st->name }} – {{ $c->name }}">
                    @else
                        {{ $v === null ? '—' : $v + 0 }}
                    @endif
                </td>
            @endforeach
            @php($t = $sheet->total($st->id))
            <td id="total-{{ $st->id }}" @class(['total', 'full' => $sheet->isComplete($st->id)])>{{ $t === null ? '' : $t + 0 }}</td>
            @if($noteable ?? false)<td class="c"><button class="btn sm ghost" type="button" data-note-student="{{ $st->id }}" data-note-name="{{ $st->name }}" title="إرسال ملاحظة للطالب">✎ ملاحظة</button></td>@endif
        </tr>
    @empty
        <tr><td colspan="{{ $sheet->components->count() + (($noteable ?? false) ? 4 : 3) }}" class="c muted">لا يوجد طلاب في هذه الشعبة.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
