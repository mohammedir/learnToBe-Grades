<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentNote extends Model
{
    public const TYPES = [
        'praise' => ['إشادة', 'تقدير لتميّز أو تحسّن'],
        'note' => ['ملاحظة', 'توجيه أو معلومة'],
        'alert' => ['تنبيه', 'أمر يحتاج متابعة'],
    ];

    protected $fillable = ['student_id', 'subject_id', 'author_id', 'type', 'body'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type][0] ?? 'ملاحظة';
    }
}
