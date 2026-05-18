# F004 — Tasks

## Backend
- [ ] `Services/Catalog/TourCatalogQuery`
- [ ] Form Request `CatalogIndexRequest`
- [ ] Controllers: `CatalogController` (index), `CategoryController` (index)
- [ ] Resource `CatalogTourResource`
- [ ] Routes públicas `/api/v1/tours` + `/api/v1/tours/categories`
- [ ] Inertia route `/tours` (+ opcional cambiar `/` por Catalog si tenant)
- [ ] Tests feature (5) + unit (1)
- [ ] Pint + wayfinder + suite verde

## Frontend
- [ ] Page `Catalog.vue`
- [ ] Organisms: TourGrid, FilterSidebar, CatalogSearchBar, ActiveFilters
- [ ] Molecules: TourCard, PriceRangeFilter, CategoryChip, SortDropdown
- [ ] Skeleton/Empty/Error states
- [ ] Debounced search
- [ ] types/catalog.ts
- [ ] types-check + lint + build

## Review
- [ ] Tests/lint/types verde
- [ ] Cobertura AC + tenant isolation
- [ ] Sin URLs hardcoded
