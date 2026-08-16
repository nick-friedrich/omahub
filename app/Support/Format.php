<?php

namespace App\Support;

class Format
{
    public static function count(int $value): string
    {
        if ($value >= 1_000_000) {
            return self::compact($value / 1_000_000, 'M');
        }

        if ($value >= 1_000) {
            return self::compact($value / 1_000, 'k');
        }

        return (string) $value;
    }

    private static function compact(float $value, string $suffix): string
    {
        $formatted = number_format($value, 1);

        return rtrim(rtrim($formatted, '0'), '.').$suffix;
    }
}
