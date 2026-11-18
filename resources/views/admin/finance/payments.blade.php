@extends('layouts.app')
@section('title', 'الدفعات')
@section('module', 'finance')
@section('content')
@include('admin.finance._tabs')
<div class="page-head">
    <div><h1>الدفعات</h1><div class="sub">كل دفعة تُسجَّل تُخصم من المبلغ المتراكم على المدرسة للمطبعة.</div></div>
</div>

@if($suppliers->isEmpty())
    <div class="panel empty-state"><b>أضف المطبعة أولاً</b>@perm('finance', 'edit')<p><a class="btn primary" href="{{ route('admin.finance.suppliers.create') }}">إضافة المطبعة</a></p>@endperm</div>
@else
@perm('finance', 'edit')
<form class="panel pay-form" method="post" action="{{ route('admin.finance.payments.store') }}" id="new">
    @csrf
    <h2>تسجيل دفعة جديدة</h2>
    @if($selected)@php($b = $selected->balance())<p class="muted small" style="margin-top:0">المتبقي حالياً على المدرسة لـ{{ $selected->name }}: <b class="due-text">{{ \App\Support\Money::fmt($b['balance']) }}</b></p>@endif
    <div class="po-grid">
        <div class="field"><label for="p-supplier">المطبعة</label><select id="p-supplier" name="supplier_id" required>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id', $selected?->id) == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
        <div class="field"><label for="p-amount">المبلغ ({{ \App\Support\Money::currency() }})</label><input id="p-amount" type="number" name="amount" min="0.01" step="0.01" inputmode="decimal" required value="{{ old('amount') }}"></div>
        <div class="field"><label for="p-date">تاريخ الدفع</label><input id="p-date" type="date" name="paid_at" required max="{{ now()->format('Y-m-d') }}" value="{{ old('paid_at', now()->format('Y-m-d')) }}"></div>
        <div class="field"><label for="p-method">طريقة الدفع</label><select id="p-method" name="method">@foreach(\App\Models\SupplierPayment::METHODS as $k => $l)<option value="{{ $k }}" @selected(old('method') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="p-ref">رقم الإيصال / الحوالة</label><input id="p-ref" name="reference" maxlength="100" value="{{ old('reference') }}"></div>
        <div class="field"><label for="p-note">ملاحظة</label><input id="p-note" name="note" maxlength="500" value="{{ old('note') }}" placeholder="مثال: دفعة حساب شهر 11"></div>
    </div>
    <button class="btn primary" type="submit">تسجيل الدفعة</button>
</form>
@endperm

<form class="panel filter-panel" method="get">
    <div class="filter-row">
        <select name="supplier" aria-label="المطبعة"><option value="">كل المطابع</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(($f['supplier'] ?? '') == $s->id)>{{ $s->name }}</option>@endforeach</select>
        <label class="date-f">من <input type="date" name="from" value="{{ $f['from'] ?? '' }}"></label>
        <label class="date-f">إلى <input type="date" name="to" value="{{ $f['to'] ?? '' }}"></label>
        <button class="btn" type="submit">تصفية</button>
        <span class="spacer"></span><span>المجموع: <b>{{ \App\Support\Money::fmt($sum) }}</b></span>
    </div>
</form>
<div class="scroll"><table>
    <thead><tr><th>التاريخ</th><th>المطبعة</th><th class="num">المبلغ</th><th>الطريقة</th><th>الإيصال</th><th>ملاحظة</th><th>سجّلها</th><th></th></tr></thead>
    <tbody>
    @forelse($payments as $p)
        <tr>
            <td>{{ $p->paid_at->format('Y/m/d') }}</td><td>{{ $p->supplier->name }}</td><td class="num"><b>{{ \App\Support\Money::fmt($p->amount) }}</b></td>
            <td>{{ $p->method_label }}</td><td dir="ltr" style="text-align:right">{{ $p->reference ?: '—' }}</td><td>{{ $p->note }}</td>
            <td class="muted">{{ $p->recorder?->name ?? '—' }} <small>{{ $p->created_at->format('Y/m/d H:i') }}</small></td>
            <td>@perm('finance', 'edit')<form method="post" action="{{ route('admin.finance.payments.destroy', $p) }}" onsubmit="return confirm('حذف هذه الدفعة؟ سيزيد المبلغ المتبقي على المدرسة.')">@csrf @method('delete')<button class="btn sm danger" type="submit">حذف</button></form>@endperm</td>
        </tr>
    @empty
        <tr><td colspan="8" class="c muted">لا توجد دفعات مسجلة.</td></tr>
    @endforelse
    </tbody>
</table></div>
<div style="margin-top:12px">{{ $payments->links('partials.pager') }}</div>
@endif
@endsection
