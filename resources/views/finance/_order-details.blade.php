<dl class="po-details">
    <div><dt>المطبعة</dt><dd>{{ $order->supplier->name }}</dd></div>
    <div><dt>الصف / الشعبة</dt><dd>{{ $order->class_label }}</dd></div>
    @if($order->subject)<div><dt>المادة</dt><dd>{{ $order->subject->name }}</dd></div>@endif
    <div><dt>المستلم من المطبعة</dt><dd><b>{{ $order->recipient_label }}</b>@if($order->recipient?->phone) <a class="wa-mini" href="{{ \App\Support\Phone::whatsapp($order->recipient->phone) }}" target="_blank" rel="noopener">واتساب</a>@endif</dd></div>
    <div><dt>طريقة الحساب</dt><dd>{{ $order->pricing_label }}@if($order->pricing === 'per_page' && $order->isPriced()) <span class="muted">({{ $order->pages * $order->copies }} ورقة)</span>@endif</dd></div>
    <div class="amount"><dt>المبلغ</dt><dd>@if($order->isPriced()){{ $order->amount_label }}@if($order->pricer)<small class="muted priced-by">سعّره: {{ $order->pricer->name }}</small>@endif @else<span class="pill part">بانتظار تسعير المطبعة</span>@endif</dd></div>
    @if($order->needed_by)<div><dt>مطلوب قبل</dt><dd>{{ $order->needed_by->format('Y/m/d') }}@if($order->needed_by->isPast() && in_array($order->status, ['pending', 'preparing'])) <span class="pill off">متأخر</span>@endif</dd></div>@endif
    <div><dt>طلبه من الإدارة</dt><dd>{{ $order->creator?->name ?? '—' }} <span class="muted">· {{ $order->created_at->format('Y/m/d H:i') }}</span></dd></div>
    @if($order->received_at)<div><dt>تاريخ الاستلام</dt><dd>{{ $order->received_at->format('Y/m/d H:i') }}</dd></div>@endif
</dl>
@if($order->details)<div class="po-note"><b>تفاصيل للمطبعة:</b> {!! nl2br(e($order->details)) !!}</div>@endif
