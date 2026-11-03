@extends('layouts.app')
@section('title', 'تصحيح '.$attempt->student->name)
@if($asAdmin) @section('module', 'quizzes') @endif
@section('content')
@php($fmt = fn ($n) => $n === null ? '' : rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'))
<div class="page-head">
    <div><h1>تصحيح: {{ $attempt->student->name }}</h1><div class="sub">{{ $quiz->title }} · {{ $quiz->assignment->subject->name }} · سلّم {{ $attempt->submitted_at->format('Y/m/d H:i') }}@if($attempt->late) · <b style="color:var(--bad)">متأخر</b>@endif</div></div>
    <div class="spacer"></div>
    <span class="pill {{ $attempt->needs_review ? 'part' : 'done' }}" id="live-total">{{ $fmt($attempt->score) }} / {{ $fmt($attempt->max_score) }}</span>
    <a class="btn" href="{{ route($rp.'quizzes.results', $quiz) }}">كل النتائج</a>
</div>

<form method="post" action="{{ route($rp.'quizzes.review.save', [$quiz, $attempt]) }}" id="review-form">
    @csrf
    @foreach($quiz->questions as $i => $q)
        @php($ans = $attempt->answers[$q->id] ?? null)
        @php($got = $attempt->awarded[$q->id] ?? null)
        @php($pending = $got === null && $q->type === 'text' && $ans !== null)
        <div class="review-card {{ $pending ? 'pending' : ($got !== null && $got >= $q->points ? 'right' : ($got ? 'partial' : 'wrong')) }}">
            <div class="inline"><span class="q-n">{{ $i + 1 }}</span><span class="pill none">{{ \App\Models\QuizQuestion::TYPES[$q->type] }}</span>@if($pending)<span class="pill part">يحتاج تصحيحك</span>@endif<div class="spacer"></div>
                <label class="review-pts">الدرجة
                    <input type="text" inputmode="decimal" name="points[{{ $q->id }}]" value="{{ $fmt($got) }}" data-max="{{ $q->points }}" @if($pending) autofocus @endif aria-label="درجة السؤال {{ $i + 1 }}"> / {{ $fmt($q->points) }}
                </label>
                @if($q->type === 'text')<button type="button" class="btn sm" data-full="{{ $q->points }}">كاملة</button><button type="button" class="btn sm" data-full="0">صفر</button>@endif
            </div>
            <div class="rv-body">{!! nl2br(e($q->body)) !!}</div>
            @if($q->imageUrl())<img class="q-img" src="{{ $q->imageUrl() }}" alt="صورة السؤال">@endif
            <div class="rv-grid">
                <div><span>إجابة الطالب</span><div class="rv-ans {{ $q->type === 'text' ? 'text' : '' }}">
                    @if($ans === null)<i style="color:var(--muted)">لم يُجب</i>
                    @elseif(in_array($q->type, ['mcq', 'tf']))<span>{{ $q->choices()[$ans] ?? $ans }}</span>
                    @else<span dir="auto">{{ $ans }}</span>@if($q->type === 'number' && !empty($q->meta['unit'])) <span dir="ltr">{{ $q->meta['unit'] }}</span>@endif
                    @endif</div></div>
                <div><span>{{ $q->type === 'text' ? 'نموذج الإجابة' : 'الإجابة الصحيحة' }}</span><div class="rv-ans correct"><span>{{ $q->correctText() ?: '—' }}</span></div>
                    @if($q->type === 'text' && count($q->meta['accepted'] ?? []) > 1)<small style="color:var(--muted)">مقبول أيضاً: {{ implode('، ', array_slice($q->meta['accepted'], 1)) }}</small>@endif
                </div>
            </div>
        </div>
    @endforeach
    <div class="inline sticky-actions">
        <button class="btn primary" type="submit">حفظ التصحيح</button>
        @if($next)<button class="btn" type="submit" name="next" value="{{ $next }}">حفظ والانتقال للطالب التالي ←</button>@endif
        <span class="hint">اترك الخانة فارغة إن أردت تأجيل تصحيح السؤال.</span>
    </div>
</form>
@endsection
@push('scripts')
@include('partials.math')
<script>
(function () {
    const f = document.getElementById('review-form'), out = document.getElementById('live-total');
    const max = {{ (float) $attempt->max_score }};
    const sum = () => {
        let s = 0, pending = false;
        f.querySelectorAll('input[name^="points"]').forEach(i => { const v = parseFloat((i.value || '').replace(',', '.')); if (isNaN(v)) pending = true; else s += v; i.classList.toggle('invalid', !isNaN(v) && (v < 0 || v > +i.dataset.max)); });
        out.textContent = (Math.round(s * 100) / 100) + ' / ' + max + (pending ? ' (ناقص)' : '');
        out.className = 'pill ' + (pending ? 'part' : 'done');
    };
    f.addEventListener('input', sum);
    f.querySelectorAll('[data-full]').forEach(b => b.addEventListener('click', () => { const i = b.closest('.inline').querySelector('input'); i.value = b.dataset.full; sum(); }));
})();
</script>
@endpush
