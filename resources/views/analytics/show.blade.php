@extends('layouts.app')
@section('title', 'الرسوم البيانية للدرجات')
@section('content')
@php($fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.'))
@php($route = $mode === 'admin' ? 'admin.analytics' : 'teacher.analytics')
<div class="page-head">
    <div><h1>مستوى الدرجات</h1><div class="sub">{{ $scopeLabel }} · {{ $sheets }} كشف · العناصر: {{ $data['componentsLabel'] ?: '—' }}</div></div>
    <div class="spacer"></div>
    @if($mode === 'admin')<a class="btn" href="{{ route('admin.dashboard', array_filter(['grade' => $f['grade'], 'subject' => $f['subject'], 'teacher' => $f['teacher'], 'components' => $chosen])) }}">متابعة الرصد</a>@endif
</div>

<form class="panel filter-panel" method="get" action="{{ route($route) }}">
    <div class="filter-row">
        @if($mode === 'admin')
            <select name="grade" aria-label="الصف" onchange="this.form.submit()"><option value="">كل الصفوف</option>@foreach($grades as $g)<option value="{{ $g->id }}" @selected($f['grade'] == $g->id)>{{ $g->name }}</option>@endforeach</select>
            <select name="section" aria-label="الشعبة" onchange="this.form.submit()"><option value="">كل الشعب</option>@foreach($grades as $g)<optgroup label="{{ $g->name }}">@foreach($g->sections as $s)<option value="{{ $s->id }}" @selected($f['section'] == $s->id)>{{ $g->name }} – {{ $s->short_name }}</option>@endforeach</optgroup>@endforeach</select>
            <select name="subject" aria-label="المادة" onchange="this.form.submit()"><option value="">كل المواد</option>@foreach($subjects as $s)<option value="{{ $s->id }}" @selected($f['subject'] == $s->id)>{{ $s->name }}</option>@endforeach</select>
            <select name="teacher" aria-label="المدرس" onchange="this.form.submit()"><option value="">كل المدرسين</option>@foreach($teachers as $t)<option value="{{ $t->id }}" @selected($f['teacher'] == $t->id)>{{ $t->name }}</option>@endforeach</select>
            @if(array_filter($f) || $chosen)<a class="btn sm" href="{{ route($route) }}">مستوى المدرسة كاملة</a>@endif
        @else
            <select name="assignment" aria-label="الكشف" onchange="this.form.submit()"><option value="">كل كشوفي</option>@foreach($mine as $a)<option value="{{ $a->id }}" @selected($assignmentId == $a->id)>{{ $a->subject->name }} – {{ $a->section->full_name }}</option>@endforeach</select>
        @endif
    </div>
    <div class="filter-row">
        <span class="filter-label">عناصر الدرجة:</span>
        <div class="chips">
            @foreach($allComponents as $c)
                <label @class(['on' => in_array($c->id, $componentIds)])><input type="checkbox" name="components[]" value="{{ $c->id }}" @checked(in_array($c->id, $chosen)) onchange="this.form.submit()"> {{ $c->name }}</label>
            @endforeach
        </div>
        <span class="hint">{{ $chosen ? 'النسب محسوبة من العناصر المحددة فقط' : 'بدون تحديد = المجموع الكلي' }}</span>
    </div>
</form>

@if(! $data['n'])
    <div class="panel" style="text-align:center;color:var(--muted);padding-block:30px">لا توجد درجات مكتملة بعد لهذه التصفية. تظهر الرسوم عندما تُرصد كل العناصر المحددة لطالب واحد على الأقل.@if($data['pending']) ({{ $data['pending'] }} طالب×مادة لم تكتمل درجاتهم بعد)@endif</div>
@else
<div class="stats">
    <div class="stat"><b>{{ $fmt($data['mean']) }}%</b><span>المتوسط</span></div>
    <div class="stat"><b>{{ $fmt($data['median']) }}%</b><span>الوسيط</span></div>
    <div class="stat"><b>{{ $data['pass'] }}%</b><span>نسبة النجاح (50% فأكثر)</span></div>
    <div class="stat"><b>{{ $data['excellent'] }}%</b><span>ممتاز (90% فأكثر)</span></div>
    <div class="stat"><b>{{ number_format($data['n']) }}</b><span>نتيجة محسوبة@if($data['pending']) · {{ $data['pending'] }} غير مكتملة @endif</span></div>
</div>

<div class="chart-grid">
    <section class="panel chart-card wide">
        <h2>منحنى توزيع الدرجات</h2>
        <p class="hint">عدد النتائج في كل فئة نسبة مئوية. أعلى درجة {{ $fmt($data['max']) }}% وأقل درجة {{ $fmt($data['min']) }}%@if($data['stdev']) · الانحراف المعياري {{ $fmt($data['stdev']) }}@endif.</p>
        <div class="chart-box"><canvas id="ch-dist" role="img" aria-label="توزيع الدرجات على فئات النسب"></canvas></div>
    </section>
    <section class="panel chart-card">
        <h2>التقديرات</h2>
        <div class="chart-box"><canvas id="ch-rating" role="img" aria-label="عدد الطلاب في كل تقدير"></canvas></div>
    </section>
    <section class="panel chart-card">
        <h2>متوسط كل عنصر درجة</h2>
        <div class="chart-box"><canvas id="ch-comp" role="img" aria-label="متوسط نسبة كل عنصر"></canvas></div>
    </section>
    @if($data['bySubject']->count() > 1)
    <section class="panel chart-card">
        <h2>متوسط المواد</h2>
        <div class="chart-box tall" style="--rows: {{ $data['bySubject']->count() }}"><canvas id="ch-subject" role="img" aria-label="متوسط كل مادة"></canvas></div>
    </section>
    @endif
    @if($data['bySection']->count() > 1)
    <section class="panel chart-card">
        <h2>متوسط الشعب</h2>
        <div class="chart-box tall" style="--rows: {{ $data['bySection']->count() }}"><canvas id="ch-section" role="img" aria-label="متوسط كل شعبة"></canvas></div>
    </section>
    @endif
    @if($data['byGender']->count() > 1)
    <section class="panel chart-card">
        <h2>الذكور والإناث</h2>
        <div class="chart-box short"><canvas id="ch-gender" role="img" aria-label="متوسط الذكور والإناث"></canvas></div>
    </section>
    @endif
</div>

<details class="panel">
    <summary><b>عرض البيانات في جداول</b></summary>
    <div class="grid2" style="margin-top:10px">
        <div class="scroll"><table><thead><tr><th>الفئة</th><th class="c">عدد النتائج</th></tr></thead><tbody>
            @foreach($data['bins'] as $i => $c)<tr><td dir="ltr" style="text-align:right">{{ $i * 10 }}–{{ $i === 9 ? 100 : $i * 10 + 9 }}%</td><td class="c">{{ $c }}</td></tr>@endforeach
        </tbody></table></div>
        <div class="scroll"><table><thead><tr><th>المادة</th><th class="c">المتوسط</th><th class="c">عدد</th></tr></thead><tbody>
            @foreach($data['bySubject'] as $name => $v)<tr><td>{{ $name }}</td><td class="c">{{ $fmt($v['avg']) }}%</td><td class="c">{{ $v['n'] }}</td></tr>@endforeach
        </tbody></table></div>
    </div>
</details>

@if($data['weak']->isNotEmpty())
<details class="panel" @if($mode === 'teacher') open @endif>
    <summary><b>طلاب يحتاجون متابعة (أقل من 50%)</b> <span class="pill off">{{ $data['weak']->count() }}</span></summary>
    <div class="scroll" style="margin-top:10px"><table>
        <thead><tr><th>الطالب</th><th>المادة</th><th>الشعبة</th>@if($mode === 'admin')<th>المدرس</th>@endif<th class="c">النسبة</th></tr></thead>
        <tbody>@foreach($data['weak'] as $w)<tr><td><b>{{ $w['student'] }}</b></td><td>{{ $w['subject'] }}</td><td>{{ $w['section'] }}</td>@if($mode === 'admin')<td>{{ $w['teacher'] }}</td>@endif<td class="c"><span class="pill off">{{ $fmt($w['percent']) }}%</span></td></tr>@endforeach</tbody>
    </table></div>
</details>
@endif
@endif
@endsection
@if($data['n'])
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (!window.Chart) return;
    const D = {!! json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    const css = getComputedStyle(document.documentElement);
    const ink = css.getPropertyValue('--ink').trim() || '#18212F';
    const muted = css.getPropertyValue('--muted').trim() || '#5B6678';
    const grid = '#E6EAF0';
    const S1 = '#2F5DA8', S1soft = 'rgba(47,93,168,.16)', S2 = '#C2410C';
    Chart.defaults.font.family = '"IBM Plex Sans Arabic", Tahoma, sans-serif';
    Chart.defaults.font.size = 13;
    Chart.defaults.color = muted;
    const tip = { rtl: true, textDirection: 'rtl', backgroundColor: '#18212F', padding: 10, cornerRadius: 8, displayColors: false };
    const hbar = (id, rows, label) => {
        const el = document.getElementById(id); if (!el || !rows.length) return;
        new Chart(el, {
            type: 'bar',
            data: { labels: rows.map(r => r.label), datasets: [{ data: rows.map(r => r.avg), backgroundColor: S1, borderRadius: 4, borderSkipped: false, barThickness: 16 }] },
            options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { ...tip, callbacks: { label: (c) => 'المتوسط ' + c.parsed.x + '% · ' + rows[c.dataIndex].n + ' نتيجة' } } },
                scales: { x: { reverse: true, min: 0, max: 100, grid: { color: grid }, ticks: { callback: v => v + '%' } }, y: { position: 'right', grid: { display: false }, ticks: { color: ink } } } },
        });
    };

    // منحنى التوزيع: أعمدة الفئات + منحنى ناعم لنفس القيم (محور واحد)
    const binLabels = D.bins.map((_, i) => i === 9 ? '90–100' : (i * 10) + '–' + (i * 10 + 9));
    new Chart(document.getElementById('ch-dist'), {
        data: { labels: binLabels, datasets: [
            { type: 'bar', label: 'عدد النتائج', data: D.bins, backgroundColor: S1soft, hoverBackgroundColor: 'rgba(47,93,168,.32)', borderRadius: 4, borderSkipped: 'bottom', categoryPercentage: .92, barPercentage: .96 },
            { type: 'line', label: 'المنحنى', data: D.bins, borderColor: S1, borderWidth: 2, tension: .4, pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: '#fff', pointBorderColor: S1, pointBorderWidth: 2, fill: false },
        ] },
        options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false }, tooltip: { ...tip, callbacks: { title: (i) => 'النسبة ' + i[0].label + '%', label: (c) => c.datasetIndex === 0 ? c.parsed.y + ' نتيجة' : null } } },
            scales: { x: { grid: { display: false }, title: { display: true, text: 'النسبة المئوية' } }, y: { beginAtZero: true, grid: { color: grid }, ticks: { precision: 0 }, title: { display: true, text: 'عدد النتائج' } } } },
    });

    // التقديرات (مرتبة من الأعلى للأدنى)
    const rLabels = Object.keys(D.ratings), rVals = Object.values(D.ratings), total = rVals.reduce((a, b) => a + b, 0);
    new Chart(document.getElementById('ch-rating'), {
        type: 'bar',
        data: { labels: rLabels, datasets: [{ data: rVals, backgroundColor: S1, borderRadius: 4, borderSkipped: false, barThickness: 18 }] },
        options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { ...tip, callbacks: { label: (c) => c.parsed.x + ' (' + (total ? Math.round(c.parsed.x / total * 100) : 0) + '%)' } } },
            scales: { x: { reverse: true, beginAtZero: true, grid: { color: grid }, ticks: { precision: 0 } }, y: { position: 'right', grid: { display: false }, ticks: { color: ink } } } },
    });

    hbar('ch-comp', D.comp.filter(c => c.avg !== null).map(c => ({ label: c.name, avg: c.avg, n: c.n })));
    hbar('ch-subject', D.subject);
    hbar('ch-section', D.section);

    const g = document.getElementById('ch-gender');
    if (g) {
        const keys = Object.keys(D.gender), names = { male: 'الذكور', female: 'الإناث' };
        new Chart(g, {
            type: 'bar',
            data: { labels: keys.map(k => names[k] || k), datasets: [{ data: keys.map(k => D.gender[k]), backgroundColor: keys.map(k => k === 'female' ? S2 : S1), borderRadius: 4, borderSkipped: false, barThickness: 22 }] },
            options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { ...tip, callbacks: { label: (c) => 'المتوسط ' + c.parsed.x + '%' } } },
                scales: { x: { reverse: true, min: 0, max: 100, grid: { color: grid }, ticks: { callback: v => v + '%' } }, y: { position: 'right', grid: { display: false }, ticks: { color: ink, font: { weight: '600' } } } } },
        });
    }
})();
</script>
@endpush
@endif
