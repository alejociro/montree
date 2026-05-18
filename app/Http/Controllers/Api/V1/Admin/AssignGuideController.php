<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Team\AssignGuideAction;
use App\Http\Controllers\Controller;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssignGuideController extends Controller
{
    public function __construct(private AssignGuideAction $assign) {}

    public function __invoke(Request $request, TourDate $tourDate): JsonResponse
    {
        if (! $request->user()?->hasRole(['admin', 'operator'])) {
            abort(403);
        }

        $data = $request->validate(['guide_id' => ['nullable', 'integer', 'exists:users,id']]);
        $guide = $data['guide_id'] !== null ? User::query()->find((int) $data['guide_id']) : null;

        $this->assign->handle($tourDate, $guide);

        return new JsonResponse([
            'data' => [
                'id' => $tourDate->id,
                'guide_id' => $tourDate->fresh()->guide_id,
            ],
        ]);
    }
}
