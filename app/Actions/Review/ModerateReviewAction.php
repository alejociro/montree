<?php

declare(strict_types=1);

namespace App\Actions\Review;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ModerateReviewAction
{
    public function approve(Review $review, User $admin): Review
    {
        return DB::transaction(function () use ($review) {
            $review->update([
                'status' => ReviewStatus::Approved,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);
            $this->recalculateTourRating($review->tour_id);

            return $review->fresh();
        });
    }

    public function reject(Review $review, User $admin, ?string $reason = null): Review
    {
        return DB::transaction(function () use ($review, $reason) {
            $review->update([
                'status' => ReviewStatus::Rejected,
                'approved_at' => null,
                'rejection_reason' => $reason,
            ]);
            $this->recalculateTourRating($review->tour_id);

            return $review->fresh();
        });
    }

    /**
     * Devuelve una reseña ya moderada a la bandeja de pendientes.
     *
     * WHY: aprobar y rechazar eran caminos de una sola vía, así que un clic
     * equivocado dejaba una reseña publicada —o escondida— para siempre. Al
     * volver a revisión se limpia el motivo de rechazo y se recalcula la nota
     * del tour: mientras esté pendiente no cuenta para el promedio público.
     */
    public function reopen(Review $review): Review
    {
        return DB::transaction(function () use ($review) {
            $review->update([
                'status' => ReviewStatus::Pending,
                'approved_at' => null,
                'rejection_reason' => null,
            ]);
            $this->recalculateTourRating($review->tour_id);

            return $review->fresh();
        });
    }

    private function recalculateTourRating(int $tourId): void
    {
        $stats = DB::table('reviews')
            ->where('tour_id', $tourId)
            ->where('status', ReviewStatus::Approved->value)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as total, COALESCE(AVG(rating), 0) as avg_rating')
            ->first();

        Tour::query()->where('id', $tourId)->update([
            'rating_count' => (int) ($stats->total ?? 0),
            'rating_average' => number_format((float) ($stats->avg_rating ?? 0), 2, '.', ''),
        ]);
    }
}
