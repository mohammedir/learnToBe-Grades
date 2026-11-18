<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\SupplierPayment;
use App\Services\PurchaseFlow;
use App\Services\SupplierStatement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** بوابة المطبعة: ترى طلبات المدرسة الموجهة لها فقط وتغيّر حالتها، وتطّلع على كشف الحساب */
class PortalController extends Controller
{
    public function index(Request $request)
    {
        $supplier = $this->supplier($request);
        $tab = $request->query('tab', 'open');
        $q = PurchaseOrder::with(['section.gradeLevel', 'gradeLevel', 'subject', 'recipient', 'creator'])->where('supplier_id', $supplier->id);
        $q = match ($tab) {
            'received' => $q->where('status', 'received')->latest('received_at'),
            'cancelled' => $q->where('status', 'cancelled')->latest('id'),
            'all' => $q->latest('id'),
            default => $q->whereIn('status', ['pending', 'preparing', 'ready'])->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'preparing' THEN 1 ELSE 2 END")->orderBy('needed_by')->orderBy('id'),
        };

        $counts = PurchaseOrder::where('supplier_id', $supplier->id)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return view('supplier.home', [
            'supplier' => $supplier,
            // المفتوحة تُعرض مجمّعة حسب المرحلة، والسجل بصفحات
            'groups' => $tab === 'open' ? $q->get()->groupBy('status') : null,
            'orders' => $tab === 'open' ? null : $q->paginate(30)->withQueryString(),
            'tab' => $tab,
            'counts' => $counts,
            'totals' => $supplier->balance(),
        ]);
    }

    public function show(Request $request, PurchaseOrder $order)
    {
        $this->own($request, $order);
        $order->load(['section.gradeLevel', 'gradeLevel', 'subject', 'recipient', 'creator', 'events.user']);

        return view('supplier.show', ['order' => $order, 'targets' => PurchaseFlow::allowedTargets($order), 'defaultPrice' => $order->supplier->default_page_price]);
    }

    public function status(Request $request, PurchaseOrder $order)
    {
        $this->own($request, $order);
        $data = $request->validate(['status' => ['required', Rule::in(PurchaseOrder::FLOW)], 'note' => ['nullable', 'string', 'max:500']]);
        $error = PurchaseFlow::advance($order, $data['status'], $request->user(), $data['note'] ?? null);

        return back()->with($error ? 'warning' : 'status', $error ?: "الطلب {$order->number}: ".$order->status_label);
    }

    /** المطبعة تحدد عدد الأوراق والنسخ وسعر الورقة */
    public function price(Request $request, PurchaseOrder $order)
    {
        $this->own($request, $order);
        $d = \App\Http\Controllers\Admin\Finance\PurchaseController::pricingInput($request);
        $error = PurchaseFlow::price($order, $d['pages'], $d['copies'], (float) $d['unit_price'], $request->user());

        return back()->with($error ? 'warning' : 'status', $error ?: "الطلب {$order->number}: ".$order->pricing_label.' = '.$order->amount_label);
    }

    public function statement(Request $request)
    {
        $supplier = $this->supplier($request);
        $f = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return view('finance.statement', [
            'supplier' => $supplier, 'suppliers' => collect(), 'f' => $f, 'mode' => 'supplier',
            'st' => SupplierStatement::build($supplier, $f['from'] ?? null, $f['to'] ?? null),
        ]);
    }

    public function print(Request $request)
    {
        $supplier = $this->supplier($request);
        $f = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return view('finance.statement-print', ['supplier' => $supplier, 'st' => SupplierStatement::build($supplier, $f['from'] ?? null, $f['to'] ?? null)]);
    }

    private function supplier(Request $request)
    {
        $supplier = $request->user()->supplier;
        abort_unless($supplier && $supplier->is_active, 403, 'حساب المطبعة غير مرتبط أو موقوف. راجع إدارة المدرسة.');

        return $supplier;
    }

    private function own(Request $request, PurchaseOrder $order): void
    {
        abort_unless((int) $order->supplier_id === (int) $this->supplier($request)->id, 404);
    }
}
