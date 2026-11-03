<?php

namespace App\Support;

/** أرقام جوال فلسطين: تُحفظ بصيغة 05xxxxxxxx، وتُحوَّل لواتساب بمفتاح 970 */
class Phone
{
    public static function normalize(?string $value): ?string
    {
        $d = preg_replace('/\D/', '', strtr((string) $value, array_combine(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], range(0, 9))));
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        }
        if (str_starts_with($d, '970') || str_starts_with($d, '972')) {
            $d = substr($d, 3);
        }
        if (strlen($d) === 9 && $d[0] === '5') {
            $d = '0'.$d;
        }

        return strlen($d) === 10 && str_starts_with($d, '05') ? $d : null;
    }

    /** خلية فيها رقم أو أكثر مثل «0599.../0567...» => [أرقام صحيحة] */
    public static function parseMany(mixed $value): array
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return [];
        }
        $found = [];
        foreach (preg_split('/[\/\\\\,;|]+/u', (string) $value) ?: [] as $part) {
            // الجزء كاملاً (مثل «+970 599 123 456»)، وإلا كل كلمة فيه وحدها
            $one = self::normalize($part);
            $found = array_merge($found, $one ? [$one] : array_map([self::class, 'normalize'], preg_split('/\s+/u', trim($part)) ?: []));
        }

        return array_values(array_unique(array_filter($found)));
    }

    public static function whatsapp(?string $phone, string $text = ''): ?string
    {
        $p = self::normalize($phone);

        return $p ? 'https://wa.me/970'.substr($p, 1).($text !== '' ? '?text='.rawurlencode($text) : '') : null;
    }
}
