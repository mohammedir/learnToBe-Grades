<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>طلب {{ $order->number }} – تطبيق مدرسة تعلم لتكون</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&family=Noto+Kufi+Arabic:wght@700&display=swap">
<style>
@page{size:A5;margin:10mm}
*{box-sizing:border-box}
body{font-family:"IBM Plex Sans Arabic",Tahoma,Arial,sans-serif;margin:0;color:#18212F;background:#fff;font-size:13.5px}
.bar{display:flex;align-items:center;gap:10px;padding:12px 16px;background:#F4F6FA;border-bottom:1px solid #D6DDE7}
.bar button{font:inherit;font-weight:700;background:#2F5DA8;color:#fff;border:0;border-radius:8px;padding:8px 16px;cursor:pointer}
.sheet{max-width:560px;margin:16px auto;padding:0 12px}
.head{display:flex;align-items:center;gap:10px;border-bottom:2px solid #1F3864;padding-bottom:8px;margin-bottom:12px}
.head img{width:46px;height:46px}
.head b{font-family:"Noto Kufi Arabic",sans-serif;color:#1F3864;font-size:16px;display:block}
.head .no{margin-inline-start:auto;text-align:left;direction:ltr;font-weight:700;font-size:15px}
h1{font-size:17px;margin:0 0 10px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #B7C3D6;padding:7px 9px;text-align:right;vertical-align:top}
th{background:#EEF2F9;width:34%;font-weight:600}
.total td{font-size:16px;font-weight:700}
.sign{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:22px}
.sign div{border-top:1px dashed #8C9BB5;padding-top:6px;font-size:12px;color:#5B6678;min-height:46px}
.foot{margin-top:14px;font-size:11px;color:#5B6678}
@media print{.bar{display:none}.sheet{margin:0;max-width:none;padding:0}}
</style>
</head>
<body>
<div class="bar"><b>نموذج طلب شراء</b><span style="flex:1"></span><button onclick="window.print()">طباعة</button></div>
<div class="sheet">
    <div class="head"><img src="{{ asset('images/logo.png') }}" alt=""><div><b>مدرسة تعلم لتكون الخاصة</b><small>طلب شراء / توريد من {{ $order->supplier->name }}</small></div><div class="no">{{ $order->number }}<br><small>{{ $order->created_at->format('Y/m/d') }}</small></div></div>
    <h1>{{ $order->title }}</h1>
    <table>
        <tr><th>الصف / الشعبة</th><td>{{ $order->class_label }}</td></tr>
        @if($order->subject)<tr><th>المادة</th><td>{{ $order->subject->name }}</td></tr>@endif
        <tr><th>طريقة الحساب</th><td>{{ $order->pricing_label }}@if($order->pricing === 'per_page' && $order->isPriced()) ({{ $order->pages * $order->copies }} ورقة)@endif</td></tr>
        <tr class="total"><th>المبلغ</th><td>{{ $order->amount_label }}</td></tr>
        <tr><th>المستلم من المطبعة</th><td><b>{{ $order->recipient_label }}</b></td></tr>
        @if($order->needed_by)<tr><th>مطلوب قبل</th><td>{{ $order->needed_by->format('Y/m/d') }}</td></tr>@endif
        <tr><th>طلبه من الإدارة</th><td>{{ $order->creator?->name ?? '—' }}</td></tr>
        <tr><th>الحالة</th><td>{{ $order->status_label }}</td></tr>
        @if($order->details)<tr><th>تفاصيل</th><td>{!! nl2br(e($order->details)) !!}</td></tr>@endif
    </table>
    <div class="sign"><div>توقيع المستلم: {{ $order->recipient_label }}</div><div>توقيع وختم المطبعة</div></div>
    <div class="foot">يُحسب المبلغ على حساب المدرسة لدى المطبعة، ويُسدَّد ضمن الحساب الدوري.</div>
</div>
</body>
</html>
