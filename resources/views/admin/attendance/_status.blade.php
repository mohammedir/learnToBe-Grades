{{-- شارة الحالة + العذر: $r --}}
@php($st = \App\Services\AttendanceReport::STATUS)
<span class="pill {{ $st[$r['status']][1] }}">{{ $st[$r['status']][0] }}@if($r['late']) {{ $r['late'] }} د@endif</span>
@if($r['excuse'] && $r['status'] !== 'excused')<span class="pill info">بعذر</span>@endif
@if($r['early'])<div class="small" style="color:var(--warn)">خرج مبكراً {{ $r['early'] }} د</div>@endif
@if($r['excuse'] && $r['excuse']->note)<div class="excuse-note" title="سجّله {{ $r['excuse']->creator?->name }}">📝 {{ $r['excuse']->note }}</div>@endif
