<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    protected $fillable = ['teaching_assignment_id', 'created_by', 'title', 'instructions', 'opens_at', 'closes_at', 'duration_minutes', 'show_answers', 'is_published', 'results_published'];

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime', 'show_answers' => 'boolean', 'is_published' => 'boolean', 'results_published' => 'boolean'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function hasManualQuestions(): bool
    {
        return $this->questions->contains('type', 'text');
    }

    public function maxScore(): float
    {
        return (float) $this->questions->sum('points');
    }

    /** حالة الاختبار للطالب الآن: upcoming | open | closed | draft */
    public function state(): string
    {
        if (! $this->is_published) {
            return 'draft';
        }
        if ($this->opens_at && now()->lt($this->opens_at)) {
            return 'upcoming';
        }
        if ($this->closes_at && now()->gt($this->closes_at)) {
            return 'closed';
        }

        return 'open';
    }

    public function stateLabel(): string
    {
        return ['draft' => 'مسودة', 'upcoming' => 'لم يبدأ بعد', 'open' => 'متاح الآن', 'closed' => 'انتهى'][$this->state()];
    }
}
