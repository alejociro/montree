<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportRevenueReportAction
{
    /**
     * @return array{from: string, to: string, group_by: string, rows: array<int, array<string, mixed>>, totals: array<string, mixed>}|StreamedResponse|Response
     */
    public function handle(Carbon $from, Carbon $to, string $groupBy, string $format): array|StreamedResponse|Response
    {
        $rows = $this->buildRows($from, $to, $groupBy);
        $totals = $this->buildTotals($from, $to);

        if ($format === 'csv') {
            return $this->streamCsv($from, $to, $rows);
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'group_by' => $groupBy,
            'rows' => $rows->all(),
            'totals' => $totals,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildRows(Carbon $from, Carbon $to, string $groupBy): Collection
    {
        $payments = Payment::query()
            ->where('status', PaymentStatus::Completed->value)
            ->whereBetween('processed_at', [$from, $to])
            ->get(['amount', 'processed_at']);

        $refunds = Payment::query()
            ->whereIn('status', [PaymentStatus::Refunded->value, PaymentStatus::PartiallyRefunded->value])
            ->whereBetween('refunded_at', [$from, $to])
            ->get(['refunded_amount', 'refunded_at']);

        $bookings = Booking::query()
            ->whereBetween('created_at', [$from, $to])
            ->get(['created_at']);

        $grossByBucket = $this->aggregateByBucket(
            $payments,
            'processed_at',
            $groupBy,
            fn ($payment): float => (float) $payment->amount,
        );

        $refundsByBucket = $this->aggregateByBucket(
            $refunds,
            'refunded_at',
            $groupBy,
            fn ($payment): float => (float) $payment->refunded_amount,
        );

        $bookingsByBucket = $this->aggregateByBucket(
            $bookings,
            'created_at',
            $groupBy,
            fn (): float => 1.0,
        );

        $buckets = collect($grossByBucket)
            ->keys()
            ->merge(array_keys($refundsByBucket))
            ->merge(array_keys($bookingsByBucket))
            ->unique()
            ->sort()
            ->values();

        return $buckets->map(function (string $bucket) use ($grossByBucket, $refundsByBucket, $bookingsByBucket): array {
            $gross = (float) ($grossByBucket[$bucket] ?? 0);
            $refunded = (float) ($refundsByBucket[$bucket] ?? 0);
            $bookingsCount = (int) ($bookingsByBucket[$bucket] ?? 0);

            return [
                'bucket' => $bucket,
                'gross' => number_format($gross, 2, '.', ''),
                'net' => number_format($gross - $refunded, 2, '.', ''),
                'bookings_count' => $bookingsCount,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTotals(Carbon $from, Carbon $to): array
    {
        $gross = (float) Payment::query()
            ->where('status', PaymentStatus::Completed->value)
            ->whereBetween('processed_at', [$from, $to])
            ->sum('amount');

        $refunded = (float) Payment::query()
            ->whereIn('status', [PaymentStatus::Refunded->value, PaymentStatus::PartiallyRefunded->value])
            ->whereBetween('refunded_at', [$from, $to])
            ->sum('refunded_amount');

        $bookingsCount = (int) Booking::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return [
            'gross' => number_format($gross, 2, '.', ''),
            'net' => number_format($gross - $refunded, 2, '.', ''),
            'bookings_count' => $bookingsCount,
        ];
    }

    /**
     * @param  Collection<int, Model>  $records
     * @return array<string, float>
     */
    private function aggregateByBucket(Collection $records, string $dateColumn, string $groupBy, \Closure $valueResolver): array
    {
        $buckets = [];

        foreach ($records as $record) {
            $value = $record->getAttribute($dateColumn);

            if ($value === null) {
                continue;
            }

            $carbon = $value instanceof Carbon ? $value : Carbon::parse((string) $value);
            $bucket = $this->bucketLabel($carbon, $groupBy);
            $buckets[$bucket] = ($buckets[$bucket] ?? 0) + $valueResolver($record);
        }

        return $buckets;
    }

    private function bucketLabel(Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => $date->format('o-\WW'),
            'month' => $date->format('Y-m'),
            default => $date->format('Y-m-d'),
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function streamCsv(Carbon $from, Carbon $to, Collection $rows): StreamedResponse
    {
        $filename = sprintf('revenue-%s-to-%s.csv', $from->toDateString(), $to->toDateString());

        return new StreamedResponse(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['bucket', 'gross', 'net', 'bookings_count']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['bucket'],
                    $row['gross'],
                    $row['net'],
                    $row['bookings_count'],
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
}
