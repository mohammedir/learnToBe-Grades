<?php

namespace App\Support;

use App\Models\Setting;

/**
 * تعطيل بوابة الطالب كاملة أو مزايا محددة منها، مع سبب يظهر للطالب.
 * الحالة محفوظة في الإعدادات (portal_lock) بصيغة JSON.
 */
class PortalLock
{
    public const FEATURES = [
        'grades' => 'الدرجات والنتائج',
        'quizzes' => 'الاختبارات الإلكترونية',
        'notes' => 'رسائل المدرسين',
        'teachers' => 'التواصل مع المدرسين (واتساب)',
    ];

    public static function state(): array
    {
        $s = json_decode((string) Setting::get('portal_lock', ''), true) ?: [];

        return [
            'all' => (bool) ($s['all'] ?? false),
            'features' => array_values(array_intersect(array_keys(self::FEATURES), $s['features'] ?? [])),
            'reason' => (string) ($s['reason'] ?? ''),
            'until' => $s['until'] ?? null,
            'since' => $s['since'] ?? null,
        ];
    }

    public static function save(bool $all, array $features, string $reason, ?string $until): void
    {
        Setting::put('portal_lock', json_encode([
            'all' => $all,
            'features' => $all ? [] : array_values(array_intersect(array_keys(self::FEATURES), $features)),
            'reason' => trim($reason),
            'until' => $until ?: null,
            'since' => now()->format('Y-m-d H:i'),
        ], JSON_UNESCAPED_UNICODE));
    }

    public static function clear(): void
    {
        Setting::put('portal_lock', '');
    }

    public static function closed(): bool
    {
        return self::state()['all'];
    }

    public static function off(string $feature): bool
    {
        $s = self::state();

        return $s['all'] || in_array($feature, $s['features'], true);
    }

    /** أسماء المزايا المعطّلة حالياً */
    public static function offLabels(): array
    {
        $s = self::state();

        return array_map(fn ($k) => self::FEATURES[$k], $s['all'] ? array_keys(self::FEATURES) : $s['features']);
    }

    /**
     * الحالة الفعلية لطالب: ما عطّلته الإدارة للجميع + ما عُطّل له وحده.
     * notes: سبب لكل مصدر تعطيل (للجميع / لك خاصة).
     */
    public static function forStudent($student): array
    {
        $g = self::state();
        $own = $student->featuresOff();
        $features = $g['all'] ? array_keys(self::FEATURES) : array_values(array_unique(array_merge($g['features'], $own)));
        $notes = [];
        if ($g['all'] || $g['features']) {
            $notes[] = ['labels' => array_map(fn ($k) => self::FEATURES[$k], $g['all'] ? array_keys(self::FEATURES) : $g['features']), 'reason' => $g['reason'], 'own' => false];
        }
        if ($own = array_diff($own, $g['all'] ? array_keys(self::FEATURES) : $g['features'])) {
            $notes[] = ['labels' => array_map(fn ($k) => self::FEATURES[$k], array_values($own)), 'reason' => (string) $student->portal_off_reason, 'own' => true];
        }

        return ['all' => $g['all'], 'features' => $features, 'notes' => $notes, 'global' => $g];
    }

    public static function offFor($student, string $feature): bool
    {
        return in_array($feature, self::forStudent($student)['features'], true);
    }

    public static function active(): bool
    {
        $s = self::state();

        return $s['all'] || $s['features'];
    }
}
