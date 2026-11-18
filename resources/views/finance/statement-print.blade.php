<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>كشف حساب {{ $supplier->name }} – تطبيق مدرسة تعلم لتكون</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&family=Noto+Kufi+Arabic:wght@700&display=swap">
<style>
@page{size:A4;margin:12mm}
*{box-sizing:border-box}
body{font-family:"IBM Plex Sans Arabic",Tahoma,Arial,sans-serif;margin:0;color:#18212F;background:#fff;font-size:12.5px}
.bar{display:flex;align-items:center;gap:10px;padding:12px 16px;background:#F4F6FA;border-bottom:1px solid #D6DDE7}
.bar button{font:inherit;font-weight:700;background:#2F5DA8;color:#fff;border:0;border-radius:8px;padding:8px 16px;cursor:pointer}
.sheet{max-width:900px;margin:16px auto;padding:0 12px}
.head{display:flex;align-items:center;gap:10px;border-bottom:2px solid #1F3864;padding-bottom:8px;margin-bottom:12px}
.head img{width:46px;height:46px}
.head b{font-family:"Noto Kufi Arabic",sans-serif;color:#1F3864;font-size:16px;display:block}
.head .meta{margin-inline-start:auto;text-align:left;font-size:12px;color:#5B6678}
.sum{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:12px}
.sum div{border:1px solid #D6DDE7;border-radius:8px;padding:6px 8px}
.sum b{display:block;font-size:15px}
.sum span{font-size:11px;color:#5B6678}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #B7C3D6;padding:5px 7px;text-align:right;vertical-align:top}
th{background:#2F5DA8;color:#fff}
.num{text-align:left;direction:ltr;font-variant-numeric:tabular-nums;white-space:nowrap}
.small{font-size:10.5px;color:#5B6678}
tfoot td,.opening td{background:#EEF2F9;font-weight:700}
thead{display:table-header-group}
tr{break-inside:avoid}
.sign{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:22px}
.sign div{border-top:1px dashed #8C9BB5;padding-top:6px;font-size:12px;color:#5B6678;min-height:46px}
@media print{.bar{display:none}.sheet{margin:0;max-width:none;padding:0}}
</style>
</head>
<body>
@php($m = fn ($v) => number_format((float) $v, 2))
<div class="bar"><b>كشف حساب</b><span style="flex:1"></span><button onclick="window.print()">طباعة</button></div>
<div class="sheet">
    <div class="head"><img src="{{ asset('images/logo.png') }}" alt=""><div><b>مدرسة تعلم لتكون الخاصة</b><small>كشف حساب: {{ $supplier->name }}</small></div>
        <div class="meta">{{ $st['from'] ? 'من '.$st['from']->format('Y/m/d') : 'من البداية' }} {{ $st['to'] ? 'إلى '.$st['to']->format('Y/m/d') : 'حتى '.now()->format('Y/m/d') }}<br>العملة: {{ \App\Support\Money::currency() }}</div></div>
    <div class="sum">
        <div><b>{{ $m($st['opening']) }}</b><span>رصيد افتتاحي</span></div>
        <div><b>{{ $m($st['debit']) }}</b><span>طلبات الفترة</span></div>
        <div><b>{{ $m($st['credit']) }}</b><span>دفعات الفترة</span></div>
        <div><b>{{ $m($st['closing']) }}</b><span>{{ $st['closing'] < 0 ? 'رصيد لصالح المدرسة' : 'المتبقي على المدرسة' }}</span></div>
    </div>
    <table>
        <thead><tr><th>التاريخ</th><th>المرجع</th><th>البيان</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr></thead>
        <tbody>
            <tr class="opening"><td></td><td></td><td>رصيد افتتاحي</td><td></td><td></td><td class="num">{{ $m($st['opening']) }}</td></tr>
            @foreach($st['rows'] as $l)
                <tr><td>{{ $l['date']->format('Y/m/d') }}</td><td dir="ltr" style="text-align:right">{{ $l['ref'] }}</td><td>{{ $l['desc'] }}<div class="small">{{ $l['detail'] }}@if($l['by']) · {{ $l['by'] }}@endif</div></td>
                    <td class="num">{{ $l['debit'] ? $m($l['debit']) : '' }}</td><td class="num">{{ $l['credit'] ? $m($l['credit']) : '' }}</td><td class="num">{{ $m($l['balance']) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="3">الإجمالي</td><td class="num">{{ $m($st['debit']) }}</td><td class="num">{{ $m($st['credit']) }}</td><td class="num">{{ $m($st['closing']) }}</td></tr></tfoot>
    </table>
    <div class="sign"><div>عن المدرسة</div><div>عن {{ $supplier->name }}</div></div>
</div>
</body>
</html>
