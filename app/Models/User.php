<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'job_title', 'username', 'phone', 'show_phone_to_students', 'role', 'permissions', 'is_active', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'permissions' => 'array',
            'show_phone_to_students' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /** يستطيع دخول لوحة الإدارة (مدير عام أو موظف) */
    public function isBackOffice(): bool
    {
        return in_array($this->role, ['admin', 'staff'], true);
    }

    public function permissionLevel(string $module): string
    {
        if ($this->isAdmin()) {
            return 'edit';
        }
        if (! $this->isStaff()) {
            return 'none';
        }

        return $this->permissions[$module] ?? 'none';
    }

    /** hasPermission('teachers') = يشاهد، hasPermission('teachers', 'edit') = يعدّل */
    public function hasPermission(string $module, string $level = 'view'): bool
    {
        return Permissions::rank($this->permissionLevel($module)) >= Permissions::rank($level);
    }

    /** الصفحة التي يفتح عليها المستخدم بعد الدخول */
    public function homeUrl(): string
    {
        if ($this->isTeacher()) {
            return route('teacher.home');
        }
        foreach (Permissions::MODULES as $module => $info) {
            if ($this->hasPermission($module)) {
                return route($info[3]);
            }
        }

        return route('no-access');
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'مدير عام',
            'staff' => $this->job_title ?: 'موظف',
            default => 'مدرس',
        };
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class, 'teacher_id');
    }

    public function scopeTeachers($query)
    {
        return $query->where('role', 'teacher');
    }
}
