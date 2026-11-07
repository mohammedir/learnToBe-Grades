@extends('layouts.app')
@section('title', 'بيانات دخول الطلاب')
@section('module', 'portal')
@section('content')
@php($qs = array_filter($f))
<div class="page-head">
    <div><h1>بيانات دخول الطلاب</h1><div class="sub">اسم المستخدم هو رقم الهوية. رابط البوابة: <span dir="ltr">{{ route('student.login') }}</span></div></div>
    <div class="spacer"></div>
    @if($canSee)
        <a class="btn" href="{{ route('admin.portal.credentials.print', $qs) }}" target="_blank">🖨 طباعة بطاقات الدخول</a>
        <a class="btn primary" href="{{ route('admin.portal.credentials.export', $qs) }}">⬇ تنزيل الملف (Excel)</a>
    @endif
    <a class="btn" href="{{ route('admin.portal.index') }}">بوابة الطلاب</a>
</div>

<div class="panel">
    <form class="inline" method="get">
        <select name="grade" aria-label="الصف" onchange="this.form.submit()"><option value="">كل الصفوف</option>@foreach($grades as $g)<option value="{{ $g->id }}" @selected(($f['grade'] ?? null) == $g->id)>{{ $g->name }}</option>@endforeach</select>
        <select name="section" aria-label="الشعبة" onchange="this.form.submit()"><option value="">كل الشعب</option>@foreach($grades as $g)<optgroup label="{{ $g->name }}">@foreach($g->sections as $s)<option value="{{ $s->id }}" @selected(($f['section'] ?? null) == $s->id)>{{ $g->name }} – {{ $s->short_name }}</option>@endforeach</optgroup>@endforeach</select>
        <select name="status" aria-label="حالة الحساب" onchange="this.form.submit()">
            <option value="">كل الحالات</option>
            <option value="temp" @selected(($f['status'] ?? '') === 'temp')>كلمة مؤقتة ظاهرة ({{ $counts['temp'] }})</option>
            <option value="own" @selected(($f['status'] ?? '') === 'own')>اختار كلمته الخاصة</option>
            <option value="none" @selected(($f['status'] ?? '') === 'none')>بدون حساب</option>
        </select>
        <input type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="بحث بالاسم أو الهوية أو الجوال" aria-label="بحث">
        <button class="btn" type="submit">بحث</button>
        @if($qs)<a class="btn sm" href="{{ route('admin.portal.credentials') }}">إلغاء التصفية</a>@endif
        <div class="spacer"></div>
        @if($canSee)<button class="btn sm" type="button" id="reveal-all">👁 إظهار كل كلمات المرور</button>@endif
    </form>
</div>

<div class="stats">
    <div class="stat"><b>{{ $counts['all'] }}</b><span>طالب في القائمة</span></div>
    <div class="stat"><b>{{ $counts['temp'] }}</b><span>كلمة مؤقتة ظاهرة</span></div>
    <div class="stat"><b>{{ $counts['own'] }}</b><span>اختاروا كلماتهم الخاصة</span></div>
    <div class="stat"><b>{{ $counts['none'] }}</b><span>بدون حساب</span></div>
</div>

