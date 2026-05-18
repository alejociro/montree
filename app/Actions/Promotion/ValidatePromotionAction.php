<?php

declare(strict_types=1);

namespace App\Actions\Promotion;

use App\Data\PromotionValidationResult;
use App\Exceptions\PromotionInvalidException;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\TourDate;
use App\Models\User;
use App\Services\Promotion\PromotionDiscountCalculator;
use Illuminate\Support\Str;

final class ValidatePromotionAction
{
    public function __construct(private PromotionDiscountCalculator $calculator) {}

    public function handle(string $code, TourDate $tourDate, string $subtotal, User $user): PromotionValidationResult
    {
        $promotion = Promotion::query()
            ->where('code', Str::upper(trim($code)))
            ->first();

        if ($promotion === null) {
            throw PromotionInvalidException::notFound();
        }

        $this->assertActive($promotion);
        $this->assertWithinDates($promotion);
        $this->assertHasRemainingUses($promotion);
        $this->assertMinAmountMet($promotion, $subtotal);
        $this->assertTourApplicable($promotion, $tourDate);
        $this->assertUserLimitNotReached($promotion, $user);

        $result = $this->calculator->calculate($promotion, $subtotal);

        return new PromotionValidationResult(
            promotion: $promotion,
            discount: $result['discount'],
            totalAfter: $result['total_after'],
        );
    }

    private function assertActive(Promotion $promotion): void
    {
        if (! $promotion->is_active) {
            throw PromotionInvalidException::inactive();
        }
    }

    private function assertWithinDates(Promotion $promotion): void
    {
        $now = now();

        if ($promotion->starts_at !== null && $promotion->starts_at->greaterThan($now)) {
            throw PromotionInvalidException::inactive();
        }

        if ($promotion->ends_at !== null && $promotion->ends_at->lessThan($now)) {
            throw PromotionInvalidException::expired();
        }
    }

    private function assertHasRemainingUses(Promotion $promotion): void
    {
        if ($promotion->max_uses !== null && $promotion->uses_count >= $promotion->max_uses) {
            throw PromotionInvalidException::exhausted();
        }
    }

    private function assertMinAmountMet(Promotion $promotion, string $subtotal): void
    {
        if ($promotion->min_amount === null) {
            return;
        }

        if ((float) $subtotal < (float) $promotion->min_amount) {
            throw PromotionInvalidException::minAmountNotMet($promotion->min_amount);
        }
    }

    private function assertTourApplicable(Promotion $promotion, TourDate $tourDate): void
    {
        $allowedTours = $promotion->applicable_tours;

        if ($allowedTours === null || $allowedTours === []) {
            return;
        }

        if (! in_array($tourDate->tour_id, $allowedTours, true)) {
            throw PromotionInvalidException::tourNotApplicable();
        }
    }

    private function assertUserLimitNotReached(Promotion $promotion, User $user): void
    {
        if ($promotion->max_uses_per_user === null) {
            return;
        }

        $userUses = Booking::query()
            ->where('user_id', $user->id)
            ->where('promotion_id', $promotion->id)
            ->count();

        if ($userUses >= $promotion->max_uses_per_user) {
            throw PromotionInvalidException::userLimitReached();
        }
    }
}
