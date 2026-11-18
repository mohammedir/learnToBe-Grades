<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\StatementExporter;
use App\Services\SupplierStatement;
use Illuminate\Http\Request;

/** كشف حساب المطبعة: الطلبات والدفعات والرصيد الجاري */
class StatementController extends Controller
{
    public function show(Request $request, ?Supplier $supplier = null)
    {
        $suppliers = Supplier::orderByDesc('is_active')->orderBy('name')->get();
        $supplier = $supplier?->exists ? $supplier : $suppliers->first();
        if (! $supplier) {
            return redirect()->route('admin.finance.suppliers.create')->with('warning', 'أضف المطبعة (المورد) أولاً.');
        }
        $f = $this->period($request);

        return view('finance.statement', [
            'supplier' => $supplier, 'suppliers' => $suppliers, 'f' => $f, 'mode' => 'admin',
            'st' => SupplierStatement::build($supplier, $f['from'] ?? null, $f['to'] ?? null),
        ]);
    }

    public function print(Request $request, Supplier $supplier)
    {
        $f = $this->period($request);

        return view('finance.statement-print', ['supplier' => $supplier, 'st' => SupplierStatement::build($supplier, $f['from'] ?? null, $f['to'] ?? null)]);
    }

    public function export(Request $request, Supplier $supplier)
    {
        $f = $this->period($request);

        return StatementExporter::download($supplier, SupplierStatement::build($supplier, $f['from'] ?? null, $f['to'] ?? null));
    }

    private function period(Request $request): array
    {
        return $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']], [], ['from' => 'من تاريخ', 'to' => 'إلى تاريخ']);
    }
}
