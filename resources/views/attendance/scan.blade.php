@extends('layouts.app')
@section('title', 'تسجيل الحضور')
@section('content')
@php($st = \App\Services\AttendanceReport::STATUS)
<div class="att-app" id="att-app"
     data-user="{{ $user->id }}"
     data-state-url="{{ route('attendance.state') }}"
     data-sync-url="{{ route('attendance.sync') }}"
     data-points='@json($points->map(fn ($p) => ['t' => $p->token, 'n' => $p->name])->values())'>

    <header class="att-head">
        <div>
            <h1>تسجيل الحضور</h1>
            <span class="muted">{{ $user->name }} · {{ now()->locale('ar')->translatedFormat('l j F') }}</span>
        </div>
        <span class="net" id="att-net" data-on="متصل" data-off="بدون إنترنت">متصل</span>
    </header>

    <section class="att-today">
        <div class="tcell"><span>الحضور اليوم</span><b id="today-in">{{ $today && $today['in'] ? $today['in']->occurred_at->format('H:i') : '—' }}</b></div>
        <div class="tcell"><span>المغادرة اليوم</span><b id="today-out">{{ $today && $today['out'] ? $today['out']->occurred_at->format('H:i') : '—' }}</b></div>
        <div class="tcell"><span>الدوام</span><b dir="ltr">{{ $settings['start'] }} – {{ $settings['end'] }}</b></div>
    </section>

    <div class="att-buttons">
        <button class="att-btn in" type="button" data-scan="in">
            <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M10 16l4-4-4-4M14 12H4"/></svg></span>
            <b>تسجيل حضور</b><small>امسح رمز QR عند وصولك</small>
        </button>
        <button class="att-btn out" type="button" data-scan="out">
            <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="M16 16l4-4-4-4M20 12H10"/></svg></span>
            <b>تسجيل مغادرة</b><small>امسح الرمز عند خروجك</small>
        </button>
    </div>

    <div class="att-pending" id="att-pending" hidden>
        <div class="inline"><b>⏳ تسجيلات بانتظار الرفع: <span id="pending-n">0</span></b><span class="spacer"></span><button class="btn sm" type="button" id="sync-now">رفع الآن</button></div>
        <ul id="pending-list"></ul>
        <p class="muted small" style="margin:0">ستُرفع تلقائياً بوقتها الأصلي عند اتصال جهازك بالإنترنت. لا تسجّل الخروج من التطبيق قبل رفعها.</p>
    </div>
    <div class="alert bad" id="att-failed" hidden></div>

    <section class="panel att-history">
        <h2>سجلي هذا الشهر</h2>
        <div class="scroll"><table>
            <thead><tr><th>اليوم</th><th class="c">الحضور</th><th class="c">المغادرة</th><th class="c">الحالة</th></tr></thead>
            <tbody>
            @forelse($days as $d)
                <tr>
                    <td>{{ $d['date']->locale('ar')->translatedFormat('l j/n') }}</td>
                    <td class="c" dir="ltr">{{ $d['in']?->occurred_at->format('H:i') ?? '—' }}</td>
                    <td class="c" dir="ltr">{{ $d['out']?->occurred_at->format('H:i') ?? '—' }}</td>
                    <td class="c"><span class="pill {{ $st[$d['status']][1] }}">{{ $st[$d['status']][0] }}@if($d['late']) {{ $d['late'] }} د@endif</span>@if($d['excuse'] && $d['status'] !== 'excused') <span class="pill info">بعذر</span>@endif @if($d['excuse']?->note)<div class="excuse-note">📝 {{ $d['excuse']->note }}</div>@endif</td>
                </tr>
            @empty
                <tr><td colspan="4" class="c muted">لا توجد تسجيلات هذا الشهر.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</div>

{{-- شاشة المسح --}}
<div class="scan-screen" id="scan-screen" hidden>
    <video id="scan-video" playsinline muted></video>
    <div class="scan-frame" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
    <div class="scan-top"><b id="scan-title">تسجيل حضور</b><span>وجّه الكاميرا إلى رمز QR المعلّق في المدرسة</span></div>
    <div class="scan-bottom">
        <label class="btn" id="photo-label">📷 التقاط صورة للرمز<input type="file" accept="image/*" capture="environment" id="scan-photo" hidden></label>
        <button class="btn" type="button" id="scan-cancel">إلغاء</button>
    </div>
    <p class="scan-err" id="scan-err" hidden></p>
</div>

{{-- رسالة النجاح --}}
<div class="att-done" id="att-done" hidden role="alertdialog" aria-live="assertive">
    <div class="att-done-card">
        <div class="att-check" aria-hidden="true"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24"/><path d="M15 27l7 7 15-16"/></svg></div>
        <h2 id="done-title">تم تسجيل الحضور بنجاح</h2>
        <b class="done-time" id="done-time" dir="ltr">--:--</b>
        <span class="muted" id="done-place"></span>
        <p class="done-sync" id="done-sync"></p>
        <button class="btn primary big" type="button" id="done-ok">تم</button>
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/attendance.js') }}?v=2"></script>
@endpush
