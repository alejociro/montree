<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Models\CommissionSchedule;

final class UpdateGlobalCommissionScheduleAction
{
    /**
     * @param  array<int, array{from: string, to: string|null, rate: string}>  $tiers
     */
    public function execute(array $tiers, ?string $maxCharge): CommissionSchedule
    {
        $schedule = CommissionSchedule::global();
        $schedule->update(['tiers' => $tiers, 'max_charge' => $maxCharge]);

        return $schedule->refresh();
    }
}
