<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    protected $fillable = ['quiz_id', 'student_id', 'started_at', 'submitted_at', 'answers', 'awarded', 'score', 'max_score', 'late', 'needs_review', 'reviewed_by', 'reviewed_at', 'leave_count', 'leave_seconds', 'copy_attempts', 'screenshot_attempts', 'integrity_log'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'submitted_at' => 'datetime', 'answers' => 'array', 'awarded' => 'array', 'score' => 'float', 'max_score' => 'float', 'late' => 'boolean', 'needs_review' => 'boolean', 'reviewed_at' => 'datetime', 'integrity_log' => 'array', 'leave_count' => 'integer', 'leave_seconds' => 'integer', 'copy_attempts' => 'integer', 'screenshot_attempts' => 'integer'];
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

    public const INTEGRITY_EVENTS = [
        'leave' => 'خرج من صفحة الاختبار',
        'close' => 'أغلق الصفحة أو حدّثها',
        'copy' => 'حاول النسخ/اللصق',
        'screenshot' => 'حاول تصوير الشاشة',
        'print' => 'حاول الطباعة',
    ];

    /** تسجيل حدث مراقبة (يُحفظ آخر 100 حدث) */
    public function recordIntegrity(string $type, int $seconds = 0): void
    {
        $seconds = max(0, min($seconds, 6 * 3600));
        if (in_array($type, ['leave', 'close'], true)) {
            $this->leave_count++;
            $this->leave_seconds += $seconds;
        } elseif ($type === 'copy') {
            $this->copy_attempts++;
        } elseif (in_array($type, ['screenshot', 'print'], true)) {
            $this->screenshot_attempts++;
        }
        $log = $this->integrity_log ?? [];
        $log[] = array_filter(['t' => $type, 'at' => now()->format('H:i:s'), 's' => $seconds ?: null]);
        $this->integrity_log = array_slice($log, -100);
        $this->save();
    }

    /** وصف قصير لما رُصد أثناء الحل */
    public function integritySummary(): array
    {
        return array_filter([
            $this->leave_count ? 'خرج '.$this->leave_count.' مرة'.($this->leave_seconds ? ' ('.self::duration($this->leave_seconds).')' : '') : null,
            $this->copy_attempts ? 'نسخ/لصق '.$this->copy_attempts : null,
            $this->screenshot_attempts ? 'تصوير/طباعة '.$this->screenshot_attempts : null,
        ]);
    }

    public static function duration(int $s): string
    {
        return $s < 60 ? $s.' ث' : intdiv($s, 60).' د'.($s % 60 ? ' '.($s % 60).' ث' : '');
    }
}
