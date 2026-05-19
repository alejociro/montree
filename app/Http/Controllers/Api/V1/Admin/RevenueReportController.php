<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Dashboard\ExportRevenueReportAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dashboard\ExportRevenueRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class RevenueReportController extends Controller
{
    public function __construct(private ExportRevenueReportAction $action) {}

    public function __invoke(ExportRevenueRequest $request): Response|JsonResponse
    {
        $tenant = Tenant::current();

        abort_if($tenant === null, 404, 'No tenant for this host.');

        $result = $this->action->handle(
            $request->fromDate(),
            $request->toDate(),
            $request->groupBy(),
            $request->exportFormat(),
        );

        if ($result instanceof Response) {
            return $result;
        }

        return new JsonResponse(['data' => $result]);
    }
}
