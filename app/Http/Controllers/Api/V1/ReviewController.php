<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Review\CreateReviewAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Resources\Review\ReviewResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ReviewController extends Controller
{
    public function __construct(private CreateReviewAction $create) {}

    public function store(StoreReviewRequest $request): JsonResponse
    {
        $booking = Booking::query()->find((int) $request->validated('booking_id'));

        if ($booking === null) {
            throw new NotFoundHttpException('Booking not found.');
        }

        $review = $this->create->handle($request->user(), $booking, $request->validated());

        return (new ReviewResource($review))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
