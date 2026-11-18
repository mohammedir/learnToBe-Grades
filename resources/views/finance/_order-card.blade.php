{{-- بطاقة طلب شراء. المتغيرات: $o، $actor = admin|supplier، $supplierPrice (اختياري) --}}
@php($isSup = ($actor ?? 'admin') === 'supplier')
@php($href = $isSup ? route('supplier.orders.show', $o) : route('admin.finance.purchases.show', $o))
@php($next = $o->nextStatus())
@php($due = $o->dueBadge())
@php($step = array_search($o->status, \App\Models\PurchaseOrder::FLOW, true))
<article class="ocard st-{{ $o->status }}" id="o{{ $o->id }}">
    <header class="ocard-head">
        <span class="ocard-ico">@include('finance._icon', ['s' => $o->status])</span>
        <div class="ocard-title">
            <a href="{{ $href }}"><b>{{ $o->title }}</b></a>
            <span class="ocard-sub"><span dir="ltr">{{ $o->number }}</span> · {{ $o->created_at->locale('ar')->diffForHumans() }}@unless($isSup) · طلبه {{ $o->creator?->name ?? '—' }}@endunless</span>
        </div>
        <span class="ostatus">{{ $o->status_label }}</span>
    </header>

    @if($step !== false)
    <div class="oprog" role="img" aria-label="المرحلة {{ $step + 1 }} من 4">@foreach(\App\Models\PurchaseOrder::FLOW as $i => $s)<i @class(['on' => $i <= $step])></i>@endforeach</div>
    @endif

    <div class="ofacts">
        <span class="ofact"><small>الصف</small>{{ $o->class_label }}</span>
        <span class="ofact"><small>المستلم</small><span class="who"><i aria-hidden="true">{{ mb_substr(preg_replace('/^(أ\.|أ\/|م\.|د\.)\s*/u', '', $o->recipient_label), 0, 1) }}</i>{{ $o->recipient_label }}</span></span>
        <span class="ofact amount"><small>المبلغ</small>@if($o->isPriced()){{ $o->amount_label }}@else<em>بانتظار التسعير</em>@endif</span>
        @if($due)<span class="ofact"><small>الموعد</small><span class="due due-{{ $due['tone'] }}">{{ $due['text'] }}</span></span>@endif
    </div>
    @if($o->pricing === 'per_page' && $o->isPriced())<div class="ocalc">{{ $o->pricing_label }} · {{ $o->pages * $o->copies }} ورقة</div>@endif
    @if($o->details)<p class="onote">{{ \Illuminate\Support\Str::limit($o->details, 140) }}</p>@endif
    @if($o->status === 'cancelled' && $o->cancel_reason)<p class="onote bad">سبب الإلغاء: {{ $o->cancel_reason }}</p>@endif

    @if($isSup && $o->canBePriced())
        <details class="s-price" @if(! $o->isPriced()) open @endif>
            <summary>{{ $o->isPriced() ? 'تصحيح الكمية والسعر' : 'حدّدوا الكمية والسعر' }}</summary>
            @include('finance._pricing-form', ['order' => $o, 'action' => route('supplier.orders.price', $o), 'defaultPrice' => $supplierPrice ?? null])
        </details>
    @endif

    <footer class="ocard-foot">
        @if($next && ($isSup || ($next === 'received' && auth()->user()->hasPermission('finance', 'edit'))))
            @if(! $o->isPriced() && in_array($next, ['ready', 'received']))
                <span class="muted small">بعد تحديد الكمية والسعر يمكن تحويله إلى «{{ \App\Models\PurchaseOrder::STATUSES[$next] }}».</span>
            @else
                <form method="post" action="{{ $isSup ? route('supplier.orders.status', $o) : route('admin.finance.purchases.status', $o) }}">
                    @csrf
                    <button class="btn {{ $isSup ? 'primary' : '' }} sm next-btn" name="status" value="{{ $next }}" type="submit">{{ \App\Models\PurchaseOrder::actionLabel($next, $isSup) }} ←</button>
                </form>
            @endif
        @endif
        <a class="ocard-more" href="{{ $href }}">التفاصيل</a>
    </footer>
</article>
