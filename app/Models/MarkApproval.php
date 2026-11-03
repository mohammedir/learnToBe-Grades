<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarkApproval extends Model
{
    public $timestamps = false;

    protected $fillable = ['section_id', 'subject_id', 'assessment_component_id', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function isLocked(int $sectionId, int $subjectId, int $componentId): bool
    {
        return static::where(['section_id' => $sectionId, 'subject_id' => $subjectId, 'assessment_component_id' => $componentId])->exists();
    }

    /** العناصر المعتمدة لكشف واحد: [component_id => MarkApproval] */
    public static function forSheet(int $sectionId, int $subjectId): array
    {
        return static::with('approver')->where(['section_id' => $sectionId, 'subject_id' => $subjectId])
            ->get()->keyBy('assessment_component_id')->all();
    }
}
