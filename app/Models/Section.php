<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = ['grade_level_id', 'name', 'gender', 'period'];

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class)->where('is_active', true);
    }

    /** الطلاب مرتبين: الذكور ثم الإناث، أبجدياً */
    public function orderedStudents()
    {
        return $this->students()->orderByRaw("CASE WHEN gender = 'male' THEN 0 ELSE 1 END")->orderBy('name')->get();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    /** الاسم الكامل للعرض، مثل: الصف السابع – شعبة 1 (صباحي) */
    public function getFullNameAttribute(): string
    {
        $name = $this->gender
            ? $this->gradeLevel->name.' – '.$this->name
            : $this->gradeLevel->name.' – شعبة '.$this->name;

        return $this->period ? $name.' ('.$this->period.')' : $name;
    }

    /** اسم الشعبة داخل صفها، مثل: شعبة 1 / إناث (صباحي) */
    public function getShortNameAttribute(): string
    {
        $name = $this->gender ? $this->name : 'شعبة '.$this->name;

        return $this->period ? $name.' ('.$this->period.')' : $name;
    }

    public function getGenderLabelAttribute(): string
    {
        return match ($this->gender) {
            'male' => 'ذكور',
            'female' => 'إناث',
            default => 'مختلطة',
        };
    }

    /** هل يقبل هذا القسم طالباً بهذا الجنس؟ */
    public function accepts(string $gender): bool
    {
        return $this->gender === null || $this->gender === $gender;
    }

    /** الشعب مرتبة حسب ترتيب الصفوف (الإناث قبل الذكور) */
    public function scopeOrdered($query)
    {
        return $query->join('grade_levels', 'grade_levels.id', '=', 'sections.grade_level_id')
            ->orderBy('grade_levels.sort_order')->orderByRaw("CASE sections.gender WHEN 'female' THEN 0 WHEN 'male' THEN 1 ELSE 2 END")->orderBy('sections.name')
            ->select('sections.*');
    }
}
