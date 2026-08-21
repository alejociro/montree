<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Tenant\HexToHsl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HexToHslTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function achromaticColors(): array
    {
        return [
            'pure black' => ['#000000', '0 0% 0%'],
            'pure white' => ['#ffffff', '0 0% 100%'],
            'mid gray' => ['#808080', '0 0% 50%'],
        ];
    }

    #[DataProvider('achromaticColors')]
    public function test_it_converts_achromatic_colors_without_dividing_by_zero(string $hex, string $expected): void
    {
        $this->assertSame($expected, HexToHsl::convert($hex));
    }

    public function test_it_converts_chromatic_colors(): void
    {
        $this->assertSame('142 71% 45%', HexToHsl::convert('#22c55e'));
        $this->assertSame('0 100% 50%', HexToHsl::convert('#ff0000'));
        $this->assertSame('240 100% 50%', HexToHsl::convert('#0000ff'));
    }

    public function test_it_accepts_hex_without_leading_hash(): void
    {
        $this->assertSame('0 0% 0%', HexToHsl::convert('000000'));
    }

    public function test_it_returns_null_for_empty_or_invalid_input(): void
    {
        $this->assertNull(HexToHsl::convert(null));
        $this->assertNull(HexToHsl::convert(''));
        $this->assertNull(HexToHsl::convert('#fff'));
        $this->assertNull(HexToHsl::convert('#zzzzzz'));
    }
}
