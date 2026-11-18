<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** دفعات المدرسة للمطبعة */
class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->only(['supplier', 'from', 'to']);
        $q = SupplierPayment::with(['supplier', 'recorder'])
            ->when($f['supplier'] ?? null, fn ($q, $v) => $q->where('supplier_id', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '<=', $v));

        $suppliers = Supplier::orderBy('name')->get();

        return view('admin.finance.payments', [
            'payments' => (clone $q)->latest('paid_at')->latest('id')->paginate(30)->withQueryString(),
            'sum' => (float) $q->sum('amount'),
            'f' => $f,
            'suppliers' => $suppliers,
            'selected' => $suppliers->firstWhere('id', (int) ($f['supplier'] ?? 0)) ?? $suppliers->where('is_active', true)->first(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(SupplierPayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['supplier_id' => 'المطبعة', 'amount' => 'المبلغ', 'paid_at' => 'تاريخ الدفع', 'method' => 'طريقة الدفع', 'reference' => 'رقم الإيصال']);
        $data['recorded_by'] = $request->user()->id;
        $p = SupplierPayment::create($data);
        $b = $p->supplier->balance();

        return back()->with('status', 'سُجّلت دفعة '.\App\Support\Money::fmt($p->amount).' لـ'.$p->supplier->name.'. الرصيد المتبقي الآن: '.\App\Support\Money::fmt($b['balance']).'.');
    }

    public function destroy(SupplierPayment $payment)
    {
        $payment->delete();

        return back()->with('status', 'حُذفت الدفعة ('.\App\Support\Money::fmt($payment->amount).') وأُعيد حساب الرصيد.');
    }
}