<div class="scroll">
<table class="cred-table">
    <thead><tr><th class="c">م</th><th>اسم الطالب</th><th>الشعبة</th><th>اسم المستخدم (الهوية)</th><th>كلمة المرور</th><th>الجوال</th><th></th></tr></thead>
    <tbody>
    @forelse($students as $i => $st)
        <tr id="st-{{ $st->id }}">
            <td class="c muted">{{ $i + 1 }}</td>
            <td><b>{{ $st->name }}</b>@if($st->isSuspended()) <span class="pill off">موقوف</span>@endif</td>
            <td style="white-space:nowrap">{{ $st->section->full_name }}</td>
            <td dir="ltr" style="text-align:right">
                @if($st->national_id)<code>{{ $st->national_id }}</code>@else<span class="pill off">بدون هوية</span>@endif
            </td>
            <td>
                @if($canSee && $st->initial_password)
                    <code class="secret" dir="ltr" data-secret="{{ $st->initial_password }}">••••••</code>
                    <button class="btn sm ghost reveal" type="button">إظهار</button>
                @elseif($st->password)
                    <span style="color:var(--muted);font-size:13px">{{ $st->must_change_password ? 'مؤقتة (غير محفوظة للعرض)' : 'اختارها الطالب 🔒' }}</span>
                @else
                    <span style="color:var(--muted)">لا يوجد حساب</span>
                @endif
            </td>
            <td dir="ltr" style="text-align:right;white-space:nowrap">{{ $st->phone ?: '—' }}</td>
            <td>
                @if($canSee && $st->national_id)
                <div class="inline" style="flex-wrap:nowrap">
                    @if($st->initial_password)
                        @php($msg = "مدرسة تعلم لتكون الخاصة\nبوابة الطالب: ".$st->name."\nرابط الدخول: ".route('student.login')."\nرقم الهوية: ".$st->national_id."\nكلمة المرور: ".$st->initial_password."\nسيُطلب تغيير كلمة المرور عند أول دخول.")
                        @if($st->phone)<a class="btn sm wa-btn" href="{{ $st->whatsappUrl($msg) }}" target="_blank" rel="noopener">واتساب</a>@endif
                        <button class="btn sm copy-msg" type="button" data-msg="{{ $msg }}">نسخ</button>
                    @endif
                    <button class="btn sm" type="button" data-pw-student="{{ $st->id }}" data-pw-name="{{ $st->name }}" data-pw-url="{{ route('admin.portal.set-password', $st) }}">تغيير كلمة المرور</button>
                </div>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="c muted">لا يوجد طلاب بهذه التصفية.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<p class="hint">الكلمة المؤقتة تظهر هنا حتى يغيّرها الطالب. بعد أن يختار الطالب كلمته الخاصة تُحفظ مشفّرة ولا يراها أحد، ويمكنك تعيين كلمة جديدة له بزر «تغيير كلمة المرور».</p>

@if($canSee)
<dialog id="pw-dialog" class="dialog">
    <div class="inline" style="margin-bottom:8px"><h2 style="margin:0">كلمة مرور جديدة</h2><div class="spacer"></div><button class="btn sm" type="button" onclick="this.closest('dialog').close()">إغلاق</button></div>
    <p style="margin-top:0">الطالب: <b id="pw-name"></b></p>
    <form class="form" method="post" id="pw-form" style="max-width:none">
        @csrf @method('put')
        <div class="field">
            <label for="pw-input">كلمة المرور الجديدة (6 أحرف أو أرقام على الأقل)</label>
            <div class="inline" style="flex-wrap:nowrap">
                <input type="text" id="pw-input" name="password" dir="ltr" minlength="6" required style="flex:1;font-size:18px;letter-spacing:1px">
                <button class="btn sm" type="button" id="pw-gen">توليد</button>
            </div>
        </div>
        <label class="check"><input type="checkbox" name="must_change" value="1" checked> يطلب من الطالب تغييرها عند أول دخول</label>
        <div><button class="btn primary" type="submit">حفظ كلمة المرور</button></div>
    </form>
</dialog>
@endif
@endsection
@push('scripts')
<script>
document.querySelectorAll('.reveal').forEach(b => b.addEventListener('click', () => {
    const c = b.previousElementSibling, shown = c.dataset.shown === '1';
    c.textContent = shown ? '••••••' : c.dataset.secret; c.dataset.shown = shown ? '0' : '1'; b.textContent = shown ? 'إظهار' : 'إخفاء';
}));
document.getElementById('reveal-all')?.addEventListener('click', () => document.querySelectorAll('.secret').forEach(c => { c.textContent = c.dataset.secret; c.dataset.shown = '1'; c.nextElementSibling.textContent = 'إخفاء'; }));
document.querySelectorAll('.copy-msg').forEach(b => b.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(b.dataset.msg); b.textContent = 'تم ✓'; } catch (e) { prompt('انسخ الرسالة:', b.dataset.msg); }
    setTimeout(() => b.textContent = 'نسخ', 2000);
}));
const dlg = document.getElementById('pw-dialog');
if (dlg) {
    const input = document.getElementById('pw-input');
    const gen = () => { input.value = String(Math.floor(100000 + Math.random() * 900000)); };
    document.querySelectorAll('[data-pw-student]').forEach(b => b.addEventListener('click', () => {
        document.getElementById('pw-form').action = b.dataset.pwUrl;
        document.getElementById('pw-name').textContent = b.dataset.pwName;
        gen(); dlg.showModal(); input.select();
    }));
    document.getElementById('pw-gen').addEventListener('click', gen);
}
</script>
@endpush
