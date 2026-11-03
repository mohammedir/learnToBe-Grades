@extends('student.layout')
@section('title', $quiz->title)
@section('content')
@php($fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'))
<div class="page-head">
    <div><h1>{{ $quiz->title }}</h1><div class="sub">{{ $quiz->assignment->subject->name }} · {{ $quiz->questions->count() }} سؤال · {{ $fmt($quiz->maxScore()) }} درجة @if($quiz->duration_minutes)· {{ $quiz->duration_minutes }} دقيقة @endif</div></div>
    <div class="spacer"></div>
    <a class="btn no-print" href="{{ route('student.home') }}">رجوع</a>
</div>

@if($attempt && $attempt->submitted_at)
    @if(! $quiz->results_published)
        {{-- سلّم والنتيجة لم تُنشر بعد --}}
        <div class="quiz-wait">
            <div class="sus-icon" style="background:var(--ok-bg);color:var(--ok)" aria-hidden="true">✓</div>
            <h2>تم تسليم الاختبار بنجاح</h2>
            <p>سلّمت في {{ $attempt->submitted_at->format('Y/m/d H:i') }}. ستظهر نتيجتك هنا عندما ينشرها المدرس.</p>
        </div>
    @else
        @php($pct = $attempt->max_score > 0 ? round($attempt->score / $attempt->max_score * 100) : 0)
        @php($tone = \App\Services\StudentReport::tone(\App\Services\StudentReport::rating($pct)))
        <div class="quiz-result tone-{{ $tone }}">
            <div><span>نتيجتك</span><b>{{ $fmt($attempt->score) }}<small> / {{ $fmt($attempt->max_score) }}</small></b></div>
            <div class="pct">{{ $pct }}%</div>
            <div class="msg">{{ $pct >= 90 ? 'ممتاز! أداء رائع.' : ($pct >= 65 ? 'أحسنت، استمر في التقدّم.' : 'راجع الأسئلة التي أخطأت فيها وستتحسّن.') }}
                @if($attempt->needs_review)<br><small>بعض الإجابات النصية ما زالت قيد التصحيح، وقد ترتفع درجتك.</small>@endif
                @if($attempt->late)<br><small>سُلِّم بعد انتهاء الوقت.</small>@endif</div>
        </div>
        @if($quiz->show_answers)
            <h2>مراجعة الإجابات</h2>
            @foreach($quiz->questions as $i => $qq)
                @php($ans = $attempt->answers[$qq->id] ?? null)
                @php($got = $attempt->awarded[$qq->id] ?? null)
                @php($cls = $got === null ? 'pending' : ($got >= $qq->points ? 'right' : ($got > 0 ? 'partial' : 'wrong')))
                <div class="q-review {{ $cls }}">
                    <div class="inline"><span class="q-n">{{ $i + 1 }}</span><div class="spacer"></div>
                        <span class="pill {{ ['right' => 'done', 'partial' => 'part', 'wrong' => 'off', 'pending' => 'none'][$cls] }}">{{ ['right' => 'صحيحة', 'partial' => 'صحيحة جزئياً', 'wrong' => 'خاطئة', 'pending' => 'قيد التصحيح'][$cls] }} · {{ $got === null ? '—' : $fmt($got) }}/{{ $fmt($qq->points) }}</span></div>
                    <div class="q-text">{!! nl2br(e($qq->body)) !!}</div>
                    @if($qq->imageUrl())<img class="q-img" src="{{ $qq->imageUrl() }}" alt="صورة السؤال" loading="lazy">@endif
                    @if(in_array($qq->type, ['mcq', 'tf']))
                        <ul>
                            @foreach($qq->choices() as $key => $text)
                                <li @class(['is-correct' => (string) $key === (string) $qq->correct, 'is-mine' => (string) $key === (string) $ans])>
                                    <span>{{ $text }}</span>
                                    @if((string) $key === (string) $qq->correct)<small>✓ الإجابة الصحيحة</small>@endif
                                    @if((string) $key === (string) $ans && $cls !== 'right')<small>✗ إجابتك</small>@endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="rv-grid">
                            <div><span>إجابتك</span><div class="rv-ans">{{ $ans ?? 'لم تُجب' }}@if($qq->type === 'number' && !empty($qq->meta['unit']) && $ans !== null) <span dir="ltr">{{ $qq->meta['unit'] }}</span>@endif</div></div>
                            <div><span>{{ $qq->type === 'text' ? 'نموذج الإجابة' : 'الإجابة الصحيحة' }}</span><div class="rv-ans correct">{{ $qq->correctText() ?: '—' }}</div></div>
                        </div>
                    @endif
                </div>
            @endforeach
        @else
            <p style="color:var(--muted)">سيعرض المدرس الإجابات الصحيحة في الحصة.</p>
        @endif
    @endif

@elseif(! $attempt)
    <div class="panel quiz-intro">
        @if($quiz->instructions)<div class="q-text" style="margin-bottom:10px">{!! nl2br(e($quiz->instructions)) !!}</div>@endif
        <ul>
            <li>عدد الأسئلة: <b>{{ $quiz->questions->count() }}</b>، والدرجة الكاملة <b>{{ $fmt($quiz->maxScore()) }}</b>.</li>
            @if($quiz->duration_minutes)<li>مدة الحل <b>{{ $quiz->duration_minutes }} دقيقة</b> تبدأ عند الضغط على «ابدأ»، ويُسلَّم الاختبار تلقائياً عند انتهائها.</li>@endif
            @if($quiz->closes_at)<li>آخر موعد للتسليم: <b>{{ $quiz->closes_at->format('Y/m/d H:i') }}</b>.</li>@endif
            @if($quiz->questions->contains('type', 'text'))<li>بعض الأسئلة إجابتها نصية يصححها المدرس.</li>@endif
            <li>يمكنك التسليم مرة واحدة فقط، فراجع إجاباتك قبل التسليم.</li>
        </ul>
        @if($state === 'open')
            <form method="post" action="{{ route('student.quiz.start', $quiz) }}">@csrf<button class="btn primary big" type="submit">ابدأ الاختبار</button></form>
        @elseif($state === 'upcoming')
            <div class="alert warn">يبدأ الاختبار في {{ $quiz->opens_at->format('Y/m/d H:i') }}.</div>
        @else
            <div class="alert bad">انتهى وقت هذا الاختبار.</div>
        @endif
    </div>

@else
    @php($deadline = $attempt->deadline())
    <div class="take-bar" id="take-bar">
        <span id="answered">—</span>
        <div class="progress" aria-hidden="true"><i id="progress-fill"></i></div>
        @if($deadline)<span class="timer" id="timer" data-deadline="{{ $deadline->getTimestamp() * 1000 }}" data-now="{{ now()->getTimestamp() * 1000 }}">⏱ <b>--:--</b></span>@endif
    </div>
    <form method="post" action="{{ route('student.quiz.submit', $quiz) }}" id="quiz-take" data-key="quiz-{{ $quiz->id }}-{{ $student->id }}">
        @csrf
        @foreach($quiz->questions as $i => $qq)
            <fieldset class="q-take" data-q>
                <legend><span class="q-n">{{ $i + 1 }}</span> <span class="pts">{{ $fmt($qq->points) }} {{ $qq->points == 1 ? 'درجة' : 'درجات' }}</span></legend>
                <div class="q-text">{!! nl2br(e($qq->body)) !!}</div>
                @if($qq->imageUrl())
                    <a href="{{ $qq->imageUrl() }}" target="_blank" rel="noopener"><img class="q-img" src="{{ $qq->imageUrl() }}" alt="صورة السؤال {{ $i + 1 }}" loading="lazy"></a>
                @endif
                @switch($qq->type)
                    @case('mcq')
                    @case('tf')
                        <div class="choices {{ $qq->type }}">
                            @foreach($qq->choices() as $key => $text)
                                <label class="choice"><input type="radio" name="answers[{{ $qq->id }}]" value="{{ $key }}"><span class="c-text">{{ $text }}</span></label>
                            @endforeach
                        </div>
                        @break
                    @case('number')
                        <div class="num-answer">
                            <input type="text" inputmode="decimal" dir="ltr" name="answers[{{ $qq->id }}]" autocomplete="off" placeholder="اكتب الرقم" aria-label="إجابة السؤال {{ $i + 1 }}">
                            @if(!empty($qq->meta['unit']))<span class="unit" dir="ltr">{{ $qq->meta['unit'] }}</span>@endif
                        </div>
                        <span class="hint">يمكنك كتابة كسر عشري مثل 9.8، أو قوة عشرة مثل 3×10^8.</span>
                        @break
                    @default
                        <textarea name="answers[{{ $qq->id }}]" rows="4" maxlength="3000" placeholder="اكتب إجابتك هنا…" aria-label="إجابة السؤال {{ $i + 1 }}"></textarea>
                @endswitch
            </fieldset>
        @endforeach
        <div class="submit-row">
            <button class="btn primary big" type="submit">تسليم الاختبار</button>
        </div>
    </form>
@endif
@endsection
@push('scripts')
@include('partials.math')
<script>
(function () {
    const form = document.getElementById('quiz-take');
    if (!form) return;
    const key = form.dataset.key, qs = [...form.querySelectorAll('[data-q]')];
    const answered = (q) => [...q.querySelectorAll('input,textarea')].some((f) => (f.type === 'radio' ? f.checked : f.value.trim() !== ''));
    const count = () => {
        const n = qs.filter(answered).length;
        document.getElementById('answered').textContent = 'أجبت عن ' + n + ' من ' + qs.length;
        document.getElementById('progress-fill').style.width = (n / qs.length * 100) + '%';
        qs.forEach((q) => q.classList.toggle('done', answered(q)));
    };
    // مسودة على هذا الجهاز حتى لا تضيع الإجابات عند تحديث الصفحة
    try {
        const saved = JSON.parse(localStorage.getItem(key) || '{}');
        Object.entries(saved).forEach(([n, v]) => {
            const fields = form.querySelectorAll('[name="' + n + '"]');
            fields.forEach((f) => { if (f.type === 'radio') f.checked = f.value === v; else f.value = v; });
        });
    } catch (e) {}
    const store = (e) => { try { const d = JSON.parse(localStorage.getItem(key) || '{}'); d[e.target.name] = e.target.value; localStorage.setItem(key, JSON.stringify(d)); } catch (err) {} count(); };
    form.addEventListener('change', store);
    form.addEventListener('input', store);
    let submitting = false;
    form.addEventListener('submit', (e) => {
        if (submitting) return;
        const left = qs.length - qs.filter(answered).length;
        if (left > 0 && !confirm('لم تُجب عن ' + left + ' سؤال. هل تريد التسليم؟')) { e.preventDefault(); return; }
        if (left === 0 && !confirm('تسليم الاختبار الآن؟ لا يمكن التعديل بعد التسليم.')) { e.preventDefault(); return; }
        submitting = true; try { localStorage.removeItem(key); } catch (err) {}
    });
    count();
    const t = document.getElementById('timer');
    if (t) {
        const offset = Date.now() - +t.dataset.now, end = +t.dataset.deadline;
        const tick = () => {
            const ms = end - (Date.now() - offset);
            if (ms <= 0) { t.querySelector('b').textContent = '00:00'; submitting = true; try { localStorage.removeItem(key); } catch (e) {} form.submit(); return; }
            const m = Math.floor(ms / 60000), s = Math.floor(ms / 1000) % 60;
            t.querySelector('b').textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            t.classList.toggle('low', ms < 60000);
            setTimeout(tick, 500);
        };
        tick();
    }
})();
</script>
@endpush
