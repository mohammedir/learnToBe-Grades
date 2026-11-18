@extends('layouts.app')
@section('title', 'رموز QR للحضور')
@section('module', 'attendance')
@section('content')
@include('admin.attendance._tabs')
<div class="page-head">
    <div><h1>رموز QR والإعدادات</h1><div class="sub">اطبع الرمز وعلّقه عند مدخل المدرسة أو غرفة المدرسين. يمسحه المدرس من «📍 حضوري» في التطبيق، ويعمل المسح حتى بدون إنترنت.</div></div>
</div>

<div class="grid2 att-setup">
    <section class="panel">
        <h2>رموز التسجيل</h2>
        @forelse($points as $p)
            <details class="point-row" @if($loop->first && $points->count() === 1) open @endif>
                <summary>
                    <span class="qr-mini" aria-hidden="true">▣</span>
                    <b>{{ $p->name }}</b>
                    @if($p->is_active)<span class="pill done">فعّال</span>@else<span class="pill off">موقوف</span>@endif
                    <span class="muted small">{{ $p->records_count }} تسجيل</span>
                    <a class="btn sm primary" href="{{ route('admin.attendance.points.print', $p) }}" onclick="event.stopPropagation()">🖨 طباعة</a>
                </summary>
                @perm('attendance', 'edit')
                <form class="form" method="post" action="{{ route('admin.attendance.points.update', $p) }}" style="margin-top:10px">
                    @csrf @method('put')
                    <div class="field"><label>الاسم</label><input name="name" value="{{ $p->name }}" required maxlength="100"></div>
                    <fieldset>
                        <legend>موقع المدرسة (اختياري)</legend>
                        <p class="hint" style="margin-top:0">إن حدّدته، يظهر تنبيه «بعيد عن المدرسة» إذا سُجّل الحضور من مكان آخر (مثل تصوير الرمز ومسحه من البيت).</p>
                        <div class="inline">
                            <input name="lat" inputmode="decimal" dir="ltr" value="{{ $p->lat }}" placeholder="خط العرض" aria-label="خط العرض" style="max-width:150px">
                            <input name="lng" inputmode="decimal" dir="ltr" value="{{ $p->lng }}" placeholder="خط الطول" aria-label="خط الطول" style="max-width:150px">
                            <input name="radius" type="number" min="30" max="5000" value="{{ $p->radius }}" aria-label="نصف القطر بالمتر" style="max-width:110px"> م
                            <button class="btn sm" type="button" data-here>📍 موقعي الحالي</button>
                        </div>
                    </fieldset>
                    <input type="hidden" name="is_active" value="0"><label class="check"><input type="checkbox" name="is_active" value="1" @checked($p->is_active)> فعّال</label>
                    <div class="inline"><button class="btn primary sm" type="submit">حفظ</button></div>
                </form>
                <form method="post" action="{{ route('admin.attendance.points.regenerate', $p) }}" onsubmit="return confirm('سيتوقف الرمز المطبوع الحالي فوراً ويجب طباعة رمز جديد. متابعة؟')" style="margin-top:8px">
                    @csrf<button class="btn sm danger" type="submit">تغيير الرمز (إبطال المطبوع)</button>
                    <span class="hint">استخدمه إذا صُوّر الرمز ونُشر بين المدرسين.</span>
                </form>
                <form method="post" action="{{ route('admin.attendance.points.destroy', $p) }}" onsubmit="return confirm('حذف رمز «{{ $p->name }}» نهائياً؟ سيتوقف الرمز المطبوع، وتبقى تسجيلات الحضور السابقة محفوظة.')" style="margin-top:8px">
                    @csrf @method('delete')<button class="btn sm danger" type="submit">🗑 حذف الرمز</button>
                </form>
                @endperm
            </details>
        @empty
            <p class="muted">لا توجد رموز بعد.</p>
        @endforelse
        @perm('attendance', 'edit')
        <form class="inline" method="post" action="{{ route('admin.attendance.points.store') }}" style="margin-top:12px">
            @csrf
            <input name="name" required maxlength="100" placeholder="مثال: مدخل المدرسة" aria-label="اسم المكان">
            <button class="btn primary" type="submit">+ إنشاء رمز QR</button>
        </form>
        @endperm
    </section>

    <section class="panel">
        <h2>أوقات الدوام</h2>
        <form class="form" method="post" action="{{ route('admin.attendance.settings') }}">
            @csrf @method('put')
            <div class="inline">
                <div class="field"><label for="a-start">بداية الدوام</label><input id="a-start" type="time" name="start" value="{{ $settings['start'] }}" required></div>
                <div class="field"><label for="a-end">نهاية الدوام</label><input id="a-end" type="time" name="end" value="{{ $settings['end'] }}" required></div>
                <div class="field"><label for="a-grace">دقائق السماح</label><input id="a-grace" type="number" name="grace" min="0" max="120" value="{{ $settings['grace'] }}" required style="max-width:100px"></div>
            </div>
            <div class="field"><label>أيام الدوام</label>
                <div class="chips">@foreach(\App\Services\AttendanceReport::DAYS as $n => $label)<label @class(['on' => in_array($n, $settings['days'])])><input type="checkbox" name="days[]" value="{{ $n }}" @checked(in_array($n, $settings['days']))> {{ $label }}</label>@endforeach</div>
            </div>
            <p class="hint" style="margin:0">يُعدّ متأخراً من سجّل بعد بداية الدوام + دقائق السماح، وغائباً من لم يسجّل في يوم دوام بعد انتهائه.</p>
            @perm('attendance', 'edit')<div><button class="btn primary" type="submit">حفظ</button></div>@endperm
        </form>
    </section>
</div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-here]').forEach(b => b.addEventListener('click', () => {
    if (!navigator.geolocation) return alert('الموقع غير متاح على هذا الجهاز');
    b.textContent = '…';
    navigator.geolocation.getCurrentPosition(p => {
        const f = b.closest('form');
        f.querySelector('[name=lat]').value = p.coords.latitude.toFixed(6);
        f.querySelector('[name=lng]').value = p.coords.longitude.toFixed(6);
        b.textContent = '✓ تم (دقة ' + Math.round(p.coords.accuracy) + ' م)';
    }, () => { b.textContent = '📍 موقعي الحالي'; alert('تعذّر تحديد الموقع. اسمح للمتصفح بالوصول للموقع.'); }, { enableHighAccuracy: true, timeout: 15000 });
}));
document.querySelectorAll('.chips input').forEach(i => i.addEventListener('change', () => i.parentElement.classList.toggle('on', i.checked)));
</script>
@endpush
