@extends('layouts.app')
@section('title', 'الطلب '.$order->number)
@section('content')
<div class="page-head">
    <div><h1><small dir="ltr" class="muted">{{ $order->number }}</small> {{ $order->title }}</h1><div class="sub"><span class="pill {{ $order->status_class }}">{{ $order->status_label }}</span></div></div>
    <div class="spacer"></div><a class="btn" href="{{ route('supplier.home') }}">كل الطلبات</a>
</div>
@include('finance._stepper')
<div class="po-layout">
    <section class="panel"><h2>بيانات الطلب</h2>@include('finance._order-details')</section>
    <aside class="po-side">
        @if($order->canBePriced())
        <section class="panel">
            <h2>{{ $order->isPriced() ? 'تصحيح الكمية والسعر' : 'تحديد الكمية والسعر' }}</h2>
            <p class="muted small">أدخلوا عدد أوراق النسخة وعدد النسخ وسعر الورقة، ويُحسب المبلغ على حساب المدرسة.</p>
            @include('finance._pricing-form', ['action' => route('supplier.orders.price', $order)])
        </section>
        @endif
        @if($targets)
        <section class="panel">
            <h2>تحديث الحالة</h2>
            <form method="post" action="{{ route('supplier.orders.status', $order) }}" class="form">
                @csrf
                <div class="inline">@foreach($targets as $t)<button class="btn sm @if($loop->first) primary @endif" name="status" value="{{ $t }}" type="submit">← {{ \App\Models\PurchaseOrder::STATUSES[$t] }}</button>@endforeach</div>
                <input name="note" maxlength="500" placeholder="ملاحظة للمدرسة (اختياري)" aria-label="ملاحظة">
            </form>
            @if($order->status === 'pending')<p class="muted small">بمجرد بدء التجهيز لا يحق للمدرسة إلغاء الطلب.</p>@endif
        </section>
        @endif
        <section class="panel"><h2>سجل الطلب</h2>@include('finance._events')</section>
    </aside>
</div>
@endsection
