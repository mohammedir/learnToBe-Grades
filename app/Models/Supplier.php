<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** مورد المدرسة، مثل المطبعة/المكتبة التي يُحاسَب معها دورياً */
class Supplier extends Model
{
    protected $fillable = ['name', 'contact_name', 'phone', 'default_page_price', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'default_page_price' => 'float'];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** إجمالي الطلبات (غير الملغاة) والمدفوع والرصيد المتبقي على المدرسة */
    public function balance(): array
    {
        $orders = (float) $this->orders()->where('status', '!=', 'cancelled')->sum('amount');
        $open = (float) $this->orders()->whereIn('status', ['pending', 'preparing', 'ready'])->sum('amount');
        $paid = (float) $this->payments()->sum('amount');
        $unpriced = $this->orders()->where('status', '!=', 'cancelled')->whereNull('amount')->count();

        return ['orders' => $orders, 'paid' => $paid, 'balance' => round($orders - $paid, 2), 'open' => $open, 'unpriced' => $unpriced];
    }
}
