@extends('student.layout')
@section('title', 'درجاتي')
@section('content')
@php($fmt = fn ($n) => $n === null ? '' : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'))
@php($R = \App\Services\StudentReport::class)

<section class="report-head">
    <div class="who">
        <span class="eyebrow">{{ $title }}</span>
        <h1>{{ $student->name }}</h1>
        <div class="meta-line">
            <span>{{ $student->section->full_name }}</span>
            @if($student->national_id)<span dir="ltr">{{ $student->national_id }}</span>@endif
        </div>
    </div>
    @if($published && $report && ! empty($report->subjects))
        @php($avg = $report->average())
        @php($tone = $avg === null ? 'pending' : $R::tone($R::rating($avg)))
        <div class="avg tone-{{ $tone }}">
            <svg viewBox="0 0 120 120" class="ring" aria-hidden="true">
                <circle cx="60" cy="60" r="52" class="ring-bg"/>
                <circle cx="60" cy="60" r="52" class="ring-fg" pathLength="100" stroke-dasharray="{{ $avg ?? 0 }} 100"/>
            </svg>
            <div class="avg-text">
                <b>{{ $avg === null ? '—' : $fmt($avg) }}<small>{{ $avg === null ? '' : '%' }}</small></b>
                <span>{{ $avg === null ? 'لم يكتمل' : $R::rating($avg) }}</span>
            </div>
        </div>
    @endif
</section>

@if($notes->isNotEmpty())
    <section class="notes-board">
        <div class="section-title"><h2>رسائل من مدرسيك</h2><span class="muted" style="color:var(--muted);font-size:13px">{{ $notes->count() }} رسالة</span></div>
        <div class="notes-grid">
            @foreach($notes as $n)
                <article class="note-card tone-note-{{ $n->type }} @if($loop->index >= 4) more-note @endif">
                    <div class="note-icon" aria-hidden="true">{{ ['praise' => '★', 'note' => 'ℹ', 'alert' => '!'][$n->type] ?? 'ℹ' }}</div>
                    <div class="note-body">
                        <div class="note-meta"><span class="note-type">{{ $n->type_label }}</span>@if($n->subject)<span>{{ $n->subject->name }}</span>@endif<span>{{ $n->created_at->format('Y/m/d') }}</span></div>
                        <p>{{ $n->body }}</p>
                        @if($n->author)<div class="note-from">— أ. {{ $n->author->name }}</div>@endif
                    </div>
                </article>
            @endforeach
        </div>
        @if($notes->count() > 4)<button class="btn sm no-print" type="button" id="more-notes" onclick="document.querySelector('.notes-board').classList.add('all');this.remove()">عرض كل الرسائل ({{ $notes->count() }})</button>@endif
    </section>
@endif

@include('student.partials.quizzes', ['quizzes' => $quizzes])

@if($teachers->isNotEmpty())
<section class="teachers-board">
    <div class="section-title"><h2>مدرسيّ</h2><span style="color:var(--muted);font-size:13px">للتواصل مع مدرس المادة عبر واتساب</span></div>
    <div class="teacher-grid">
        @foreach($teachers as $t)
            <article class="teacher-card">
                <div class="t-avatar" aria-hidden="true">{{ $t['initials'] }}</div>
                <div class="t-info">
                    <b>أ. {{ $t['name'] }}</b>
                    <span>{{ implode('، ', $t['subjects']) }}</span>
                </div>
                @if($t['whatsapp'])
                    <a class="t-wa" href="{{ $t['whatsapp'] }}" target="_blank" rel="noopener" aria-label="مراسلة أ. {{ $t['name'] }} على واتساب">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.8-1.4.1-.2 0-.3 0-.5l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3Z"/></svg>
                        واتساب
                    </a>
                @else
                    <span class="t-none">التواصل عبر المدرسة</span>
                @endif
            </article>
        @endforeach
    </div>
</section>
@endif

@if(! $published)
    <div class="panel empty-state">
        <b>لم تُنشر الدرجات بعد</b>
        <p>تظهر درجاتك هنا عندما تنشرها إدارة المدرسة. افتح البوابة لاحقاً.</p>
    </div>
@elseif(empty($report->subjects))
    <div class="panel empty-state"><b>لا توجد مواد مسجّلة لشعبتك بعد</b></div>
@else
    @php($best = $report->best())
    @php($attention = $report->needsAttention())
    <div class="highlights">
        <div class="hl"><span>المعدل العام</span><b>{{ $avg === null ? '—' : $fmt($avg).'%' }}</b><small>متوسط المواد المكتملة</small></div>
        <div class="hl"><span>المواد المكتملة</span><b>{{ $report->completeCount() }} من {{ count($report->subjects) }}</b><small>{{ $report->completeCount() < count($report->subjects) ? 'بعض المواد قيد الرصد' : 'اكتمل رصد كل المواد' }}</small></div>
        @if($best)<div class="hl"><span>أعلى مادة</span><b>{{ $best['subject'] }}</b><small>{{ $fmt($best['percent']) }}% · {{ $best['rating'] }}</small></div>@endif
    </div>

    @if($attention)
        <div class="note">
            <b>مواد تحتاج اهتماماً أكبر:</b>
            @foreach($attention as $s){{ $s['subject'] }} ({{ $fmt($s['percent']) }}%)@if(! $loop->last)، @endif @endforeach.
            المراجعة المنتظمة والتواصل مع مدرس المادة يساعدان على التحسن.
        </div>
    @endif

    <div class="section-title"><h2>الدرجات حسب المادة</h2><button class="btn sm no-print" type="button" onclick="window.print()">طباعة الكشف</button></div>
    <div class="subjects">
        @foreach($report->subjects as $s)
            @php($tone = $s['complete'] ? $R::tone($s['rating']) : 'pending')
            @php($width = $s['max'] > 0 && $s['total'] !== null ? min(100, $s['total'] / $s['max'] * 100) : 0)
            <article class="subject tone-{{ $tone }}">
                <header>
                    <h3>{{ $s['subject'] }}</h3>
                    <span class="badge">{{ $s['complete'] ? $s['rating'] : ($s['total'] !== null ? 'قيد الرصد' : 'لم تُرصد') }}</span>
                </header>
                <div class="score">
                    <b>{{ $s['total'] === null ? '—' : $fmt($s['total']) }}</b><span>/ {{ $fmt($s['max']) }}</span>
                    @if($s['percent'] !== null)<em>{{ $fmt($s['percent']) }}%</em>@endif
                </div>
                <div class="meter" role="img" aria-label="{{ $s['subject'] }}: {{ $s['total'] === null ? 'لم تُرصد' : $fmt($s['total']).' من '.$fmt($s['max']) }}"><i style="width:{{ $width }}%"></i></div>
                <dl class="parts">
                    @foreach($report->components as $c)
                        @php($v = $s['scores'][$c->id])
                        <div @class(['missing' => $v === null])>
                            <dt>{{ $c->name }}</dt>
                            <dd>{{ $v === null ? '—' : $fmt($v) }}<small> / {{ $fmt($c->max_score) }}</small></dd>
                        </div>
                    @endforeach
                </dl>
            </article>
        @endforeach
    </div>

    <details class="panel table-view">
        <summary>عرض الدرجات في جدول</summary>
        <div class="scroll" style="margin-top:10px">
        <table>
            <thead><tr>
                <th>المادة</th>
                @foreach($report->components as $c)<th class="c">{{ $c->name }}<small>من {{ $fmt($c->max_score) }}</small></th>@endforeach
                <th class="c">المجموع</th><th class="c">النسبة</th><th class="c">التقدير</th>
            </tr></thead>
            <tbody>
            @foreach($report->subjects as $s)
                <tr>
                    <td><b>{{ $s['subject'] }}</b></td>
                    @foreach($report->components as $c)<td class="c">{{ $s['scores'][$c->id] === null ? '—' : $fmt($s['scores'][$c->id]) }}</td>@endforeach
                    <td class="c"><b>{{ $s['total'] === null ? '—' : $fmt($s['total']) }}</b></td>
                    <td class="c">{{ $s['percent'] === null ? '—' : $fmt($s['percent']).'%' }}</td>
                    <td class="c">{{ $s['rating'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </details>

    <div class="scale">
        <span class="tone-excellent"><i></i>ممتاز 90+</span>
        <span class="tone-vgood"><i></i>جيد جداً 80–89</span>
        <span class="tone-good"><i></i>جيد 65–79</span>
        <span class="tone-pass"><i></i>مقبول 50–64</span>
        <span class="tone-weak"><i></i>يحتاج متابعة &lt;50</span>
    </div>
    <p class="fine">«—» تعني أن الدرجة لم تُرصد بعد. المعدل يُحسب من المواد المكتملة فقط. لأي استفسار تواصل مع مدرس المادة أو إدارة المدرسة.</p>
@endif
@endsection
@push('scripts')@include('partials.math')@endpush
