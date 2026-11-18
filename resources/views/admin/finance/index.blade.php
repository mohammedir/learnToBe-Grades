@extends('layouts.app')
@section('title', 'المالية')
@section('module', 'finance')
@section('content')
@include('admin.finance._tabs')
@php($totalOrders = $suppliers->sum(fn ($s) => $s->totals['orders']))
@php($totalPaid = $suppliers->sum(fn ($s) => $s->totals['paid']))
@php($paidPct = $totalOrders > 0 ? min(100, round($totalPaid / $totalOrders * 100)) : 0)

<section class="fin-hero">
    <div class="fin-hero-main">
        <span class="fin-label">المتبقي على المدرسة للمطابع</span>
        <b class="fin-big">{{ \App\Support\Money::fmt($balance) }}</b>
        <div class="fin-bar" role="img" aria-label="سُدّد {{ $paidPct }}% من إجمالي الطلبات"><i style="width: {{ $paidPct }}%"></i></div>
        <span class="fin-small">سُدّد {{ \App\Support\Money::fmt($totalPaid) }} من {{ \App\Support\Money::fmt($totalOrders) }} ({{ $paidPct }}%)</span>
    </div>
    <div class="fin-hero-side">
        <div><span>مشتريات هذا الشهر</span><b>{{ \App\Support\Money::fmt($monthOrders) }}</b></div>
        <div><span>مدفوع هذا الشهر</span><b>{{ \App\Support\Money::fmt($monthPaid) }}</b></div>
        @perm('finance', 'edit')
        <div class="fin-actions">
            <a class="btn" href="{{ route('admin.finance.purchases.create') }}">+ طلب شراء</a>
            <a class="btn ghost" href="{{ route('admin.finance.payments.index') }}#new">تسجيل دفعة</a>
        </div>
        @endperm
    </div>
</section>

{{-- مسار الطلبات: كم طلباً في كل مرحلة --}}
<nav class="flow-strip" aria-label="الطلبات حسب المرحلة">
    @foreach(\App\Models\PurchaseOrder::FLOW as $s)
        <a class="flow-step st-{{ $s }}" href="{{ route('admin.finance.purchases.index', ['view' => 'board']) }}#col-{{ $s }}">
            <span class="ocard-ico">@include('finance._icon', ['s' => $s])</span>
            <b>{{ $flow[$s] ?? 0 }}</b><span>{{ \App\Models\PurchaseOrder::STATUSES[$s] }}</span>
        </a>
    @endforeach
</nav>

@if($suppliers->isEmpty())
    <div class="panel empty-state"><b>لم تُضف أي مطبعة بعد</b><p>ابدأ بإضافة المطبعة التي تتعامل معها المدرسة، ثم أنشئ طلبات الشراء.</p>
        @perm('finance', 'edit')<p><a class="btn primary" href="{{ route('admin.finance.suppliers.create') }}">إضافة المطبعة</a></p>@endperm</div>
@else
<div class="fin-grid">
    <section class="panel attention">
        <h2>يحتاج انتباهك</h2>
        @forelse($attention as $o)
            @php($d = $o->dueBadge())
            <a class="att-row st-{{ $o->status }}" href="{{ route('admin.finance.purchases.show', $o) }}">
                <span class="ocard-ico">@include('finance._icon', ['s' => $o->status])</span>
                <span class="att-text"><b>{{ $o->title }}</b>
                    <small>{{ $o->class_label }} · {{ $o->recipient_label }}</small></span>
                <span class="att-why">
                    @if($o->status === 'ready')<span class="tag ready">جاهز للاستلام</span>@endif
                    @if(! $o->isPriced())<span class="tag price">بانتظار التسعير</span>@endif
                    @if($d && $d['tone'] === 'late')<span class="tag late">{{ $d['text'] }}</span>@endif
                </span>
            </a>
        @empty
            <div class="all-good">✓ لا شيء يحتاج متابعة الآن</div>
        @endforelse
    </section>

    <div class="fin-col">
        @foreach($suppliers as $s)
            @php($pct = $s->totals['orders'] > 0 ? min(100, round($s->totals['paid'] / $s->totals['orders'] * 100)) : 0)
            <section class="panel sup-card">
                <div class="inline" style="justify-content:space-between"><h2 style="margin:0">{{ $s->name }}</h2>@unless($s->is_active)<span class="pill off">موقوف</span>@endunless</div>
                <div class="sup-due"><span>المتبقي</span><b>{{ \App\Support\Money::fmt($s->totals['balance']) }}</b></div>
                <div class="fin-bar light" role="img" aria-label="سُدّد {{ $pct }}%"><i style="width: {{ $pct }}%"></i></div>
                <div class="sup-meta"><span>الطلبات {{ \App\Support\Money::fmt($s->totals['orders']) }}</span><span>المدفوع {{ \App\Support\Money::fmt($s->totals['paid']) }}</span></div>
                @if($s->totals['unpriced'])<div class="muted small">⏳ {{ $s->totals['unpriced'] }} طلب بانتظار التسعير لا يدخل في المبالغ بعد</div>@endif
                <div class="inline">
                    <a class="btn sm primary" href="{{ route('admin.finance.statement', $s) }}">كشف الحساب</a>
                    @perm('finance', 'edit')<a class="btn sm" href="{{ route('admin.finance.payments.index', ['supplier' => $s->id]) }}#new">تسجيل دفعة</a>@endperm
                </div>
            </section>
        @endforeach

        <section class="panel">
            <div class="inline" style="justify-content:space-between"><h2 style="margin:0">آخر الدفعات</h2><a href="{{ route('admin.finance.payments.index') }}">الكل ←</a></div>
            <ul class="pay-list">
            @forelse($payments as $p)
                <li><span><b>{{ \App\Support\Money::fmt($p->amount) }}</b> <small class="muted">{{ $p->method_label }} · {{ $p->recorder?->name ?? '—' }}</small></span><time class="muted small">{{ $p->paid_at->format('Y/m/d') }}</time></li>
            @empty
                <li class="muted">لا توجد دفعات بعد.</li>
            @endforelse
            </ul>
        </section>
    </div>
</div>

<section class="panel">
    <div class="inline" style="justify-content:space-between"><h2 style="margin:0">آخر الطلبات</h2><a href="{{ route('admin.finance.purchases.index') }}">لوحة المشتريات ←</a></div>
    @include('admin.finance.purchases._table', ['orders' => $recent, 'compact' => true])
</section>
@endif
@endsection
