@extends('layouts.app')
@section('title', 'المطابع والموردون')
@section('module', 'finance')
@section('content')
@include('admin.finance._tabs')
<div class="page-head">
    <div><h1>المطابع والموردون</h1><div class="sub">لكل مطبعة حساب دخول اختياري تتابع منه طلبات المدرسة وتغيّر حالتها.</div></div>
    <div class="spacer"></div>
    @perm('finance', 'edit')<a class="btn primary" href="{{ route('admin.finance.suppliers.create') }}">إضافة مطبعة / مورد</a>@endperm
</div>
<div class="scroll"><table>
    <thead><tr><th>الاسم</th><th>الجوال</th><th>حساب الدخول</th><th class="c">الطلبات</th><th class="num">المتبقي على المدرسة</th><th class="c">الحالة</th><th></th></tr></thead>
    <tbody>
    @forelse($suppliers as $s)
        @php($acc = $s->accounts->first())
        <tr>
            <td><b>{{ $s->name }}</b>@if($s->contact_name)<div class="muted small">{{ $s->contact_name }}</div>@endif</td>
            <td class="phones">@if($s->phone)<span class="phone"><a href="tel:{{ $s->phone }}" dir="ltr">{{ $s->phone }}</a><a class="wa" href="{{ \App\Support\Phone::whatsapp($s->phone) }}" target="_blank" rel="noopener">واتساب</a></span>@else<span class="muted">—</span>@endif</td>
            <td>@if($acc)<code dir="ltr">{{ $acc->username }}</code> @if($acc->is_active)<span class="pill done">مفعّل</span>@else<span class="pill off">موقوف</span>@endif<div class="muted small">آخر دخول: {{ $acc->last_login_at?->format('Y/m/d H:i') ?? 'لم يدخل' }}</div>@else<span class="muted">بدون حساب</span>@endif</td>
            <td class="c">{{ $s->orders_count }}</td>
            <td class="num"><b>{{ \App\Support\Money::fmt($s->totals['balance']) }}</b></td>
            <td class="c">@if($s->is_active)<span class="pill done">نشط</span>@else<span class="pill off">موقوف</span>@endif</td>
            <td class="inline" style="justify-content:flex-end"><a class="btn sm primary" href="{{ route('admin.finance.statement', $s) }}">كشف الحساب</a>@perm('finance', 'edit')<a class="btn sm" href="{{ route('admin.finance.suppliers.edit', $s) }}">تعديل</a>@endperm</td>
        </tr>
    @empty
        <tr><td colspan="7" class="c muted">لم تُضف أي مطبعة بعد.</td></tr>
    @endforelse
    </tbody>
</table></div>
@endsection
