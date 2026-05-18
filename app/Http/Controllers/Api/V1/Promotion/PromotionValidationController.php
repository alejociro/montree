<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Promotion;

use App\Actions\Promotion\ValidatePromotionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\ValidatePromotionRequest;
use App\Http\Resources\Promotion\PromotionValidationResource;
use App\Models\TourDate;
use Illuminate\Http\JsonResponse;

final class PromotionValidationController extends Controller
{
    public function __construct(private ValidatePromotionAction $validateAction) {}

    public function __invoke(ValidatePromotionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tourDate = TourDate::query()->findOrFail((int) $data['tour_date_id']);

        $result = $this->validateAction->handle(
            code: (string) $data['code'],
            tourDate: $tourDate,
            subtotal: (string) $data['subtotal'],
            user: $request->user(),
        );

        return new JsonResponse(['data' => (new PromotionValidationResource($result))->resolve()]);
    }
}
