<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>بطاقات دخول الطلاب – تطبيق مدرسة تعلم لتكون</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&family=Noto+Kufi+Arabic:wght@700&display=swap">
<style>
@page{size:A4;margin:10mm}
*{box-sizing:border-box}
body{font-family:"IBM Plex Sans Arabic",Tahoma,Arial,sans-serif;margin:0;color:#18212F;background:#fff}
.bar{display:flex;align-items:center;gap:10px;padding:12px 16px;background:#F4F6FA;border-bottom:1px solid #D6DDE7}
.bar button{font:inherit;font-weight:700;background:#2F5DA8;color:#fff;border:0;border-radius:8px;padding:8px 16px;cursor:pointer}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:6mm;padding:8mm}
.card{border:1.5px dashed #8C9BB5;border-radius:10px;padding:10px 12px;break-inside:avoid;display:flex;flex-direction:column;gap:6px}
.top{display:flex;align-items:center;gap:8px;border-bottom:1px solid #E6EAF0;padding-bottom:6px}
.top img{width:34px;height:34px}
.top b{font-family:"Noto Kufi Arabic",sans-serif;font-size:13px;color:#1F3864;display:block}
.top small{font-size:10.5px;color:#5B6678}
.name{font-weight:700;font-size:14.5px}
.sec{font-size:11.5px;color:#5B6678}
.row{display:flex;justify-content:space-between;align-items:center;background:#F4F6FA;border-radius:6px;padding:5px 8px;font-size:12px}
.row code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:15px;font-weight:700;direction:ltr;letter-spacing:.5px}
.url{font-size:10.5px;color:#2F5DA8;direction:ltr;text-align:left}
.note{font-size:10px;color:#5B6678}
.empty{padding:30px;text-align:center;color:#5B6678}
@media print{.bar{display:none}.grid{padding:0}}
</style>
</head>
<body>
<div class="bar"><b>{{ $students->count() }} بطاقة</b><span style="color:#5B6678;font-size:13px">تظهر هنا فقط الحسابات التي كلمتها المؤقتة ما زالت ظاهرة.</span><span style="flex:1"></span><button onclick="window.print()">طباعة</button></div>
@if($students->isEmpty())
    <div class="empty">لا توجد بطاقات للطباعة بهذه التصفية. أنشئ كلمات مرور للطلاب أولاً من «بوابة الطلاب».</div>
@else
<div class="grid">
    @foreach($students as $st)
        <div class="card">
            <div class="top"><img src="{{ asset('icons/icon-192.png') }}" alt=""><div><b>تطبيق مدرسة تعلم لتكون</b><small>بوابة الطالب · LearnToBe</small></div></div>
            <div class="name">{{ $st->name }}</div>
            <div class="sec">{{ $st->section->full_name }}</div>
            <div class="row"><span>اسم المستخدم (رقم الهوية)</span><code>{{ $st->national_id }}</code></div>
            <div class="row"><span>كلمة المرور</span><code>{{ $st->initial_password }}</code></div>
            <div class="url">{{ route('student.login') }}</div>
            <div class="note">سيُطلب منك اختيار كلمة مرور جديدة عند أول دخول.</div>
        </div>
    @endforeach
</div>
@endif
</body>
</html>
