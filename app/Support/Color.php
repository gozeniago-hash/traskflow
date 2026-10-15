<?php

namespace App\Support;

class Color
{
    /** Darkens a colour just enough for white text on it to meet WCAG AA (4.5:1). */
    public static function forWhiteText(?string $hex, float $target = 4.5): string
    {
        $hex = ltrim((string) $hex, '#');
        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return '#5f6170';
        }
        [$r, $g, $b] = array_map(fn ($i) => hexdec(substr($hex, $i, 2)), [0, 2, 4]);

        for ($i = 0; $i < 30 && static::contrastWithWhite($r, $g, $b) < $target; $i++) {
            [$r, $g, $b] = [(int) round($r * 0.94), (int) round($g * 0.94), (int) round($b * 0.94)];
        }

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    protected static function contrastWithWhite(int $r, int $g, int $b): float
    {
        $lin = fn ($c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $l = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

        return 1.05 / ($l + 0.05);
    }
}
