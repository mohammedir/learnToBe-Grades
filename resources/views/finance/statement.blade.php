@extends('layouts.app')
@section('title', 'كشف حساب '.$supplier->name)
@if($mode === 'admin') @section('module', 'finance') @endif
@section('content')
@if($mode === 'admin') @include('admin.finance._tabs') @endif
@php($r = $mode === 'admin' ? 'admin.finance.statement' : 'supplier.statement')
@php($q = array_filter($f))
<div class="page-head">
    <div><h1>كشف حساب {{ $supplier->name }}</h1><div class="sub">الطلبات (مدين على المدرسة) والدفعات (دائن) والرصيد بعد كل حركة. الطلبات الملغاة لا تُحسب.</div></div>
    <div class="spacer"></div>
    <a class="btn" target="_blank" href="{{ $mode === 'admin' ? route('admin.finance.statement.print', ['supplier' => $supplier] + $q) : route('supplier.statement.print', $q) }}">🖨 طباعة</a>
    @if($mode === 'admin')<a class="btn" href="{{ route('admin.finance.statement.export', ['supplier' => $supplier] + $q) }}">⬇ Excel</a>@endif
</div>

<form class="panel filter-panel" method="get" action="{{ $mode === 'admin' ? route($r, $supplier) : route($r) }}">
    <div class="filter-row">
        @if($mode === 'admin' && $suppliers->count() > 1)
            <select aria-label="المطبعة" onchange="location = this.value">@foreach($suppliers as $s)<option value="{{ route($r, ['supplier' => $s] + $q) }}" @selected($s->is($supplier))>{{ $s->name }}</option>@endforeach</select>
        @endif
        <label class="date-f">من <input type="date" name="from" value="{{ $f['from'] ?? '' }}"></label>
        <label class="date-f">إلى <input type="date" name="to" value="{{ $f['to'] ?? '' }}"></label>
        <button class="btn" type="submit">عرض</button>
        <a class="btn sm" href="{{ $mode === 'admin' ? route($r, ['supplier' => $supplier, 'from' => now()->startOfMonth()->format('Y-m-d')]) : route($r, ['from' => now()->startOfMonth()->format('Y-m-d')]) }}">هذا الشهر</a>
        @if($q)<a class="btn sm" href="{{ $mode === 'admin' ? route($r, $supplier) : route($r) }}">من البداية</a>@endif
    </div>
</form>

<div class="stats">
    @if($st['from'])<div class="stat"><b>{{ \App\Support\Money::fmt($st['opening']) }}</b><span>رصيد افتتاحي ({{ $st['from']->format('Y/m/d') }})</span></div>@endif
    <div class="stat"><b>{{ \App\Support\Money::fmt($st['debit']) }}</b><span>طلبات الفترة</span></div>
    <div class="stat"><b>{{ \App\Support\Money::fmt($st['credit']) }}</b><span>دفعات الفترة</span></div>
    <div class="stat money-due"><b>{{ \App\Support\Money::fmt($st['closing']) }}</b><span>{{ $st['closing'] < 0 ? 'رصيد لصالح المدرسة' : 'المتبقي على المدرسة' }}@if($st['to']) حتى {{ $st['to']->format('Y/m/d') }}@endif</span></div>
</div>

<div class="scroll"><table class="ledger-table">
    <thead><tr><th>التاريخ</th><th>المرجع</th><th>البيان</th><th class="num">مدين (طلبات)</th><th class="num">دائن (دفعات)</th><th class="num">الرصيد</th></tr></thead>
    <tbody>
        @if($st['from'])<tr class="opening"><td>{{ $st['from']->format('Y/m/d') }}</td><td></td><td>رصيد افتتاحي</td><td></td><td></td><td class="num"><b>{{ \App\Support\Money::fmt($st['opening'], false) }}</b></td></tr>@endif
        @forelse($st['rows'] as $l)
            <tr>
                <td>{{ $l['date']->format('Y/m/d') }}</td>
                <td dir="ltr" style="text-align:right">@if($l['type'] === 'order')<a href="{{ $mode === 'admin' ? route('admin.finance.purchases.show', $l['model']) : route('supplier.orders.show', $l['model']) }}">{{ $l['ref'] }}</a>@else{{ $l['ref'] }}@endif</td>
                <td>{{ $l['desc'] }}<div class="muted small">{{ $l['detail'] }}@if($l['by']) · {{ $l['type'] === 'order' ? 'طلبه' : 'سجّلها' }}: {{ $l['by'] }}@endif</div></td>
                <td class="num">@if($l['type'] === 'order' && ! $l['model']->isPriced())<span class="pill part">بانتظار التسعير</span>@else{{ $l['debit'] ? \App\Support\Money::fmt($l['debit'], false) : '' }}@endif</td>
                <td class="num credit">{{ $l['credit'] ? \App\Support\Money::fmt($l['credit'], false) : '' }}</td>
                <td class="num"><b>{{ \App\Support\Money::fmt($l['balance'], false) }}</b></td>
            </tr>
        @empty
            <tr><td colspan="6" class="c muted">لا توجد حركات في هذه الفترة.</td></tr>
        @endforelse
    </tbody>
    <tfoot><tr><td colspan="3">الإجمالي</td><td class="num">{{ \App\Support\Money::fmt($st['debit'], false) }}</td><td class="num">{{ \App\Support\Money::fmt($st['credit'], false) }}</td><td class="num">{{ \App\Support\Money::fmt($st['closing'], false) }}</td></tr></tfoot>
</table></div>
@endsection
