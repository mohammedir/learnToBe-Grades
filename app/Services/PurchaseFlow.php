<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * قواعد تغيير حالة طلب الشراء: للأمام فقط، والإلغاء فقط وهو «بانتظار المطبعة».
 * كل عملية تقفل صف الطلب حتى لا تتعارض مع تغيير متزامن من المطبعة.
 */
class PurchaseFlow
{
    /** الحالات التي يمكن نقل الطلب إليها من حالته الحالية */
    public static function allowedTargets(PurchaseOrder $order): array
    {
        $i = array_search($order->status, PurchaseOrder::FLOW, true);

        return $i === false ? [] : array_slice(PurchaseOrder::FLOW, $i + 1);
    }

    public static function advance(PurchaseOrder $order, string $to, User $user, ?string $note = null): ?string
    {
        return self::locked($order, function (PurchaseOrder $o) use ($to, $user, $note) {
            if (! in_array($to, self::allowedTargets($o), true)) {
                return 'لا يمكن نقل الطلب من «'.$o->status_label.'» إلى «'.(PurchaseOrder::STATUSES[$to] ?? $to).'».';
            }
            if (! $o->isPriced() && in_array($to, ['ready', 'received'], true)) {
                return 'حدّد عدد الأوراق والنسخ وسعر الورقة أولاً، ثم غيّر الحالة إلى «'.PurchaseOrder::STATUSES[$to].'».';
            }
            $o->moveTo($to, $user, $note);

            return null;
        });
    }

    public static function cancel(PurchaseOrder $order, User $user, string $reason): ?string
    {
        return self::locked($order, function (PurchaseOrder $o) use ($user, $reason) {
            if (! $o->isEditable()) {
                return 'لا يمكن إلغاء الطلب لأن المطبعة بدأت العمل عليه (الحالة: '.$o->status_label.').';
            }
            $o->moveTo('cancelled', $user, $reason);

            return null;
        });
    }

    /** تعديل بيانات الطلب مسموح فقط وهو «بانتظار المطبعة» */
    public static function update(PurchaseOrder $order, array $data, User $user): ?string
    {
        return self::locked($order, function (PurchaseOrder $o) use ($data, $user) {
            if (! $o->isEditable()) {
                return 'لا يمكن تعديل الطلب لأن المطبعة بدأت العمل عليه (الحالة: '.$o->status_label.').';
            }
            $o->update($data);
            $o->log($user, 'edited');

            return null;
        });
    }

    /** تحديد أو تصحيح الكمية والسعر لطلب طباعة بالورقة (المطبعة أو الإدارة) مع التسجيل */
    public static function price(PurchaseOrder $order, int $pages, int $copies, float $unitPrice, User $user): ?string
    {
        return self::locked($order, function (PurchaseOrder $o) use ($pages, $copies, $unitPrice, $user) {
            if (! $o->canBePriced()) {
                return 'لا يمكن تغيير الكمية والسعر لهذا الطلب (الحالة: '.$o->status_label.').';
            }
            $before = $o->isPriced() ? $o->pricing_label.' = '.\App\Support\Money::fmt($o->amount) : null;
            $o->forceFill([
                'pages' => $pages, 'copies' => $copies, 'unit_price' => $unitPrice,
                'amount' => PurchaseOrder::computeAmount('per_page', $pages, $copies, $unitPrice, null),
                'priced_by' => $user->id, 'priced_at' => now(),
            ])->save();
            $o->log($user, 'priced', null, null, $o->pricing_label.' = '.\App\Support\Money::fmt($o->amount).($before ? ' (كان: '.$before.')' : ''));

            return null;
        });
    }

    private static function locked(PurchaseOrder $order, \Closure $fn): ?string
    {
        $error = DB::transaction(fn () => $fn(PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail()));
        $order->refresh();

        return $error;
    }
}
