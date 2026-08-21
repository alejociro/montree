<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Team\AssignGuideAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourDate\AssignGuideRequest;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class AssignGuideController extends Controller
{
    public function __construct(private AssignGuideAction $assign) {}

    public function __invoke(AssignGuideRequest $request, TourDate $tourDate): JsonResponse
    {
        $guide = User::query()->findOrFail((int) $request->validated('guide_id'));

        $updated = $this->assign->handle($tourDate, $guide);

        return new JsonResponse([
            'data' => [
                'id' => $tourDate->id,
                'guide_id' => $updated->guide_id,
            ],
        ]);
    }
}
