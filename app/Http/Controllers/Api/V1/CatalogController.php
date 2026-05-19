<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CatalogIndexRequest;
use App\Http\Resources\Catalog\CatalogTourResource;
use App\Models\User;
use App\Services\Catalog\TourCatalogQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CatalogController extends Controller
{
    public function __construct(private TourCatalogQuery $catalogQuery) {}

    public function index(CatalogIndexRequest $request): AnonymousResourceCollection
    {
        /** @var User|null $viewer */
        $viewer = $request->user();

        $tours = $this->catalogQuery->paginate($request->filters(), $viewer);

        return CatalogTourResource::collection($tours);
    }
}
