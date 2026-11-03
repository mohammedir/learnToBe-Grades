@extends('layouts.app')
@section('title', 'اعتماد الدرجات')
@section('module', 'approvals')
@section('content')
@php($canEdit = auth()->user()->hasPermission('approvals', 'edit'))
@php($q = array_filter($f))
<div class="page-head">
    <div><h1>اعتماد الدرجات</h1><div class="sub">الخانة المعتمدة 🔒 تُقفل على المدرس فلا يستطيع تعديلها حتى يُفك اعتمادها. اعتمد خانة واحدة، أو عموداً (مثل «الشهري الأول» لكل الكشوف)، أو كشفاً كاملاً، أو كل المعروض.</div></div>
</div>

<div class="stats">
    <div class="stat"><b>{{ $stats['approved'] }}/{{ $stats['cells'] }}</b><span>خانة معتمدة في المعروض</span></div>
    <div class="stat"><b>{{ $assignments->count() }}</b><span>كشف معروض</span></div>
    <div class="stat"><b>{{ $stats['cells'] ? round($stats['approved'] / $stats['cells'] * 100) : 0 }}%</b><span>نسبة الاعتماد</span></div>
</div>

<div class="panel">
    <form class="inline" method="get" action="{{ route('admin.approvals.index') }}">
        <select name="grade" aria-label="الصف" onchange="this.form.submit()"><option value="">كل الصفوف</option>@foreach($grades as $g)<option value="{{ $g->id }}" @selected($f['grade'] == $g->id)>{{ $g->name }}</option>@endforeach</select>
        <select name="section" aria-label="الشعبة" onchange="this.form.submit()"><option value="">كل الشعب</option>@foreach($grades as $g)<optgroup label="{{ $g->name }}">@foreach($g->sections as $s)<option value="{{ $s->id }}" @selected($f['section'] == $s->id)>{{ $g->name }} – {{ $s->short_name }}</option>@endforeach</optgroup>@endforeach</select>
        <select name="subject" aria-label="المادة" onchange="this.form.submit()"><option value="">كل المواد</option>@foreach($subjects as $s)<option value="{{ $s->id }}" @selected($f['subject'] == $s->id)>{{ $s->name }}</option>@endforeach</select>
        <select name="teacher" aria-label="المدرس" onchange="this.form.submit()"><option value="">كل المدرسين</option>@foreach($teachers as $t)<option value="{{ $t->id }}" @selected($f['teacher'] == $t->id)>{{ $t->name }}</option>@endforeach</select>
        @if($q)<a class="btn sm" href="{{ route('admin.approvals.index') }}">إلغاء التصفية</a>@endif
    </form>
</div>

