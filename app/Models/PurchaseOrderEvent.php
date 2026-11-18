<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['purchase_order_id', 'user_id', 'action', 'from_status', 'to_status', 'note'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDescriptionAttribute(): string
    {
        $s = PurchaseOrder::STATUSES;

        return match ($this->action) {
            'created' => 'أنشأ الطلب',
            'edited' => 'عدّل الطلب',
            'cancelled' => 'ألغى الطلب',
            'priced' => 'حدّد الكمية والسعر',
            default => 'غيّر الحالة: '.($s[$this->from_status] ?? $this->from_status).' ← '.($s[$this->to_status] ?? $this->to_status),
        };
    }
}
