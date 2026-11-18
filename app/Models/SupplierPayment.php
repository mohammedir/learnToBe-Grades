<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** دفعة من المدرسة للمورد */
class SupplierPayment extends Model
{
    public const METHODS = ['cash' => 'نقداً', 'transfer' => 'تحويل بنكي', 'wallet' => 'محفظة إلكترونية', 'cheque' => 'شيك', 'other' => 'أخرى'];

    protected $fillable = ['supplier_id', 'amount', 'paid_at', 'method', 'reference', 'note', 'recorded_by'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'paid_at' => 'date'];
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }

    public function getMethodLabelAttribute(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }
}
