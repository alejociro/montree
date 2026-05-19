# F011 — Dashboard admin y métricas

## Descripción

Panel de control para admins/operators con métricas del negocio: ingresos, reservas, ocupación, reseñas pendientes, tours populares.

## User stories

- Como admin, quiero ver resumen de ingresos del mes actual.
- Como admin, quiero ver reservas y tasa de conversión.
- Como admin, quiero ver qué tours son más populares.
- Como admin, quiero ver próximas fechas con ocupación.
- Como admin, quiero exportar reportes de ingresos.
- Como operator, quiero ver reservas recientes que necesitan atención.

## Acceptance criteria

- **Given** admin en dashboard, **then** ve: ingresos (total, neto, crecimiento%), reservas (total, confirmadas, canceladas, pendientes), rating promedio.
- **Given** periodo "últimos 30 días", **then** datos reflejan solo ese rango.
- **Given** sección "top tours", **then** muestra los 5 con más reservas en el periodo.
- **Given** sección "próximas fechas", **then** muestra próximos 7 días con ocupación.
- **Given** operator, **then** ve mismo dashboard pero sin exportar.
- **Given** reporte de ingresos, **when** se exporta, **then** genera archivo con desglose por día/semana/mes.

## Edge cases

- Tenant nuevo sin datos: dashboard vacío con onboarding tips.
- Periodo sin reservas: $0 y 0 reservas, no error.
- Crecimiento cuando periodo anterior = 0: mostrar "N/A".
- Muchos datos: lazy load + cache 5 min.

## Dependencias

- F003 (Tours), F006 (Bookings), F007 (Payments).

## Endpoints involucrados

```
GET    /api/v1/admin/dashboard?period=
GET    /api/v1/admin/reports/revenue?from=&to=&format=
GET    /api/v1/admin/bookings
```

## Componentes UI

- Pages: `DashboardPage`
- Organisms: `RevenueCard`, `BookingsOverview`, `TopToursTable`, `UpcomingDatesTable`, `RevenueChart`
- Molecules: `StatCard`, `MiniChart`, `PeriodSelector`, `ExportButton`
- Atoms: `Badge`, `TrendIndicator`, `ProgressBar`, `Skeleton`

## Datos requeridos

Tablas: `bookings`, `payments`, `tours`, `tour_dates`, `reviews`

---

## Decisiones abiertas

- [ ] Librería de charts (Chart.js, ApexCharts, recharts-vue).

## Changelog

- `2026-05-17` — Creación inicial.
- `2026-05-19` — Review Playwright detectó (P0-2 sistémico para F006/F008/F009/F010/F011/F012/F013/F014) que `router.post('/api/v1/...')` no dispara request. Ver `docs/review-2026-05-19/findings.md`.
