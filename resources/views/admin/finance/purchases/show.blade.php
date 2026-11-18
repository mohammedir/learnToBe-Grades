@extends('layouts.app')
@section('title', 'الطلب '.$order->number)
@section('module', 'finance')
@section('content')
@include('admin.finance._tabs')
<div class="page-head">
    <div><h1><small dir="ltr" class="muted">{{ $order->number }}</small> {{ $order->title }}</h1>
        <div class="sub"><span class="pill {{ $order->status_class }}">{{ $order->status_label }}</span>@if($order->status_at && $order->status !== 'pending') · آخر تغيير {{ $order->status_at->format('Y/m/d H:i') }}@if($order->statusUser) بواسطة {{ $order->statusUser->name }}@endif @endif</div></div>
    <div class="spacer"></div>
    <a class="btn" href="{{ route('admin.finance.purchases.print', $order) }}" target="_blank">🖨 طباعة نموذج الطلب</a>
    @perm('finance', 'edit')<a class="btn" href="{{ route('admin.finance.purchases.create', ['copy' => $order->id]) }}">نسخ كطلب جديد</a>@endperm
</div>

@include('finance._stepper')

<div class="po-layout">
    <section class="panel">
        <h2>بيانات الطلب</h2>
        @include('finance._order-details')
        @php($msg = "مدرسة تعلم لتكون الخاصة\nطلب ".$order->number.': '.$order->title."\nالصف: ".$order->class_label."\n".$order->pricing_label.($order->isPriced() ? ' = '.$order->amount_label : '')."\nالمستلم: ".$order->recipient_label.($order->needed_by ? "\nمطلوب قبل: ".$order->needed_by->format('Y/m/d') : '').($order->details ? "\n".$order->details : ''))
        <div class="inline" style="margin-top:12px">
            @if($order->supplier->phone && $order->status !== 'cancelled')<a class="btn sm wa-btn" href="{{ \App\Support\Phone::whatsapp($order->supplier->phone, $msg) }}" target="_blank" rel="noopener">إرسال الطلب للمطبعة واتساب</a>@endif
            @if($order->status === 'ready' && $order->recipient?->phone)<a class="btn sm wa-btn" href="{{ \App\Support\Phone::whatsapp($order->recipient->phone, 'السلام عليكم '.$order->recipient->name.'، الطلبية «'.$order->title.'» جاهزة للاستلام من '.$order->supplier->name.'.') }}" target="_blank" rel="noopener">إبلاغ المستلم أنها جاهزة</a>@endif
        </div>
    </section>

    <aside class="po-side">
        @perm('finance', 'edit')
        @if($order->canBePriced())
        <section class="panel">
            <h2>الكمية والسعر</h2>
            <p class="muted small">تحددها المطبعة عادةً من حسابها. يمكنك إدخالها أو تصحيحها هنا اختيارياً، ويُسجَّل ذلك باسمك.</p>
            @include('finance._pricing-form', ['action' => route('admin.finance.purchases.price', $order), 'defaultPrice' => $order->supplier->default_page_price])
        </section>
        @endif
        @if($targets)
        <section class="panel">
            <h2>تغيير الحالة</h2>
            <p class="muted small">عادةً تغيّرها المطبعة من حسابها. يمكنك تسجيلها هنا إن لم يكن لها حساب أو عند الاستلام.</p>
            <form method="post" action="{{ route('admin.finance.purchases.status', $order) }}" class="form">
                @csrf
                <div class="inline">@foreach($targets as $t)<button class="btn sm @if($loop->first) primary @endif" name="status" value="{{ $t }}" type="submit">← {{ \App\Models\PurchaseOrder::STATUSES[$t] }}</button>@endforeach</div>
                <input name="note" maxlength="500" placeholder="ملاحظة (اختياري)" aria-label="ملاحظة على تغيير الحالة">
            </form>
        </section>
        @endif

        @if($order->isEditable())
        <section class="panel">
            <h2>تعديل أو إلغاء</h2>
            <p class="muted small">متاح فقط ما دام الطلب «بانتظار المطبعة».</p>
            <a class="btn" href="{{ route('admin.finance.purchases.edit', $order) }}">تعديل الطلب</a>
            <details style="margin-top:10px">
                <summary class="btn danger">إلغاء الطلب</summary>
                <form method="post" action="{{ route('admin.finance.purchases.cancel', $order) }}" class="form" style="margin-top:8px">
                    @csrf
                    <textarea name="reason" rows="2" required minlength="3" maxlength="500" placeholder="سبب الإلغاء" aria-label="سبب الإلغاء"></textarea>
                    <div><button class="btn danger" type="submit">تأكيد الإلغاء</button></div>
                </form>
            </details>
        </section>
        @elseif($order->status !== 'cancelled')
        <div class="alert warn small">🔒 بدأت المطبعة العمل على الطلب، فلا يمكن تعديله أو إلغاؤه. المبلغ محسوب على المدرسة.</div>
        @endif
        @endperm

        <section class="panel">
            <h2>سجل الطلب</h2>
            @include('finance._events')
        </section>
    </aside>
</div>
@endsection
