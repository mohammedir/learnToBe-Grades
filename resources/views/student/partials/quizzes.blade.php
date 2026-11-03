@if($quizzes->isNotEmpty())
<section class="quiz-board">
    <div class="section-title"><h2>اختباراتي</h2></div>
    <div class="quiz-grid">
        @foreach($quizzes as $item)
            @php($q = $item['quiz'])
            @php($a = $item['attempt'])
            @php($done = $a && $a->submitted_at)
            @php($cls = $done ? 'done' : ($item['state'] === 'open' ? 'open' : ($item['state'] === 'upcoming' ? 'soon' : 'over')))
            <a class="quiz-card quiz-{{ $cls }}" href="{{ route('student.quiz', $q) }}">
                <div class="inline"><span class="quiz-subject">{{ $q->assignment->subject->name }}</span><div class="spacer"></div>
                    <span class="quiz-status">
                        @if($done && ! $q->results_published) تم التسليم ✓
                        @elseif($done) النتيجة منشورة
                        @elseif($a) لم يُسلَّم بعد
                        @else {{ $q->stateLabel() }} @endif
                    </span></div>
                <b>{{ $q->title }}</b>
                <span class="quiz-meta">{{ $q->questions->count() }} سؤال @if($q->duration_minutes)· {{ $q->duration_minutes }} دقيقة @endif
                    @if(! $done && $q->closes_at && $item['state'] !== 'closed')· آخر موعد {{ $q->closes_at->format('Y/m/d H:i') }}@endif
                    @if($item['state'] === 'upcoming' && $q->opens_at)· يبدأ {{ $q->opens_at->format('Y/m/d H:i') }}@endif</span>
                @if($done && ! $q->results_published)
                    <span class="quiz-meta">ستظهر نتيجتك عندما ينشرها المدرس</span>
                @elseif($done)
                    <span class="quiz-score">{{ rtrim(rtrim(number_format($a->score, 2, '.', ''), '0'), '.') }}<small> / {{ rtrim(rtrim(number_format($a->max_score, 2, '.', ''), '0'), '.') }}</small></span>
                @elseif($item['state'] === 'open')
                    <span class="btn sm primary" style="align-self:flex-start">{{ $a ? 'أكمل الاختبار' : 'ابدأ الاختبار' }}</span>
                @endif
            </a>
        @endforeach
    </div>
</section>
@endif
