<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** طلب شراء/توريد من المطبعة */
class PurchaseOrder extends Model
{
    public const STATUSES = [
        'pending' => 'بانتظار المطبعة',
        'preparing' => 'قيد التجهيز',
        'ready' => 'جاهز للاستلام',
        'received' => 'تم الاستلام',
        'cancelled' => 'ملغى',
    ];

    /** ترتيب سير الطلب (للأمام فقط) */
    public const FLOW = ['pending', 'preparing', 'ready', 'received'];

    public const PRICING = [
        'fixed' => 'سعر ثابت',
        'per_page' => 'بالورقة (طباعة)',
        'per_unit' => 'بالقطعة / الكمية',
    ];

    protected $fillable = ['supplier_id', 'title', 'details', 'grade_level_id', 'section_id', 'subject_id', 'recipient_id', 'recipient_name',
        'pricing', 'pages', 'copies', 'unit_price', 'amount', 'priced_by', 'priced_at', 'status', 'needed_by', 'created_by', 'status_at', 'status_by', 'received_at', 'cancel_reason'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'unit_price' => 'float',
            'needed_by' => 'date',
            'status_at' => 'datetime',
            'received_at' => 'datetime',
            'priced_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function gradeLevel(): BelongsTo { return $this->belongsTo(GradeLevel::class); }
    public function section(): BelongsTo { return $this->belongsTo(Section::class); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function statusUser(): BelongsTo { return $this->belongsTo(User::class, 'status_by'); }
    public function pricer(): BelongsTo { return $this->belongsTo(User::class, 'priced_by'); }

    /** هل حُدد مبلغ الطلب؟ (طلبات الطباعة بالورقة قد تنتظر تسعير المطبعة) */
    public function isPriced(): bool
    {
        return $this->amount !== null;
    }

    /** يمكن تحديد/تصحيح الكمية والسعر لطلب الطباعة بالورقة قبل الاستلام */
    public function canBePriced(): bool
    {
        return $this->pricing === 'per_page' && in_array($this->status, ['pending', 'preparing', 'ready'], true);
    }

    public function getAmountLabelAttribute(): string
    {
        return $this->isPriced() ? \App\Support\Money::fmt($this->amount) : 'بانتظار التسعير';
    }

    public function events(): HasMany
    {
        return $this->hasMany(PurchaseOrderEvent::class)->orderBy('id');
    }

    public function getNumberAttribute(): string
    {
        return 'PO-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** لون الحالة في الواجهة */
    public function getStatusClassAttribute(): string
    {
        return ['pending' => 'none', 'preparing' => 'part', 'ready' => 'info', 'received' => 'done', 'cancelled' => 'off'][$this->status] ?? 'none';
    }

    public function getRecipientLabelAttribute(): string
    {
        return $this->recipient?->name ?? ($this->recipient_name ?: '—');
    }

    /** الصف/الشعبة كما تُعرض */
    public function getClassLabelAttribute(): string
    {
        if ($this->section) {
            return $this->section->full_name;
        }

        return $this->gradeLevel?->name ?? '—';
    }

    /** وصف طريقة الحساب، مثل: 12 ورقة × 30 نسخة × 0.2 */
    public function getPricingLabelAttribute(): string
    {
        $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');

        if ($this->pricing === 'per_page' && ! $this->isPriced()) {
            $known = array_filter([$this->pages ? "{$this->pages} ورقة" : null, $this->copies ? "{$this->copies} نسخة" : null, $this->unit_price !== null ? 'سعر الورقة '.$n($this->unit_price) : null]);

            return 'بالورقة – الكمية والسعر تحددها المطبعة'.($known ? ' ('.implode('، ', $known).')' : '');
        }

        return match ($this->pricing) {
            'per_page' => "{$this->pages} ورقة × {$this->copies} نسخة × ".$n($this->unit_price),
            'per_unit' => "{$this->copies} × ".$n($this->unit_price),
            default => 'سعر ثابت',
        };
    }

    /** يحق للمدرسة الإلغاء والتعديل فقط قبل أن تبدأ المطبعة العمل */
    public function isEditable(): bool
    {
        return $this->status === 'pending';
    }

    /** شارة الموعد: متأخر / اليوم / بعد أيام */
    public function dueBadge(): ?array
    {
        if (! $this->needed_by || in_array($this->status, ['received', 'cancelled'], true)) {
            return $this->needed_by ? ['text' => $this->needed_by->format('Y/m/d'), 'tone' => 'plain'] : null;
        }
        $days = (int) now()->startOfDay()->diffInDays($this->needed_by->copy()->startOfDay(), false);

        return match (true) {
            $days < 0 => ['text' => 'متأخر '.abs($days).' '.(abs($days) === 1 ? 'يوم' : (abs($days) <= 10 ? 'أيام' : 'يوماً')), 'tone' => 'late'],
            $days === 0 => ['text' => 'مطلوب اليوم', 'tone' => 'soon'],
            $days === 1 => ['text' => 'مطلوب غداً', 'tone' => 'soon'],
            $days <= 3 => ['text' => 'بعد '.$days.' أيام', 'tone' => 'soon'],
            default => ['text' => 'قبل '.$this->needed_by->format('m/d'), 'tone' => 'plain'],
        };
    }

    /** نص الزر الذي ينقل الطلب للمرحلة التالية */
    public static function actionLabel(string $to, bool $supplier = false): string
    {
        return [
            'preparing' => 'بدء التجهيز',
            'ready' => 'الطلب جاهز',
            'received' => $supplier ? 'تم التسليم للمستلم' : 'تم الاستلام',
        ][$to] ?? (self::STATUSES[$to] ?? $to);
    }

    public function nextStatus(): ?string
    {
        $i = array_search($this->status, self::FLOW, true);

        return $i === false ? null : (self::FLOW[$i + 1] ?? null);
    }

    public static function computeAmount(string $pricing, ?int $pages, ?int $copies, ?float $unitPrice, ?float $fixed): ?float
    {
        // بالورقة: لا يُحسب المبلغ إلا باكتمال الأوراق والنسخ والسعر
        if ($pricing === 'per_page' && (! $pages || ! $copies || $unitPrice === null)) {
            return null;
        }

        return round(match ($pricing) {
            'per_page' => (int) $pages * (int) $copies * (float) $unitPrice,
            'per_unit' => (int) $copies * (float) $unitPrice,
            default => (float) $fixed,
        }, 2);
    }

    public function log(?User $user, string $action, ?string $from = null, ?string $to = null, ?string $note = null): void
    {
        $this->events()->create(['user_id' => $user?->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'note' => $note]);
    }

    /** تغيير الحالة مع التسجيل في السجل */
    public function moveTo(string $status, ?User $user, ?string $note = null): void
    {
        $from = $this->status;
        $this->forceFill([
            'status' => $status,
            'status_at' => now(),
            'status_by' => $user?->id,
            'received_at' => $status === 'received' ? now() : $this->received_at,
            'cancel_reason' => $status === 'cancelled' ? $note : $this->cancel_reason,
        ])->save();
        $this->log($user, $status === 'cancelled' ? 'cancelled' : 'status', $from, $status, $note);
    }
}
