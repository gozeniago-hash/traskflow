<?php

namespace Tests\Unit\Support;

use App\Support\Color;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/** Darkening colours until white text on them is readable. */
class ColorTest extends TestCase
{
    public function test_a_dark_colour_is_kept(): void
    {
        $this->assertSame('#1f2937', Color::forWhiteText('#1f2937'));
    }

    public function test_a_light_colour_is_darkened_until_white_text_reaches_4_5_to_1(): void
    {
        $darkened = Color::forWhiteText('#ffd166');

        $this->assertNotSame('#ffd166', $darkened);
        $this->assertGreaterThanOrEqual(4.5, $this->contrastWithWhite($darkened));
    }

    #[TestWith([null])]
    #[TestWith(['red'])]
    #[TestWith(['#fff'])]
    #[TestWith(['#zzzzzz'])]
    public function test_anything_that_is_not_a_six_digit_hex_colour_falls_back_to_grey(?string $input): void
    {
        $this->assertSame('#5f6170', Color::forWhiteText($input));
    }

    /** WCAG contrast ratio of white text on the colour, worked out independently of Color. */
    private function contrastWithWhite(string $hex): float
    {
        $channel = function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $l = 0.2126 * $channel(substr($hex, 1, 2)) + 0.7152 * $channel(substr($hex, 3, 2)) + 0.0722 * $channel(substr($hex, 5, 2));

        return 1.05 / ($l + 0.05);
    }
}
