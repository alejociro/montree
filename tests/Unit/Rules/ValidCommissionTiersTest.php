<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\ValidCommissionTiers;
use Tests\TestCase;

class ValidCommissionTiersTest extends TestCase
{
    private function failures(array $tiers): array
    {
        $failures = [];
        (new ValidCommissionTiers)->validate('tiers', $tiers, function (string $message) use (&$failures): void {
            $failures[] = $message;
        });

        return $failures;
    }

    public function test_a_contiguous_schedule_passes(): void
    {
        $tiers = [
            ['from' => '0', 'to' => '500000', 'rate' => '9'],
            ['from' => '500000', 'to' => null, 'rate' => '5'],
        ];

        $this->assertSame([], $this->failures($tiers));
    }

    public function test_a_gap_between_tiers_is_rejected(): void
    {
        $tiers = [
            ['from' => '0', 'to' => '100', 'rate' => '9'],
            ['from' => '150', 'to' => null, 'rate' => '5'],
        ];

        $this->assertNotSame([], $this->failures($tiers));
    }

    public function test_an_overlap_between_tiers_is_rejected(): void
    {
        $tiers = [
            ['from' => '0', 'to' => '100', 'rate' => '9'],
            ['from' => '50', 'to' => null, 'rate' => '5'],
        ];

        $this->assertNotSame([], $this->failures($tiers));
    }

    public function test_the_first_tier_must_start_at_zero(): void
    {
        $tiers = [
            ['from' => '10', 'to' => null, 'rate' => '9'],
        ];

        $this->assertNotSame([], $this->failures($tiers));
    }

    public function test_the_last_tier_must_stay_open(): void
    {
        $tiers = [
            ['from' => '0', 'to' => '100', 'rate' => '9'],
        ];

        $this->assertNotSame([], $this->failures($tiers));
    }

    public function test_a_middle_tier_cannot_be_open(): void
    {
        $tiers = [
            ['from' => '0', 'to' => null, 'rate' => '9'],
            ['from' => '100', 'to' => null, 'rate' => '5'],
        ];

        $this->assertNotSame([], $this->failures($tiers));
    }

    public function test_a_rate_above_one_hundred_is_rejected(): void
    {
        $tiers = [
            ['from' => '0', 'to' => null, 'rate' => '120'],
        ];

        $this->assertNotSame([], $this->failures($tiers));
    }

    public function test_more_than_ten_tiers_is_rejected(): void
    {
        $tiers = [];

        for ($i = 0; $i < 11; $i++) {
            $tiers[] = ['from' => (string) ($i * 100), 'to' => $i === 10 ? null : (string) (($i + 1) * 100), 'rate' => '5'];
        }

        $this->assertNotSame([], $this->failures($tiers));
    }

    public function test_an_empty_list_is_rejected(): void
    {
        $this->assertNotSame([], $this->failures([]));
    }
}
