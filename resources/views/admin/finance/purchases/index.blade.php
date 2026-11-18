@extends('layouts.app')
@section('title', 'المشتريات')
@section('module', 'finance')
@section('content')
@include('admin.finance._tabs')
@php($base = array_filter(\Illuminate\Support\Arr::except($f, 'status')))
<div class="page-head">
    <div><h1>المشتريات</h1><div class="sub">تابع كل طلب من لحظة إرساله للمطبعة حتى استلامه. التعديل والإلغاء متاحان فقط وهو «بانتظار المطبعة».</div></div>
    <div class="spacer"></div>
    <div class="seg" role="group" aria-label="طريقة العرض">
        <a href="{{ route('admin.finance.purchases.index', $base + ['view' => 'board']) }}" @class(['on' => $view === 'board'])>▦ لوحة</a>
        <a href="{{ route('admin.finance.purchases.index', array_filter($f) + ['view' => 'list']) }}" @class(['on' => $view !== 'board'])>☰ قائمة</a>
    </div>
    @perm('finance', 'edit')<a class="btn primary" href="{{ route('admin.finance.purchases.create') }}">+ طلب شراء جديد</a>@endperm
</div>

<form class="panel filter-panel compact" method="get">
    <input type="hidden" name="view" value="{{ $view }}">
    @if(! empty($f['status']) && $view !== 'board')<input type="hidden" name="status" value="{{ $f['status'] }}">@endif
    <div class="filter-row">
        <input type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="🔍 بحث بالعنوان أو رقم الطلب أو المستلم" aria-label="بحث">
        @if($suppliers->count() > 1)<select name="supplier" aria-label="المطبعة"><option value="">كل المطابع</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(($f['supplier'] ?? '') == $s->id)>{{ $s->name }}</option>@endforeach</select>@endif
        <select name="grade" aria-label="الصف"><option value="">كل الصفوف</option>@foreach($grades as $g)<option value="{{ $g->id }}" @selected(($f['grade'] ?? '') == $g->id)>{{ $g->name }}</option>@endforeach</select>
        <select name="recipient" aria-label="المستلم"><option value="">كل المستلمين</option>@foreach($people as $p)<option value="{{ $p->id }}" @selected(($f['recipient'] ?? '') == $p->id)>{{ $p->name }}</option>@endforeach</select>
        <select name="creator" aria-label="طلبه"><option value="">طلبه: الكل</option>@foreach($creators as $p)<option value="{{ $p->id }}" @selected(($f['creator'] ?? '') == $p->id)>{{ $p->name }}</option>@endforeach</select>
        <details class="more-f" @if(! empty($f['from']) || ! empty($f['to'])) open @endif><summary>التاريخ</summary>
            <label class="date-f">من <input type="date" name="from" value="{{ $f['from'] ?? '' }}"></label>
            <label class="date-f">إلى <input type="date" name="to" value="{{ $f['to'] ?? '' }}"></label>
        </details>
        <button class="btn" type="submit">تصفية</button>
        @if(array_filter(\Illuminate\Support\Arr::except($f, 'status')))<a class="btn sm" href="{{ route('admin.finance.purchases.index', ['view' => $view]) }}">مسح</a>@endif
    </div>
</form>

@if($view === 'board')
    <div class="board">
        @foreach($board as $status => $items)
            <section class="bcol st-{{ $status }}" id="col-{{ $status }}" aria-label="{{ \App\Models\PurchaseOrder::STATUSES[$status] }}">
                <header class="bcol-head">
                    <span class="ocard-ico">@include('finance._icon', ['s' => $status])</span>
                    <b>{{ \App\Models\PurchaseOrder::STATUSES[$status] }}</b>
                    <span class="bcount">{{ $status === 'received' ? ($counts['received'] ?? 0) : $items->count() }}</span>
                </header>
                @if($status !== 'received' && $items->count())<div class="bsum">{{ \App\Support\Money::fmt($items->sum('amount')) }}@if($items->whereNull('amount')->count()) · {{ $items->whereNull('amount')->count() }} بلا تسعير@endif</div>@endif
                <div class="bcol-body">
                    @forelse($items as $o)
                        @include('finance._order-card', ['o' => $o, 'actor' => 'admin'])
                    @empty
                        <div class="bempty">{{ ['pending' => 'لا طلبات تنتظر المطبعة', 'preparing' => 'لا شيء قيد التجهيز', 'ready' => 'لا طلبات جاهزة', 'received' => 'لم يُستلم شيء بعد'][$status] }}</div>
                    @endforelse
                </div>
                @if($status === 'received' && ($counts['received'] ?? 0) > $items->count())<a class="bmore" href="{{ route('admin.finance.purchases.index', $base + ['status' => 'received', 'view' => 'list']) }}">كل المستلمة ({{ $counts['received'] }}) ←</a>@endif
            </section>
        @endforeach
    </div>
    @if($counts['cancelled'] ?? 0)<p class="muted small" style="text-align:center"><a href="{{ route('admin.finance.purchases.index', $base + ['status' => 'cancelled', 'view' => 'list']) }}">الطلبات الملغاة ({{ $counts['cancelled'] }})</a></p>@endif
@else
    <div class="status-tabs">
        <a href="{{ route('admin.finance.purchases.index', $base + ['view' => 'list']) }}" @class(['on' => empty($f['status'])])>الكل</a>
        <a href="{{ route('admin.finance.purchases.index', $base + ['status' => 'open', 'view' => 'list']) }}" @class(['on' => ($f['status'] ?? '') === 'open'])>المفتوحة</a>
        @foreach(\App\Models\PurchaseOrder::STATUSES as $k => $label)
            <a href="{{ route('admin.finance.purchases.index', $base + ['status' => $k, 'view' => 'list']) }}" @class(['on' => ($f['status'] ?? '') === $k, 'st-'.$k])>{{ $label }} <small>{{ $counts[$k] ?? 0 }}</small></a>
        @endforeach
    </div>
    <div class="inline" style="justify-content:space-between;margin-bottom:8px">
        <span class="muted">{{ $orders->total() }} طلب</span>
        <span>إجمالي غير الملغى: <b>{{ \App\Support\Money::fmt($sum) }}</b></span>
    </div>
    @include('admin.finance.purchases._table', ['orders' => $orders])
    <div style="margin-top:12px">{{ $orders->links('partials.pager') }}</div>
@endif
@endsection
