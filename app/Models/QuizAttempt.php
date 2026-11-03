<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    protected $fillable = ['quiz_id', 'student_id', 'started_at', 'submitted_at', 'answers', 'awarded', 'score', 'max_score', 'late', 'needs_review', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'submitted_at' => 'datetime', 'answers' => 'array', 'awarded' => 'array', 'score' => 'float', 'max_score' => 'float', 'late' => 'boolean', 'needs_review' => 'boolean', 'reviewed_at' => 'datetime'];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** آخر وقت مسموح للتسليم لهذه المحاولة */
    public function deadline(): ?\Carbon\Carbon
    {
        $byDuration = $this->quiz->duration_minutes ? $this->started_at->copy()->addMinutes($this->quiz->duration_minutes) : null;
        $close = $this->quiz->closes_at;
        if ($byDuration && $close) {
            return $byDuration->min($close);
        }

        return $byDuration ?? $close;
    }
}
