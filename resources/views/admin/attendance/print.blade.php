<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>رمز الحضور – {{ $point->name }}</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&family=Noto+Kufi+Arabic:wght@700&display=swap">
<style>
@page{size:A4;margin:12mm}
*{box-sizing:border-box}
body{font-family:"IBM Plex Sans Arabic",Tahoma,Arial,sans-serif;margin:0;color:#18212F;background:#fff}
.bar{display:flex;align-items:center;gap:10px;padding:12px 16px;background:#F4F6FA;border-bottom:1px solid #D6DDE7;flex-wrap:wrap}
.bar button,.bar a{font:inherit;font-weight:700;background:#2F5DA8;color:#fff;border:0;border-radius:8px;padding:8px 16px;cursor:pointer;text-decoration:none}
.bar a{background:#fff;color:#1F3864;border:1px solid #C9D3E3}
.sheet{max-width:640px;margin:20px auto;padding:28px 24px;text-align:center;border:3px solid #1F3864;border-radius:24px}
.head{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:6px}
.head img{width:60px;height:60px}
.head b{font-family:"Noto Kufi Arabic",sans-serif;color:#1F3864;font-size:22px}
h1{font-size:30px;margin:10px 0 4px}
.place{font-size:18px;color:#2F5DA8;font-weight:700;margin-bottom:14px}
#qr{display:inline-block;padding:14px;border:2px solid #E1E7F0;border-radius:18px;background:#fff}
#qr svg,#qr img{width:340px;height:340px;display:block}
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:20px 0 6px;text-align:center}
.steps div{background:#F4F6FA;border-radius:12px;padding:10px 8px;font-size:14px}
.steps b{display:block;width:30px;height:30px;line-height:30px;border-radius:50%;background:#1F3864;color:#fff;margin:0 auto 6px}
.foot{font-size:12px;color:#5B6678;margin-top:10px}
.err{color:#B42318;font-weight:700}
@media print{.bar{display:none}.sheet{margin:0 auto;border-width:3px}}
</style>
</head>
<body>
<div class="bar"><b>رمز الحضور: {{ $point->name }}</b><span style="flex:1"></span><a href="{{ route('admin.attendance.points') }}">رجوع</a><button onclick="window.print()">🖨 طباعة</button></div>
@if(session('status') || session('warning'))<p style="text-align:center;font-weight:700;color:{{ session('warning') ? '#94600A' : '#1F7A55' }}">{{ session('warning') ?? session('status') }}</p>@endif
<div class="sheet">
    <div class="head"><img src="{{ asset('images/logo.png') }}" alt=""><b>مدرسة تعلم لتكون الخاصة</b></div>
    <h1>تسجيل حضور المدرسين</h1>
    <div class="place">{{ $point->name }}</div>
    <div id="qr" data-text="{{ $point->qrText() }}" aria-label="رمز QR لتسجيل الحضور"></div>
    <div class="steps">
        <div><b>1</b>افتح تطبيق المدرسة ← «📍 حضوري»</div>
        <div><b>2</b>اضغط «تسجيل حضور» أو «تسجيل مغادرة»</div>
        <div><b>3</b>امسح هذا الرمز — يعمل بدون إنترنت</div>
    </div>
    <div class="foot">الدوام {{ $settings['start'] }} – {{ $settings['end'] }} · يُرجى عدم تصوير الرمز أو مشاركته</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
<script>
(function () {
    const box = document.getElementById('qr');
    if (!window.qrcode) { box.innerHTML = '<p class="err">تعذّر إنشاء الرمز. تأكد من الاتصال بالإنترنت ثم حدّث الصفحة.</p>'; return; }
    const qr = qrcode(0, 'M');
    qr.addData(box.dataset.text);
    qr.make();
    box.innerHTML = qr.createSvgTag({ cellSize: 8, margin: 2, scalable: true });
})();
</script>
</body>
</html>
