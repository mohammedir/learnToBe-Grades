<?php

namespace App\Support;

use App\Models\Setting;

class Money
{
    public static function currency(): string
    {
        return (string) Setting::get('currency', '₪');
    }

    /** 1250.5 ← "1,250.50 ₪" */
    public static function fmt(?float $amount, bool $symbol = true): string
    {
        $s = number_format((float) $amount, 2);

        return $symbol ? $s.' '.self::currency() : $s;
    }
}
