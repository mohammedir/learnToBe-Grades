@php($flow = \App\Models\PurchaseOrder::FLOW)
@php($at = array_search($order->status, $flow, true))
@if($order->status === 'cancelled')
    <div class="alert bad"><b>الطلب ملغى</b>@if($order->cancel_reason) · السبب: {{ $order->cancel_reason }}@endif @if($order->statusUser) · ألغاه {{ $order->statusUser->name }}@endif @if($order->status_at) في {{ $order->status_at->format('Y/m/d H:i') }}@endif</div>
@else
<ol class="stepper" aria-label="مراحل الطلب">
    @foreach($flow as $i => $s)
        <li @class(['done' => $i < $at, 'now' => $i === $at])><span class="dot" aria-hidden="true">{{ $i < $at ? '✓' : $i + 1 }}</span><span>{{ \App\Models\PurchaseOrder::STATUSES[$s] }}</span></li>
    @endforeach
</ol>
@endif