@if($canEdit && $assignments->isNotEmpty())
<div class="panel approve-all">
    <h2>اعتماد جماعي {{ $q ? 'للكشوف المعروضة' : 'لكل الكشوف' }}</h2>
    <form method="post" action="{{ route('admin.approvals.apply') }}" class="inline" id="bulk-form">
        @csrf
        <input type="hidden" name="scope" value="filtered">
        @foreach($q as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
        <span>العناصر:</span>
        @foreach($components as $c)
            <label class="check"><input type="checkbox" name="component_ids[]" value="{{ $c->id }}" checked> {{ $c->name }}</label>
        @endforeach
        <div class="spacer"></div>
        <button class="btn primary" type="submit" name="action" value="approve" onclick="return confirm('اعتماد العناصر المحددة في {{ $assignments->count() }} كشف؟')">🔒 اعتماد</button>
        <button class="btn" type="submit" name="action" value="unapprove" onclick="return confirm('فك اعتماد العناصر المحددة في {{ $assignments->count() }} كشف؟')">🔓 فك الاعتماد</button>
    </form>
    <p class="hint" style="margin:6px 0 0">مثال: لاعتماد «الاختبار الشهري الأول» فقط في كل المدرسة، أبقِ علامته وحده ثم اضغط «اعتماد». استخدم التصفية أعلاه لتطبيقه على صف أو مادة أو مدرس معين.</p>
</div>
@endif

@if($assignments->isEmpty())
    <div class="panel" style="color:var(--muted)">لا توجد كشوف بهذه التصفية.</div>
@else
<form method="post" action="{{ route('admin.approvals.apply') }}" id="cells-form">
    @csrf
    <input type="hidden" name="scope" value="cells">
    @if($canEdit)
    <div class="inline cells-bar">
        <span id="sel-count">لم تحدد خانات</span>
        <div class="spacer"></div>
        <button class="btn sm" type="button" id="sel-clear">إلغاء التحديد</button>
        <button class="btn sm primary" type="submit" name="action" value="approve">🔒 اعتماد المحدد</button>
        <button class="btn sm" type="submit" name="action" value="unapprove">🔓 فك اعتماد المحدد</button>
    </div>
    @endif
    <div class="scroll">
    <table class="approve-table">
        <thead>
        <tr>
            <th>الكشف</th>
            @foreach($components as $c)
                <th class="c">{{ $c->name }}<small>من {{ $c->max_score + 0 }}</small>
                    @if($canEdit)<button type="button" class="col-pick" data-col="{{ $c->id }}" title="تحديد العمود كاملاً">تحديد العمود</button>@endif
                </th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($assignments as $a)
            @php($n = $studentCounts[$a->section_id] ?? 0)
            <tr>
                <td class="sheet-cell">
                    <b>{{ $a->subject->name }}</b> · {{ $a->section->full_name }}
                    <div class="muted" style="color:var(--muted);font-size:12px">أ. {{ $a->teacher->name }} · {{ $n }} طالب
                        @if($canEdit) · <button type="button" class="row-pick linkbtn" style="color:var(--accent)">تحديد الكشف</button>@endif</div>
                </td>
                @foreach($components as $c)
                    @php($key = "{$a->section_id}-{$a->subject_id}-{$c->id}")
                    @php($ap = $approvals[$key] ?? null)
                    @php($done = $filled[$key] ?? 0)
                    <td class="ap-cell {{ $ap ? 'is-approved' : '' }}" data-col="{{ $c->id }}">
                        <label>
                            @if($canEdit)<input type="checkbox" name="cells[]" value="{{ $key }}" aria-label="{{ $a->subject->name }} {{ $a->section->full_name }} – {{ $c->name }}">@endif
                            <span class="ap-state">{{ $ap ? '🔒 معتمد' : 'غير معتمد' }}</span>
                        </label>
                        <span class="ap-fill {{ $n && $done >= $n ? 'full' : '' }}">مرصود {{ $done }}/{{ $n }}</span>
                        @if($ap)<span class="ap-by" title="{{ $ap->approved_at->format('Y/m/d H:i') }}">{{ $ap->approver?->name }} · {{ $ap->approved_at->format('m/d') }}</span>@endif
                    </td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</form>
@endif
@endsection
@push('scripts')
<script>
(function () {
    const form = document.getElementById('cells-form');
    if (!form) return;
    const boxes = () => [...form.querySelectorAll('input[name="cells[]"]')];
    const label = document.getElementById('sel-count');
    const update = () => {
        const n = boxes().filter(b => b.checked).length;
        if (label) label.textContent = n ? 'محدد ' + n + ' خانة' : 'لم تحدد خانات';
        boxes().forEach(b => b.closest('td').classList.toggle('picked', b.checked));
    };
    form.addEventListener('change', update);
    form.querySelectorAll('.col-pick').forEach(btn => btn.addEventListener('click', () => {
        const cells = boxes().filter(b => b.closest('td').dataset.col === btn.dataset.col);
        const all = cells.every(b => b.checked);
        cells.forEach(b => b.checked = !all); update();
    }));
    form.querySelectorAll('.row-pick').forEach(btn => btn.addEventListener('click', () => {
        const cells = [...btn.closest('tr').querySelectorAll('input[name="cells[]"]')];
        const all = cells.every(b => b.checked);
        cells.forEach(b => b.checked = !all); update();
    }));
    document.getElementById('sel-clear')?.addEventListener('click', () => { boxes().forEach(b => b.checked = false); update(); });
    form.addEventListener('submit', (e) => {
        const n = boxes().filter(b => b.checked).length;
        if (!n) { e.preventDefault(); alert('حدد خانة واحدة على الأقل.'); }
    });
})();
</script>
@endpush
