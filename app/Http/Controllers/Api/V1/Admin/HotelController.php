<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\LogisticsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Logistics\StoreHotelRequest;
use App\Http\Requests\Admin\Logistics\UpdateHotelRequest;
use App\Http\Resources\Admin\HotelResource;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class HotelController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $hotels = Hotel::query()
            ->withCount('tourDates')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('name')
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return HotelResource::collection($hotels);
    }

    public function store(StoreHotelRequest $request): JsonResponse
    {
        $hotel = Hotel::create($request->validated());

        return new JsonResponse(['data' => (new HotelResource($hotel->loadCount('tourDates')))->resolve()], 201);
    }

    public function update(UpdateHotelRequest $request, Hotel $hotel): JsonResponse
    {
        $hotel->update($request->validated());

        return new JsonResponse(['data' => (new HotelResource($hotel->loadCount('tourDates')))->resolve()]);
    }

    public function destroy(Hotel $hotel): JsonResponse
    {
        $usage = $hotel->tourDates()->count();

        if ($usage > 0) {
            throw LogisticsException::inUse('Hotel', $usage);
        }

        $hotel->delete();

        return new JsonResponse(null, 204);
    }
}
