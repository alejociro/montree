<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Review\ModerateReviewAction;
use App\Actions\Review\RespondReviewAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Review\ModerateReviewRequest;
use App\Http\Requests\Admin\Review\RespondReviewRequest;
use App\Http\Resources\Review\ReviewResource;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ReviewController extends Controller
{
    public function __construct(
        private ModerateReviewAction $moderate,
        private RespondReviewAction $respond,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Review::query()->with(['tour', 'user'])->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ReviewResource::collection($query->paginate(20));
    }

    public function updateStatus(ModerateReviewRequest $request, Review $review): ReviewResource
    {
        $action = $request->validated('status') === 'approved' ? 'approve' : 'reject';
        $updated = $this->moderate->{$action}($review, $request->user(), $request->validated('rejection_reason'));

        return new ReviewResource($updated);
    }

    public function respond(RespondReviewRequest $request, Review $review): ReviewResource
    {
        $updated = $this->respond->handle($review, $request->user(), $request->validated('response'));

        return new ReviewResource($updated);
    }
}
