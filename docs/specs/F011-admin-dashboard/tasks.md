# F011 — Tasks

## Backend
- [x] Services (`Services/Dashboard/`): DashboardMetricsAggregator, RevenueCalculator, BookingCounters, TopToursResolver, OccupancyCalculator, PeriodFilter
- [x] DTOs (`Data/Dashboard/`): DashboardSnapshot, RevenueBreakdown, BookingCounts, OccupancyBreakdown
- [x] Action: ExportRevenueReportAction
- [x] Form Requests: DashboardRequest, ExportRevenueRequest
- [x] Controllers: DashboardController (show), RevenueReportController (invoke), Admin/BookingController (index)
- [x] Resources: DashboardResource, BookingSummaryResource
- [x] Policy: DashboardPolicy
- [x] Routes: routes/api.php admin block + routes/web.php Inertia page (/admin/dashboard)
- [x] Tests: DashboardControllerTest, RevenueReportControllerTest, BookingControllerTest (feature) + PeriodFilterTest, RevenueCalculatorTest (unit)
- [x] Wayfinder generate
- [x] Pint + tests pasan

## Frontend
- [x] Page Admin/Dashboard.vue
- [x] Organisms: DashboardStatGrid, TopToursTable, UpcomingDatesTable, RevenueSparkline
- [x] Molecules: StatCard, PeriodSelector, ExportRevenueButton, TrendIndicator
- [x] AdminSidebar: agregar "Dashboard" como primer item
- [x] Types: types/dashboard.ts
- [x] types-check, lint, build OK

## Review
- [x] Tests, lint, types verde
- [x] N+1 verified
- [x] Operator no ve botón export y backend lo rechaza

---

## Notas
- `2026-05-17` (claude principal): F011 arrancado.
- `2026-05-17` (worktree agent): F011 completado. Backend (services + DTOs + actions + controllers + resources + policy + tests) y frontend (page + organisms + molecules + types) listos. Tests 126/126 verdes, pint clean, types-check clean (errors pre-existentes en otros archivos), build OK.
