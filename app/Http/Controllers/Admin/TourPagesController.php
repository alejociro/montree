<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Tour\BuildTourIndexStatsAction;
use App\Actions\Tour\BuildTourShowStatsAction;
use App\Actions\Tour\CreateTourAction;
use App\Actions\Tour\DeleteTourAction;
use App\Actions\Tour\UpdateTourAction;
use App\Data\Tour\TourFilters;
use App\Exceptions\TourHasActiveBookingsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tour\StoreTourRequest;
use App\Http\Requests\Admin\Tour\TourIndexRequest;
use App\Http\Requests\Admin\Tour\UpdateTourRequest;
use App\Http\Resources\Admin\TourDateDetailResource;
use App\Http\Resources\Tour\CategoryResource;
use App\Http\Resources\Tour\TourResource;
use App\Http\Resources\Tour\TourSummaryResource;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\Tour;
use App\Queries\DepartureOptionsQuery;
use App\Queries\TourOperationalSummaryQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class TourPagesController extends Controller
{
    /** La rejilla es de tres columnas: nueve por página la deja siempre completa. */
    private const PER_PAGE = 9;

    private const DETAIL_RELATIONS = ['category', 'images', 'itineraries', 'stops'];

    public function __construct(private TourOperationalSummaryQuery $operations) {}

    public function index(TourIndexRequest $request, BuildTourIndexStatsAction $buildStats): Response
    {
        $filters = TourFilters::fromRequest($request);

        return Inertia::render('Admin/Tour/Index', [
            'tours' => $this->paginated($filters),
            'filters' => $filters->toArray(),
            'categories' => $this->categories(),
            'stats' => $buildStats->handle(Gate::allows('viewAny', Booking::class)),
        ]);
    }

    public function show(Tour $tour, BuildTourShowStatsAction $buildStats): Response
    {
        Gate::authorize('view', $tour);

        $tour->load(self::DETAIL_RELATIONS)->load($this->routesRelation());

        return Inertia::render('Admin/Tour/Show', [
            'tour' => (new TourResource($tour))->resolve(),
            'stats' => $buildStats->handle($tour),
            'departures' => $this->departures($tour, upcomingOnly: true),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Tour::class);

        return Inertia::render('Admin/Tour/Create', [
            'categories' => $this->categories(),
        ]);
    }

    public function edit(Tour $tour, DepartureOptionsQuery $options): Response
    {
        Gate::authorize('update', $tour);

        return Inertia::render('Admin/Tour/Edit', $this->editProps($tour, $options));
    }

    public function store(StoreTourRequest $request, CreateTourAction $createTour): RedirectResponse
    {
        $tenant = Tenant::current();
        abort_if($tenant === null, 404);

        $tour = $createTour->execute($tenant, $request->validated());

        return redirect()->route('admin.tours.edit', $tour)->with('success', __('Tour creado.'));
    }

    public function update(UpdateTourRequest $request, Tour $tour, UpdateTourAction $updateTour): RedirectResponse
    {
        $updateTour->execute($tour, $request->validated());

        return back()->with('success', __('Tour actualizado.'));
    }

    public function destroy(Tour $tour, DeleteTourAction $deleteTour): RedirectResponse
    {
        Gate::authorize('delete', $tour);

        try {
            $deleteTour->handle($tour);
        } catch (TourHasActiveBookingsException $blocked) {
            return back()->withErrors(['tour' => $blocked->getMessage()]);
        }

        return redirect()->route('admin.tours.index')->with('success', __('Tour eliminado.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function editProps(Tour $tour, DepartureOptionsQuery $options): array
    {
        $tour->load(self::DETAIL_RELATIONS)->load($this->routesRelation());

        return [
            'tour' => (new TourResource($tour))->resolve(),
            'categories' => $this->categories($tour),
            'departures' => $this->departures($tour),
            'departureOptions' => $options->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paginated(TourFilters $filters): array
    {
        $tours = $this->operations->applyTo(Tour::query())
            ->with(['category', 'coverImage'])
            ->withCount(['images', 'bookings'])
            ->applyFilters($filters)
            ->orderBy($this->sortExpression($filters->sort), $filters->direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return TourSummaryResource::collection($tours)->response()->getData(assoc: true);
    }

    private function sortExpression(string $sort): mixed
    {
        return $this->operations->sortableExpressions()[$sort] ?? $sort;
    }

    /**
     * El contador de paradas de cada ruta se agrega en la consulta: pintarlo
     * desde la relación cargada sería una consulta por ruta del producto.
     *
     * @return array<string, callable>
     */
    private function routesRelation(): array
    {
        return ['routes' => fn (HasMany $query) => $query->with('stops')->withCount(['stops', 'tourDates'])];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function departures(Tour $tour, bool $upcomingOnly = false): array
    {
        $dates = $tour->dates()
            ->with(['tour', 'guide', 'route', 'provider', 'hotels'])
            ->when($upcomingOnly, fn (Builder $query) => $query->where('starts_at', '>', now()))
            ->orderBy('starts_at')
            ->get();

        return TourDateDetailResource::collection($dates)->resolve();
    }

    /**
     * El select ofrece solo categorías activas. La del producto que se está
     * editando viaja igual aunque esté desactivada: si no, guardar el formulario
     * la borraría del producto sin que nadie lo pidiera.
     *
     * @return array<int, array<string, mixed>>
     */
    private function categories(?Tour $tour = null): array
    {
        return CategoryResource::collection(
            Category::query()
                ->where(fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->when($tour?->category_id !== null, fn (Builder $inner) => $inner->orWhere('id', $tour?->category_id)))
                ->orderBy('display_order')
                ->orderBy('name')
                ->get()
        )->resolve();
    }
}
