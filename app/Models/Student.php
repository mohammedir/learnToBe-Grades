<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** الطالب، وهو أيضاً حساب دخول لبوابة الطلاب (برقم الهوية) */
class Student extends Authenticatable
{
    protected $fillable = ['section_id', 'name', 'gender', 'national_id', 'birth_date', 'phone', 'phone2', 'is_active', 'enrollment_status', 'status_reason', 'status_date', 'status_changed_by'];

    /** حالة قيد الطالب. غير «منتظم» = لا يظهر في كشوف الرصد ولا يُحسب في نسب الإكمال، وتبقى درجاته محفوظة */
    public const ENROLLMENT = [
        'active' => 'منتظم',
        'stopped' => 'متوقف عن الدراسة',
        'withdrawn' => 'منسحب',
        'transferred' => 'منقول لمدرسة أخرى',
    ];

    protected $hidden = ['password', 'initial_password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'status_date' => 'date',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'initial_password' => 'encrypted',
            'must_change_password' => 'boolean',
            'password_issued_at' => 'datetime',
            'last_login_at' => 'datetime',
            'suspended_at' => 'datetime',
            'portal_features_off' => 'array',
            'portal_off_at' => 'datetime',
        ];
    }

    public function statusChanger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function getEnrollmentLabelAttribute(): string
    {
        return self::ENROLLMENT[$this->enrollment_status ?? 'active'] ?? $this->enrollment_status;
    }

    /** تغيير حالة القيد: يُخفي الطالب من الكشوف أو يعيده، دون المساس بدرجاته */
    public function setEnrollment(string $status, ?string $reason, ?string $date, ?User $by, ?int $sectionId = null): void
    {
        $active = $status === 'active';
        $this->forceFill([
            'enrollment_status' => $status,
            'is_active' => $active,
            'status_reason' => $reason ? trim($reason) : null,
            'status_date' => $date ?: now()->toDateString(),
            'status_changed_by' => $by?->id,
            'section_id' => $sectionId ?: $this->section_id,
        ])->save();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(StudentNote::class)->latest();
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /** رابط واتساب لجوال ولي الأمر مع رسالة جاهزة */
    public function whatsappUrl(string $text = ''): ?string
    {
        return \App\Support\Phone::whatsapp($this->phone, $text);
    }

    /** كلمة الدخول الظاهرة للإدارة: الموحدة/المعيّنة حتى يغيّرها الطالب، وإلا null */
    public function getLoginPasswordAttribute(): ?string
    {
        if (! $this->national_id) {
            return null;
        }

        return $this->initial_password ?: ($this->password ? null : \App\Support\StudentPassword::default());
    }

    /** مزايا معطّلة لهذا الطالب وحده */
    public function featuresOff(): array
    {
        return array_values(array_intersect(array_keys(\App\Support\PortalLock::FEATURES), $this->portal_features_off ?? []));
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function hasAccount(): bool
    {
        return $this->national_id && $this->password;
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    public function getGenderLabelAttribute(): string
    {
        return $this->gender === 'male' ? 'ذكر' : 'أنثى';
    }
}
