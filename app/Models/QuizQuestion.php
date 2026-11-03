<?php

namespace App\Models;

use App\Services\QuizGrader;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    public const TYPES = [
        'mcq' => 'اختيار من متعدد',
        'tf' => 'صح أو خطأ',
        'number' => 'إجابة رقمية',
        'text' => 'إجابة نصية',
    ];

    protected $fillable = ['quiz_id', 'type', 'body', 'image_path', 'options', 'correct', 'meta', 'points', 'sort_order'];

    protected function casts(): array
    {
        return ['options' => 'array', 'meta' => 'array', 'points' => 'float'];
    }

    /** خيارات الأسئلة المغلقة: مفتاح => نص */
    public function choices(): array
    {
        return match ($this->type) {
            'tf' => ['true' => 'صح', 'false' => 'خطأ'],
            'mcq' => collect($this->options ?? [])->mapWithKeys(fn ($t, $i) => [(string) $i => $t])->all(),
            default => [],
        };
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? asset($this->image_path) : null;
    }

    /** الإجابة الصحيحة كنص للعرض */
    public function correctText(): string
    {
        return match ($this->type) {
            'mcq', 'tf' => (string) ($this->choices()[$this->correct] ?? ''),
            'number' => QuizGrader::formatNumber($this->meta['value'] ?? null)
                .(($this->meta['tolerance'] ?? 0) > 0 ? ' ± '.QuizGrader::formatNumber($this->meta['tolerance']) : '')
                .(! empty($this->meta['unit']) ? ' '.$this->meta['unit'] : ''),
            'text' => (string) ($this->meta['model'] ?? ($this->meta['accepted'][0] ?? '')),
            default => '',
        };
    }

    public function isCorrect(?string $answer): bool
    {
        return QuizGrader::auto($this, $answer) === (float) $this->points;
    }
}
