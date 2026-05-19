# F011 — Plan técnico

## 1. Resumen

Endpoint compuesto que devuelve métricas agregadas del tenant en el periodo elegido. Servicio `DashboardMetricsAggregator` que ejecuta queries optimizadas (sums + counts + scopes). Page Inertia `Admin/Dashboard.vue` con cards y selector de periodo. Export CSV via streaming response.

## 2. Backend

### Services (`app/Services/Dashboard/`)
- `DashboardMetricsAggregator::for(Tenant $tenant, PeriodFilter $period): DashboardSnapshot` — orquesta los queries.
- `RevenueCalculator::between($tenant, $start, $end): RevenueBreakdown` — sums sobre `payments` completed.
- `BookingCounters::between($tenant, $start, $end): BookingCounts` — counts por status.
- `TopToursResolver::for($tenant, $start, $end, int $limit=5): Collection`.
- `OccupancyCalculator::upcoming($tenant, int $days=7): OccupancyBreakdown`.
- `PeriodFilter` value object: `key`, `start`, `end`, `previousStart`, `previousEnd`.

### Action
- `ExportRevenueReportAction::handle(Tenant $tenant, Carbon $from, Carbon $to, string $groupBy, string $format): StreamedResponse|array`.

### DTOs (`app/Data/Dashboard/`) — readonly classes
- `DashboardSnapshot`, `RevenueBreakdown`, `BookingCounts`, `OccupancyBreakdown`.

### Form Requests
- `DashboardRequest` (validates `period`, `tz`).
- `ExportRevenueRequest` (validates `from`, `to`, range, group_by, format).

### Controllers (`app/Http/Controllers/Api/V1/Admin/`)
- `DashboardController` (`show`).
- `RevenueReportController` (`__invoke`).
- `Admin\BookingController` (`index` — recientes con `attention_only`).

### Resources
- `DashboardResource`, `BookingSummaryResource`.

### Policy
- `DashboardPolicy::view` → admin/operator.
- Export: solo admin (gate inline en controller via `$user->hasRole('admin')` con team scoped).

### Cache
- Cache TTL 5 min keyed por `tenant:{id}:dashboard:{period}`. Invalidación: ninguna (TTL natural). Cache se invalida al expirar.

## 3. Frontend

### Page
- `resources/js/pages/Admin/Dashboard.vue` — grid responsive con:
  - StatCards (Revenue, Bookings, Rating)
  - RevenueChart (placeholder con simple line — usar `<canvas>` y librería pequeña o el SVG hand-rolled). NO instalar deps nuevas en MVP; usar SVG/CSS para sparkline simple.
  - TopToursTable
  - UpcomingDatesTable
  - PeriodSelector

### Organisms (`resources/js/components/organisms/`)
- `DashboardStatGrid.vue` — wrapper de cards.
- `TopToursTable.vue`, `UpcomingDatesTable.vue`.
- `RevenueSparkline.vue` — SVG basado en datos del response (no chart lib). Para MVP, just show numbers + trend arrow.

### Molecules
- `StatCard.vue` — title, value, currency optional, growth indicator (↑/↓ + %).
- `PeriodSelector.vue` — select shadcn-vue con presets.
- `ExportRevenueButton.vue` — dropdown con format (CSV / JSON), opens datepicker dialog.
- `TrendIndicator.vue` — arrow + colored pct.

### AdminSidebar
- Agregar item "Dashboard" → `/admin/dashboard` con icon `LayoutDashboard`. Ubicar PRIMERO en la lista (antes de Configuración).

### Wayfinder
- `@/actions/App/Http/Controllers/Api/V1/Admin/DashboardController` (show)
- `@/actions/App/Http/Controllers/Api/V1/Admin/RevenueReportController` (invoke)

## 4. Tests

### Feature
- `DashboardControllerTest`: admin sees full payload; operator sees same but `permissions.can_export_reports=false`; rating/revenue calculated correctly; growth_pct null when previous=0; tenant isolation.
- `RevenueReportControllerTest`: csv content-type + format + correct grouping; operator gets 403; range > 366 = 422.
- `BookingControllerTest`: attention_only filter; pagination.

### Unit
- `Services/Dashboard/PeriodFilterTest`: each preset returns expected start/end with proper TZ.
- `Services/Dashboard/RevenueCalculatorTest`: skips failed/refunded payments.

## 5. Decisiones
- **NO chart library** en MVP. SVG hand-rolled o sparkline simple. Considerar Chart.js cuando F011 evolucione.
- **Cache 5 min**: spec dice "muchos datos: lazy load secciones y cache 5 min".
- **Operator restriction**: enforcement en backend (no en frontend solamente).
- **No bookings detail page** acá — solo lista resumida para dashboard. Detail completo es feature aparte (F006/F009 lo cubren parcialmente).

## 6. Out of scope
- Custom date range picker (sólo presets en MVP)
- Multiple chart types
- Real-time updates (websockets)
