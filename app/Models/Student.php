<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** الطالب، وهو أيضاً حساب دخول لبوابة الطلاب (برقم الهوية) */
class Student extends Authenticatable
{
    protected $fillable = ['section_id', 'name', 'gender', 'national_id', 'birth_date', 'phone', 'phone2', 'is_active'];

    protected $hidden = ['password', 'initial_password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'initial_password' => 'encrypted',
            'must_change_password' => 'boolean',
            'password_issued_at' => 'datetime',
            'last_login_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
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
