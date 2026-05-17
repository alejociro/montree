<?php

declare(strict_types=1);

namespace App\Services\Tenant;

final class HexToHsl
{
    /**
     * Convert a hex color (e.g. `#16a34a`) into the shadcn HSL string format
     * (e.g. `"142 65% 38%"`). Returns null when the input is empty or invalid.
     */
    public static function convert(?string $hex): ?string
    {
        if ($hex === null || $hex === '') {
            return null;
        }

        $clean = ltrim($hex, '#');

        if (! preg_match('/^[0-9A-Fa-f]{6}$/', $clean)) {
            return null;
        }

        $red = hexdec(substr($clean, 0, 2)) / 255;
        $green = hexdec(substr($clean, 2, 2)) / 255;
        $blue = hexdec(substr($clean, 4, 2)) / 255;

        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $delta = $max - $min;

        $lightness = ($max + $min) / 2;

        if ($delta === 0.0) {
            $hue = 0.0;
            $saturation = 0.0;
        } else {
            $saturation = $lightness > 0.5
                ? $delta / (2 - $max - $min)
                : $delta / ($max + $min);

            $hue = match (true) {
                $max === $red => (($green - $blue) / $delta) + ($green < $blue ? 6 : 0),
                $max === $green => (($blue - $red) / $delta) + 2,
                default => (($red - $green) / $delta) + 4,
            };

            $hue *= 60;
        }

        return sprintf(
            '%d %d%% %d%%',
            (int) round($hue),
            (int) round($saturation * 100),
            (int) round($lightness * 100),
        );
    }
}
