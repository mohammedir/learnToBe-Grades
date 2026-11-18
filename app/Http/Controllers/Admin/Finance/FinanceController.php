<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPayment;

/** قسم المالية: نظرة عامة على المشتريات والأرصدة */
class FinanceController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::orderByDesc('is_active')->orderBy('name')->get()->map(function ($s) {
            $s->setAttribute('totals', $s->balance());

            return $s;
        });

        $month = now()->startOfMonth();

        return view('admin.finance.index', [
            'suppliers' => $suppliers,
            'balance' => $suppliers->sum(fn ($s) => $s->totals['balance']),
            'open' => PurchaseOrder::whereIn('status', ['pending', 'preparing', 'ready'])->count(),
            'ready' => PurchaseOrder::where('status', 'ready')->count(),
            'monthOrders' => (float) PurchaseOrder::where('status', '!=', 'cancelled')->where('created_at', '>=', $month)->sum('amount'),
            'monthPaid' => (float) SupplierPayment::where('paid_at', '>=', $month->toDateString())->sum('amount'),
            // يحتاج انتباهك: جاهز للاستلام، بانتظار التسعير، متأخر
            'attention' => PurchaseOrder::with(['supplier', 'section.gradeLevel', 'gradeLevel', 'recipient'])
                ->where(fn ($q) => $q->where('status', 'ready')
                    ->orWhere(fn ($w) => $w->whereIn('status', ['pending', 'preparing'])->whereNull('amount'))
                    ->orWhere(fn ($w) => $w->whereIn('status', ['pending', 'preparing'])->whereDate('needed_by', '<', now()->toDateString())))
                ->orderByRaw("CASE status WHEN 'ready' THEN 0 ELSE 1 END")->orderBy('needed_by')->limit(12)->get(),
            'flow' => PurchaseOrder::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'recent' => PurchaseOrder::with(['supplier', 'section.gradeLevel', 'gradeLevel', 'recipient', 'creator'])->latest('id')->limit(8)->get(),
            'payments' => SupplierPayment::with(['supplier', 'recorder'])->latest('paid_at')->latest('id')->limit(5)->get(),
        ]);
    }
}
