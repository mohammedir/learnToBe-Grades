<?php

namespace App\Services;

use App\Models\QuizAttempt;
use App\Models\QuizQuestion;

/**
 * تصحيح الاختبارات:
 * - اختيار من متعدد / صح وخطأ: تلقائي.
 * - رقمي: تلقائي ضمن هامش الخطأ الذي حدده المدرس.
 * - نصي: تلقائي إن طابق إحدى الإجابات المقبولة، وإلا ينتظر تصحيح المدرس.
 */
class QuizGrader
{
    /** درجة السؤال تلقائياً، أو null إن احتاج تصحيحاً يدوياً */
    public static function auto(QuizQuestion $q, ?string $answer): ?float
    {
        $answer = $answer === null ? null : trim($answer);
        $full = (float) $q->points;

        switch ($q->type) {
            case 'mcq':
            case 'tf':
                return $answer !== null && $answer === (string) $q->correct ? $full : 0.0;

            case 'number':
                $n = self::parseNumber($answer);
                $v = $q->meta['value'] ?? null;
                if ($n === null || $v === null) {
                    return 0.0;
                }

                return abs($n - (float) $v) <= (float) ($q->meta['tolerance'] ?? 0) + 1e-9 * max(1, abs((float) $v)) ? $full : 0.0;

            case 'text':
                if ($answer === null || $answer === '') {
                    return 0.0;
                }
                $a = self::normalizeText($answer);
                foreach ($q->meta['accepted'] ?? [] as $acc) {
                    if ($a === self::normalizeText($acc)) {
                        return $full;
                    }
                }

                return null; // يصححه المدرس
        }

        return 0.0;
    }

    /** تصحيح محاولة بعد التسليم */
    public static function grade(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        $quiz = $attempt->quiz->loadMissing('questions');
        $clean = [];
        $awarded = [];
        foreach ($quiz->questions as $q) {
            $a = $answers[$q->id] ?? null;
            $a = is_string($a) || is_numeric($a) ? mb_substr(trim((string) $a), 0, 3000) : null;
            if ($a === '') {
                $a = null;
            }
            if ($a !== null && in_array($q->type, ['mcq', 'tf'], true) && ! array_key_exists($a, $q->choices())) {
                $a = null;
            }
            $clean[$q->id] = $a;
            $awarded[$q->id] = self::auto($q, $a);
        }
        $deadline = $attempt->deadline();
        $attempt->fill([
            'answers' => $clean,
            'submitted_at' => now(),
            'late' => $deadline && now()->gt($deadline->copy()->addMinute()),
        ]);

        return self::finalize($attempt, $awarded);
    }

    /** حفظ درجات الأسئلة وحساب المجموع */
    public static function finalize(QuizAttempt $attempt, array $awarded, ?int $reviewer = null): QuizAttempt
    {
        $quiz = $attempt->quiz->loadMissing('questions');
        $pending = collect($awarded)->contains(fn ($v) => $v === null);
        $attempt->fill([
            'awarded' => $awarded,
            'score' => (float) collect($awarded)->filter(fn ($v) => $v !== null)->sum(),
            'max_score' => $quiz->maxScore(),
            'needs_review' => $pending,
        ]);
        if ($reviewer) {
            $attempt->reviewed_by = $reviewer;
            $attempt->reviewed_at = now();
        }
        $attempt->save();

        return $attempt;
    }

    public static function parseNumber(?string $s): ?float
    {
        if ($s === null) {
            return null;
        }
        $s = strtr(trim($s), ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', ',' => '.', '−' => '-', ' ' => '']);
        if (preg_match('/^([-+]?\d*\.?\d+)(?:[eE×x\*]10\^?([-+]?\d+)|e([-+]?\d+))?$/u', $s, $m)) {
            $exp = ($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? '');

            return (float) $m[1] * ($exp !== '' ? 10 ** (int) $exp : 1);
        }
        if (preg_match('/^([-+]?\d+)\/(\d+)$/', $s, $m) && (int) $m[2] !== 0) {
            return (int) $m[1] / (int) $m[2];
        }

        return null;
    }

    public static function normalizeText(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[أإآ]/u', 'ا', $s);
        $s = str_replace(['ة', 'ى', 'ـ'], ['ه', 'ي', ''], $s);
        $s = preg_replace('/[\x{064B}-\x{0652}]/u', '', $s);
        $s = preg_replace('/^ال(?=\S)/u', '', $s);
        $s = preg_replace('/[^\p{L}\p{N}\s\.\-\+]/u', ' ', $s);

        return trim(preg_replace('/\s+/u', ' ', $s), " .-+");
    }

    public static function formatNumber($n): string
    {
        return $n === null ? '' : rtrim(rtrim(number_format((float) $n, 6, '.', ''), '0'), '.');
    }
}
