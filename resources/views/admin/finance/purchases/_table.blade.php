<div class="scroll"><table class="orders-table">
    <thead><tr>
        <th>الطلب</th><th>الصف / الشعبة</th>
        @unless($compact ?? false)<th>المطبعة</th>@endunless
        <th>المستلم</th><th class="num">المبلغ</th><th class="c">الحالة</th>
        @unless($compact ?? false)<th>طلبه</th><th>التاريخ</th>@endunless
    </tr></thead>
    <tbody>
    @forelse($orders as $o)
        <tr @class(['is-cancelled' => $o->status === 'cancelled'])>
            <td><a class="order-link" href="{{ route('admin.finance.purchases.show', $o) }}"><small dir="ltr">{{ $o->number }}</small> <b>{{ $o->title }}</b></a>@unless($compact ?? false)<div class="muted small">{{ $o->pricing_label }}@if($o->subject) · {{ $o->subject->name }}@endif</div>@endunless</td>
            <td>{{ $o->class_label }}</td>
            @unless($compact ?? false)<td>{{ $o->supplier->name }}</td>@endunless
            <td>{{ $o->recipient_label }}</td>
            <td class="num">@if($o->isPriced())<b>{{ $o->amount_label }}</b>@else<span class="pill part">بانتظار التسعير</span>@endif</td>
            <td class="c"><span class="spill st-{{ $o->status }}"><span class="ocard-ico">@include('finance._icon', ['s' => $o->status])</span>{{ $o->status_label }}</span>@if($d = $o->dueBadge())@if($d['tone'] !== 'plain')<div class="due due-{{ $d['tone'] }}">{{ $d['text'] }}</div>@endif @endif</td>
            @unless($compact ?? false)<td class="muted">{{ $o->creator?->name ?? '—' }}</td><td class="muted">{{ $o->created_at->format('Y/m/d') }}</td>@endunless
        </tr>
    @empty
        <tr><td colspan="8" class="c muted">لا توجد طلبات.</td></tr>
    @endforelse
    </tbody>
</table></div>
