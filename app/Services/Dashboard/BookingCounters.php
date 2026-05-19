<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Data\Dashboard\BookingCounts;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Support\Carbon;

final class BookingCounters
{
    public function between(Carbon $start, Carbon $end, Carbon $previousStart, Carbon $previousEnd): BookingCounts
    {
        $rows = Booking::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $total = array_sum($rows);
        $previousTotal = (int) Booking::query()
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();

        return new BookingCounts(
            total: $total,
            confirmed: (int) ($rows[BookingStatus::Confirmed->value] ?? 0),
            pendingPayment: (int) ($rows[BookingStatus::PendingPayment->value] ?? 0),
            cancelled: (int) ($rows[BookingStatus::Cancelled->value] ?? 0),
            previousTotal: $previousTotal,
            growthPct: $this->growthPct($total, $previousTotal),
        );
    }

    private function growthPct(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
