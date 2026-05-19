# F004 — Plan técnico

## Backend
- `app/Services/Catalog/TourCatalogQuery`: build de query con filtros, sort, pagination. Solo `status=active`, con `with('category','coverImage')`, `withCount('reviews')`, eager favorite check via subselect si user logged.
- `app/Http/Controllers/Api/V1/CatalogController` (`index`), `CategoryController` (`index`).
- `app/Http/Requests/CatalogIndexRequest` valida query params (sort whitelist, per_page max:48).
- `app/Http/Resources/CatalogTourResource` — shape del contrato. `is_favorite` resuelto en `additional()` con set precargado.
- Tour::scopeActive(), Tour::scopeWithNextFutureDate() helpers ya o agregar.
- Routes públicas: `/api/v1/tours`, `/api/v1/tours/categories` SIN auth.
- Inertia route `/tours` y opcional `/` (Welcome → catálogo si tenant tiene tours).

## Frontend
- `resources/js/pages/Catalog.vue` — grid responsive (1/2/3/4 cols).
- Organisms: `TourGrid`, `FilterSidebar`, `CatalogSearchBar`, `ActiveFilters`.
- Molecules: `TourCard` (img + price + duración + rating + difficulty badge + favorite btn), `PriceRangeFilter`, `CategoryChip`, `SortDropdown`.
- Reutilizar `Skeleton`, `Badge`, `Button` del starter.
- Page sin `defineOptions` layout default `AppLayout` (público). Si user no logged, el shared `auth.user=null`.
- Estados: loading (skeleton 6 cards), error, empty (con CTA "Ver tours en otras categorías"), sin resultados (sugerencia relajar filtros).
- Wayfinder: importa de `@/actions/App/Http/Controllers/Api/V1/CatalogController`.
- `useDebouncedRef` o setTimeout para search input (300ms debounce).

## Tests
- Feature: `CatalogControllerTest` (filters happy + invalid sort + tenant isolation + per_page cap + empty result), `CategoryControllerTest` (only categories with active tours).
- Unit: `TourCatalogQueryTest` (each filter applies).
