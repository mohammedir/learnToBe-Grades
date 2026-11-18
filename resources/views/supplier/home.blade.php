@extends('layouts.app')
@section('title', 'طلبات المدرسة')
@section('content')
@php($groupInfo = [
    'pending' => ['جديدة – بانتظاركم', 'ابدؤوا التجهيز وحدّدوا الكمية والسعر'],
    'preparing' => ['قيد التجهيز', 'عند الانتهاء اضغطوا «الطلب جاهز»'],
    'ready' => ['جاهزة – بانتظار المستلم', 'اضغطوا «تم التسليم» عندما يستلمها مندوب المدرسة'],
])
<section class="sup-hero">
    <div>
        <span class="fin-label">مرحباً {{ $supplier->contact_name ?: $supplier->name }}</span>
        <h1>طلبات مدرسة تعلم لتكون</h1>
        <span class="fin-small">{{ $supplier->name }}</span>
    </div>
    <a class="sup-hero-due" href="{{ route('supplier.statement') }}">
        <span>المتبقي لكم على المدرسة</span>
        <b>{{ \App\Support\Money::fmt($totals['balance']) }}</b>
        <small>كشف الحساب ←</small>
    </a>
</section>

<nav class="flow-strip" aria-label="الطلبات حسب المرحلة">
    @foreach(\App\Models\PurchaseOrder::FLOW as $s)
        <a class="flow-step st-{{ $s }}" href="{{ $s === 'received' ? route('supplier.home', ['tab' => 'received']) : route('supplier.home').'#g-'.$s }}">
            <span class="ocard-ico">@include('finance._icon', ['s' => $s])</span>
            <b>{{ $counts[$s] ?? 0 }}</b><span>{{ \App\Models\PurchaseOrder::STATUSES[$s] }}</span>
        </a>
    @endforeach
</nav>

<div class="status-tabs">
    @foreach(['open' => 'الطلبات المفتوحة', 'received' => 'المستلمة', 'cancelled' => 'الملغاة', 'all' => 'كل الطلبات'] as $k => $l)
        <a href="{{ route('supplier.home', ['tab' => $k]) }}" @class(['on' => $tab === $k])>{{ $l }}</a>
    @endforeach
    @if($totals['unpriced'])<span class="tag price" style="margin-inline-start:auto">⏳ {{ $totals['unpriced'] }} بانتظار تسعيركم</span>@endif
</div>

@if($tab === 'open')
    @if($groups->isEmpty())
        <div class="panel empty-state"><b>✓ لا توجد طلبات مفتوحة</b><p>ستظهر طلبات المدرسة الجديدة هنا فور إرسالها.</p></div>
    @endif
    @foreach(['pending', 'preparing', 'ready'] as $s)
        @if($groups->has($s))
        <section class="sgroup st-{{ $s }}" id="g-{{ $s }}">
            <header class="sgroup-head">
                <span class="ocard-ico">@include('finance._icon', ['s' => $s])</span>
                <div><b>{{ $groupInfo[$s][0] }}</b> <span class="bcount">{{ $groups[$s]->count() }}</span><small>{{ $groupInfo[$s][1] }}</small></div>
            </header>
            <div class="ocard-grid">
                @foreach($groups[$s] as $o)
                    @include('finance._order-card', ['o' => $o, 'actor' => 'supplier', 'supplierPrice' => $supplier->default_page_price])
                @endforeach
            </div>
        </section>
        @endif
    @endforeach
    <p class="muted small" style="text-align:center" id="auto-note">تتحدّث الصفحة تلقائياً كل دقيقتين لعرض الطلبات الجديدة.</p>
@else
    <div class="ocard-grid">
        @forelse($orders as $o)
            @include('finance._order-card', ['o' => $o, 'actor' => 'supplier', 'supplierPrice' => $supplier->default_page_price])
        @empty
            <div class="panel empty-state"><b>لا توجد طلبات هنا</b></div>
        @endforelse
    </div>
    <div style="margin-top:12px">{{ $orders->links('partials.pager') }}</div>
@endif
@endsection
@if($tab === 'open')
@push('scripts')
<script>
// تحديث تلقائي ما دام المستخدم لا يكتب في نموذج
setInterval(() => {
    if (document.hidden) return;
    const a = document.activeElement;
    if (a && /INPUT|TEXTAREA|SELECT/.test(a.tagName)) return;
    if ([...document.querySelectorAll('.price-form input')].some(i => i.value !== i.defaultValue)) return;
    location.reload();
}, 120000);
</script>
@endpush
@endif
