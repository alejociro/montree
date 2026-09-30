<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\TourDateStatus;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds the public catalog listing query for the current tenant.
 *
 * Always scoped to `status=active` (tenant filter applied by the global scope).
 * Sorting includes a synthetic `next_date_asc` that joins the soonest future
 * open tour date so the catalog ranks bookable tours first.
 *
 * @phpstan-type CatalogFilters array{
 *     search?: string|null,
 *     category?: string|null,
 *     difficulty?: string|null,
 *     price_min?: float|null,
 *     price_max?: float|null,
 *     sort?: string|null,
 *     per_page?: int|null,
 * }
 */
final class TourCatalogQuery
{
    private const DEFAULT_PER_PAGE = 12;

    private const MAX_PER_PAGE = 48;

    /**
     * @param  CatalogFilters  $filters
     * @return LengthAwarePaginator<int, Tour>
     */
    public function paginate(array $filters, ?User $viewer = null): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($filters['per_page'] ?? null);
        $sort = $filters['sort'] ?? 'next_date_asc';

        $query = Tour::query()
            ->active()
            ->with(['category', 'coverImage'])
            ->select('tours.*')
            ->addSelect([
                'next_date_starts_at' => $this->nextDateSubquery(),
                'from_price' => $this->fromPriceSubquery(),
            ]);

        $this->applyFilters($query, $filters);
        $this->applySort($query, $sort);

        /** @var LengthAwarePaginator<int, Tour> $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        $this->hydrateFavorites($paginator->getCollection()->all(), $viewer);

        return $paginator;
    }

    /**
     * @param  Builder<Tour>  $query
     * @param  CatalogFilters  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $search = $this->trim($filters['search'] ?? null);
        if ($search !== null) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('tours.name', 'like', $term)
                    ->orWhere('tours.short_description', 'like', $term)
                    ->orWhere('tours.description', 'like', $term)
                    ->orWhereHas('category', function (Builder $category) use ($term): void {
                        $category->where('name', 'like', $term)
                            ->orWhere('slug', 'like', $term);
                    });
            });
        }

        $categorySlug = $this->trim($filters['category'] ?? null);
        if ($categorySlug !== null) {
            $query->whereHas('category', fn (Builder $relation) => $relation->where('slug', $categorySlug));
        }

        $difficulty = $this->trim($filters['difficulty'] ?? null);
        if ($difficulty !== null) {
            $query->where('tours.difficulty', $difficulty);
        }

        if (isset($filters['price_min'])) {
            $query->where('tours.base_price', '>=', $filters['price_min']);
        }

        if (isset($filters['price_max'])) {
            $query->where('tours.base_price', '<=', $filters['price_max']);
        }
    }

    /**
     * @param  Builder<Tour>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('tours.base_price', 'asc')->orderBy('tours.id'),
            'price_desc' => $query->orderBy('tours.base_price', 'desc')->orderBy('tours.id'),
            'rating_desc' => $query->orderBy('tours.rating_average', 'desc')->orderBy('tours.rating_count', 'desc')->orderBy('tours.id'),
            'newest' => $query->orderBy('tours.created_at', 'desc')->orderBy('tours.id'),
            default => $query->orderByRaw('next_date_starts_at IS NULL')
                ->orderBy('next_date_starts_at', 'asc')
                ->orderBy('tours.id'),
        };
    }

    /**
     * Espeja `TourDate::scopeBookable()` a nivel SQL: la salida más próxima
     * que todavía admite una reserva nueva, no solo la más próxima abierta.
     */
    private function nextDateSubquery(): QueryBuilder
    {
        $now = Carbon::now();

        return DB::table('tour_dates')
            ->selectRaw('MIN(starts_at)')
            ->whereColumn('tour_dates.tour_id', 'tours.id')
            ->where('tour_dates.status', TourDateStatus::Open->value)
            ->where('tour_dates.starts_at', '>', $now)
            ->where(fn ($q) => $this->applyBookableWindow($q, $now));
    }

    /**
     * «Desde $X» del catálogo (T7): el mínimo precio EFECTIVO entre las
     * salidas reservables del tour, o `base_price` si no tiene ninguna.
     *
     * WHY: se resuelve con una subconsulta escalar en vez de cargar las
     * salidas en PHP —el catálogo pagina decenas de tours por página y cada
     * uno puede tener docenas de salidas—.
     */
    private function fromPriceSubquery(): QueryBuilder
    {
        $now = Carbon::now();

        return DB::table('tour_dates')
            ->selectRaw('MIN(COALESCE(tour_dates.price_override, tours_price.base_price))')
            ->join('tours as tours_price', 'tours_price.id', '=', 'tour_dates.tour_id')
            ->whereColumn('tour_dates.tour_id', 'tours.id')
            ->where('tour_dates.status', TourDateStatus::Open->value)
            ->where('tour_dates.starts_at', '>', $now)
            ->where(fn ($q) => $this->applyBookableWindow($q, $now));
    }

    /**
     * Espeja la parte de {@see TourDate::scopeBookable()} que
     * decide si una fila admite una reserva nueva, además del `status`/
     * `starts_at` que cada subconsulta ya filtra por su cuenta (T12): el
     * cierre propio de la salida manda; sin él, rige la regla de la agencia
     * (`booking_advance_hours`); sin ninguno, cualquier salida abierta con el
     * inicio por venir sirve.
     */
    private function applyBookableWindow(QueryBuilder $query, Carbon $now): QueryBuilder
    {
        $advanceHours = Tenant::current()?->configuration?->booking_advance_hours;

        return $query->where('tour_dates.booking_closes_at', '>', $now)
            ->orWhere(function (QueryBuilder $inner) use ($now, $advanceHours): void {
                $inner->whereNull('tour_dates.booking_closes_at');

                if ($advanceHours !== null) {
                    $inner->where('tour_dates.starts_at', '>', $now->copy()->addHours($advanceHours));
                }
            });
    }

    /**
     * @param  array<int, Tour>  $tours
     */
    private function hydrateFavorites(array $tours, ?User $viewer): void
    {
        if ($viewer === null || $tours === []) {
            return;
        }

        $tourIds = array_map(static fn (Tour $tour): int => $tour->id, $tours);

        $favoriteTourIds = DB::table('favorites')
            ->where('user_id', $viewer->id)
            ->whereIn('tour_id', $tourIds)
            ->pluck('tour_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $favoriteSet = array_flip($favoriteTourIds);
        foreach ($tours as $tour) {
            $tour->setAttribute('is_favorite', array_key_exists($tour->id, $favoriteSet));
        }
    }

    private function resolvePerPage(?int $requested): int
    {
        if ($requested === null || $requested < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($requested, self::MAX_PER_PAGE);
    }

    private function trim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
