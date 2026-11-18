<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Support\Carbon;

/**
 * كشف حساب المورد: رصيد افتتاحي + حركات (طلبات = مدين على المدرسة، دفعات = دائن) + رصيد جارٍ.
 * الطلبات الملغاة لا تدخل الحساب. تاريخ الطلب = تاريخ إنشائه.
 */
class SupplierStatement
{
    public static function build(Supplier $supplier, ?string $from = null, ?string $to = null): array
    {
        $from = $from ? Carbon::parse($from)->startOfDay() : null;
        $to = $to ? Carbon::parse($to)->endOfDay() : null;

        $orders = PurchaseOrder::with(['section.gradeLevel', 'gradeLevel', 'recipient', 'creator'])
            ->where('supplier_id', $supplier->id)->where('status', '!=', 'cancelled')->get();
        $payments = SupplierPayment::with('recorder')->where('supplier_id', $supplier->id)->get();

        $lines = collect();
        foreach ($orders as $o) {
            $lines->push(['date' => $o->created_at->copy(), 'type' => 'order', 'ref' => $o->number, 'desc' => $o->title.($o->class_label !== '—' ? ' – '.$o->class_label : ''),
                'detail' => $o->pricing_label.' · المستلم: '.$o->recipient_label.' · '.$o->status_label.($o->isPriced() ? '' : ' · لم يُسعَّر بعد'), 'by' => $o->creator?->name, 'debit' => (float) $o->amount, 'credit' => 0.0, 'model' => $o]);
        }
        foreach ($payments as $p) {
            $lines->push(['date' => $p->paid_at->copy()->setTimeFrom($p->created_at), 'type' => 'payment', 'ref' => 'دفعة #'.$p->id, 'desc' => 'دفعة '.$p->method_label.($p->reference ? ' – '.$p->reference : ''),
                'detail' => (string) $p->note, 'by' => $p->recorder?->name, 'debit' => 0.0, 'credit' => $p->amount, 'model' => $p]);
        }
        $lines = $lines->sortBy(fn ($l) => $l['date']->format('Y-m-d H:i:s').($l['type'] === 'order' ? '0' : '1'))->values();

        $opening = 0.0;
        $rows = [];
        $balance = 0.0;
        $tDebit = $tCredit = 0.0;
        foreach ($lines as $l) {
            if ($from && $l['date']->lt($from)) {
                $opening += $l['debit'] - $l['credit'];
                continue;
            }
            if ($to && $l['date']->gt($to)) {
                continue;
            }
            $rows[] = $l;
        }
        $balance = $opening;
        foreach ($rows as &$r) {
            $balance = round($balance + $r['debit'] - $r['credit'], 2);
            $r['balance'] = $balance;
            $tDebit += $r['debit'];
            $tCredit += $r['credit'];
        }
        unset($r);

        return [
            'opening' => round($opening, 2),
            'rows' => $rows,
            'debit' => round($tDebit, 2),
            'credit' => round($tCredit, 2),
            'closing' => round($balance, 2),
            'from' => $from,
            'to' => $to,
        ];
    }
}
