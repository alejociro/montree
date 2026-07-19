# F017 — Tasks

> Checklist atómico. Cada item se asigna a un rol y se marca al terminar.
> Generado a partir de `plan.md`. Modificaciones se reflejan en ambos.

---

## DB (`montree-db-architect`)

- [x] Migration `create_routes_table` (tenant-scoped, índice `[tenant_id, name]`)
- [x] Migration `create_providers_table`
- [x] Migration `create_hotels_table`
- [x] Migration `create_tour_date_hotels_table` (unique compuesto, cascade)
- [x] Migration `add_route_and_provider_to_tour_dates` (nullable FKs, `restrictOnDelete`)
- [x] Modelos `Route`, `Provider`, `Hotel` (final, BelongsToTenant, fillable, relaciones)
- [x] Extender `TourDate`: fillable + relaciones `route/provider/hotels`
- [x] Factories de los 3 modelos + states en `TourDateFactory`
- [x] Seeder demo: 2 rutas, 2 proveedores, 2 hoteles, condiciones en fechas existentes
- [x] `php artisan migrate` verde + verificación con database-schema
- [x] Pint

## Backend (`montree-backend-dev`)

- [x] `TourDateException` y `LogisticsException` (códigos del contrato)
- [x] `CreateTourDateAction` / `UpdateTourDateAction` / `CancelTourDateAction` / `DeleteTourDateAction`
- [x] Modificar `ChangeTourStatusAction`: activar requiere solo ≥1 imagen (actualizar tests F003 afectados)
- [x] Form Requests de salidas (store/update/cancel) con validación tenant-aware de guide/route/provider/hotels
- [x] Form Requests de routes/providers/hotels (store/update × 3)
- [x] `TourDateController` (index/store/update/destroy) + `CancelTourDateController` invokable
- [x] `RouteController`, `ProviderController`, `HotelController` (index/store/update/destroy, delete con check de uso → 409)
- [x] `TourDateDetailResource`, `RouteResource`, `ProviderResource`, `HotelResource` (con `tour_dates_count`)
- [x] Rutas en `routes/api.php` (grupo admin existente)
- [x] Tests: salidas (happy/failure/edge/tenant-isolation por endpoint, según plan §4)
- [x] Tests: logística CRUD + `RESOURCE_IN_USE`
- [x] Test: activar producto sin fechas pasa; catálogo público lo muestra con `has_future_dates: false` (ver notas)
- [x] `php artisan wayfinder:generate`
- [x] Pint + suite verde (340/340)
- [x] `TourDateDisplayStatus` enum (estado de presentación derivado, labels en español)
- [x] `TourDate::displayStatus()` + scope `withDisplayStatus()` (derivación sin columnas nuevas)
- [x] `display_status` y `tour` (id/name/slug) en `TourDateDetailResource` (adición no breaking)
- [x] Endpoint global `GET /api/v1/admin/tour-dates` (`TourDateIndexController` invokable + `TourDateIndexRequest`)
- [x] Tests: `TourDateGlobalIndexTest` (happy/403/422/edge derivación/tenant isolation) — 5 tests

### Listado global de salidas + estado derivado (agregado 2026-07-13)

- [~] Enum `App\Enums\TourDateDisplayStatus` (open/full/closed/cancelled/in_progress/finished) — en progreso 2026-07-13
- [~] `TourDate::displayStatus()` con reglas de derivación (cancelled gana → finished → in_progress → status almacenado) — en progreso 2026-07-13
- [~] Ampliar `TourDateDetailResource`: `display_status` + `tour: { id, name, slug }` (adición no-breaking, sirve index anidado y global) — en progreso 2026-07-13
- [~] `IndexTourDatesRequest` (filtros status/tour_id/from/to/direction/per_page tenant-aware) — en progreso 2026-07-13
- [~] Controller + ruta `GET /api/v1/admin/tour-dates` (global cross-producto, eager-load sin N+1, paginado) — en progreso 2026-07-13
- [~] Tests: listado global (happy/tenant-isolation/filtros status derivado/tour_id/from/to/422) + `displayStatus()` — en progreso 2026-07-13
- [~] `php artisan wayfinder:generate` (nueva ruta) — en progreso 2026-07-13

### Home público "Próximas salidas" (agregado 2026-07-13)

Backend (`montree-backend-dev`) — listo:
- [x] Prop deferred `upcomingDepartures` en `HomePageController` (`Inertia::defer`, no endpoint API) — 2026-07-13
- [x] Resource público `App\Http\Resources\Catalog\UpcomingDepartureResource` (shape mínimo, sin datos operativos internos) — 2026-07-13
- [x] Query: `openFuture()` + tour activo + `with('tour.coverImage')` + orden `starts_at` asc + límite 6 — 2026-07-13
- [x] Tests en `tests/Feature/HomePageTest.php`: happy (orden asc + shape + keys prohibidas ausentes), edge (cancelled/full/closed/past/tour-inactivo excluidos + cap 6), tenant isolation — 3 tests, `HomePageTest` verde 9/9 — 2026-07-13

Frontend (`montree-frontend-dev`) — listo (2026-07-13):
- [x] Tipo `UpcomingDeparture` (aditivo en `types/home.ts`) — 2026-07-13
- [x] Sección "Próximas salidas" (`Deferred` + skeleton) en `Home.vue` entre "Tours destacados" y "Promociones"; grid 1/2/3 cols con imagen+fallback, Link al detalle (`tourShow`), fecha ES (`formatTourDate` + "hasta …"), badge "¡Últimos X cupos!" (≤3), precio (`formatCurrency`), CTA "Reservar" → `bookingCreate({ query: { tour_date_id } })` — 2026-07-13
- [x] Array vacío → la sección no se renderiza — 2026-07-13
- [x] Wayfinder para todas las URLs (cero hardcode); eslint/types:check/build OK — 2026-07-13
- [x] Eliminar la sección de newsletter del storefront `Home.vue` (F013 admin + endpoint API intactos) — 2026-07-13

## Frontend (`montree-frontend-dev`)

- [x] Types en `resources/js/types/logistics.ts`
- [x] `TourDatesPanel.vue` (tabla próximas/pasadas/canceladas + acciones)
- [x] `TourDateFormDialog.vue` (crear/editar con condiciones: guía, ruta, proveedor, hoteles)
- [x] Integrar panel de salidas en `Admin/Tour/Edit.vue`
- [x] `LogisticsCrudPanel.vue` + page `Admin/Logistics/Index.vue` (tabs Rutas/Proveedores/Hoteles)
- [x] Entrada "Logística" en sidebar admin + labels "Productos"/"Salidas" en el panel
- [x] `TourDetail.vue` público: card "Sin fechas disponibles" cuando `dates` vacío
- [x] Llamadas API vía `useApi()` + Wayfinder (cero URLs hardcodeadas; GET de solo lectura con `fetch` siguiendo el patrón de `Admin/Team/Index.vue`)
- [x] Estados loading/error/empty en panel y dialogs
- [x] `npm run types:check` (solo 2 errores preexistentes AppHeader/Notifications) + lint (solo 3 errores preexistentes ajenos a F017) + build verde
- [x] Probar en navegador: crear salida con condiciones + editar + cancelar + logística CRUD (verificado 2026-07-12 con Playwright: salida creada con guía/ruta/proveedor/hotel, cancelación con motivo, ruta creada, delete de ruta en uso → 409 protegido, producto activo sin fechas visible en catálogo con "Sin disponibilidad" y detalle con card "Sin fechas disponibles")

### Listado global "Tours" (agregado 2026-07-13)

- [x] Page `Admin/Departures/Index.vue` (tabla producto/fecha/ocupación/precio/guía/condiciones/estado + filtros estado/producto/rango + acciones editar/cancelar) — 2026-07-13
- [x] Segunda entrada de sidebar admin: "Productos" (`/admin/tours`) y "Tours" (`/admin/departures`) — 2026-07-13
- [x] Reutilizar `TourDateFormDialog` (editar) y flujo de cancelación de `TourDatesPanel` desde el listado global — 2026-07-13
- [x] Types: `TourDateGlobalAdmin` (`extends TourDateAdmin` con `display_status` + `tour`) en `resources/js/types/logistics.ts` — 2026-07-13

## Review (`montree-reviewer`)

- [ ] Tests pasan (suite completa)
- [ ] Pint / types / ESLint pasan
- [ ] Spec F017 cubierta 100%
- [ ] Constitución respetada (controllers ≤10 líneas, sin validación inline, N+1 check en index de salidas)
- [ ] Sin código muerto

## Notas durante implementación

- **Validación de `capacity` ≥ `booked_count`**: se resolvió en `UpdateTourDateRequest` (closure sobre `booked_count` del route-model) → 422 con error en el campo `capacity`, como pide el contrato. El plan §2 mencionaba hacerlo en la Action; se prefirió el Form Request (constitución §2.7 fail-fast en el borde). La Action sólo maneja las reglas de estado (starts_at con reservas → 409, salida cancelada → 409).
- **`starts_at` con reservas activas**: "reservas activas" = `pending_payment` + `confirmed`. `DELETE` bloquea con CUALQUIER booking (activo o histórico), como dice el contrato.
- **Serialización de fechas**: se usa `toIso8601String()` (offset `+00:00`) para ser consistente con el resto de la app (`PublicTourResource`, `BookingPagesController`). El contrato muestra sufijo `Z`; es equivalente y el frontend ya parsea ambos.
- **Razón de cancelación**: no hay columna dedicada (no se pidió migration); se guarda en `notes` con prefijo `Cancelación:` preservando notas previas.
- **Catálogo público sin fechas**: ya está cubierto por `CatalogControllerTest::test_sort_next_date_asc_orders_by_soonest_future_open_date` (F004), que verifica que un tour activo sin fechas aparece con `has_future_dates: false`. No se duplicó.
- **Autorización**: los Form Requests autorizan con `can('create', Tour::class)` (gate admin/operator ya existente); no se crearon Policies por modelo de logística (plan §2 lo indica). El middleware `tenant_admin.only` ya gatea el grupo.
- **N+1**: verificado — el index de salidas hace 5 queries fijas (dates + tour + guide + route + provider + hotels) independiente de la cantidad de filas.
- **Colisión `Route`**: en `RouteController` se importa `use App\Models\Route as RouteModel;` para no chocar con el facade.
- **Listado global de salidas (2026-07-13)**: `GET /api/v1/admin/tour-dates` (name `api.v1.admin.tour-dates.index`) en el mismo grupo `tenant_admin.only`, controller invokable `TourDateIndexController` (1 statement, autorización delegada al Form Request). Filtros vía `TourDateIndexRequest`: `status` (whitelist del enum `TourDateDisplayStatus`), `tour_id` (`Rule::exists` con `where('tenant_id', ...)` porque el global scope NO aplica en exists), `from`/`to` sobre `starts_at` (`to` ≥ `from`), `per_page` (max 100, default 15), `direction` (asc/desc, default desc). Orden fijo por `starts_at`.
- **Estado de presentación derivado**: `TourDateDisplayStatus` (open/full/closed/cancelled/in_progress/finished) NO agrega columnas. `TourDate::displayStatus()` deriva en lectura (cancelled gana; luego finished si `COALESCE(ends_at,starts_at) < now`; luego in_progress si dentro de la ventana con `ends_at` no null; si no, mapea el status almacenado). `scopeWithDisplayStatus()` replica la derivación en SQL (usa `whereRaw COALESCE` + `whereNot` para excluir in_progress de open/full/closed) para poder filtrar y paginar. Bindings Carbon se serializan por `prepareBindings()` (cross-DB, funciona en SQLite de tests).
- **Resource extendido (no breaking)**: `TourDateDetailResource` agrega `display_status` (siempre presente) y `tour` (`whenLoaded`, id/name/slug). El `tour` ya se hacía eager load en todos los endpoints que usan el resource (nested index, cancel, respondWith y el nuevo global index incluyen `tour` en RELATIONS), por lo que `effective_price` (que usa `$this->tour->base_price`) y el nuevo campo `tour` no introducen N+1.
- **Autorización del global index**: `TourDateIndexRequest::authorize()` usa `can('viewAny', Tour::class)` — equivalente sin instancia al `Gate::authorize('view', $tour)` del index anidado (ambos resuelven `isStaff` en `TourPolicy`). El 403 efectivo para no admin/operator lo impone el middleware `tenant_admin.only`.
- **Wayfinder no regenerado**: el helper TS de la nueva ruta queda pendiente para `montree-frontend-dev` (fuera de mi scope backend; no corrí `wayfinder:generate` para no tocar artefactos de frontend).
- **Home público "Próximas salidas" (2026-07-13, backend)**: nueva prop deferred `upcomingDepartures` en `HomePageController` (método privado siguiendo el patrón de las demás props, `Inertia::defer`). Query: `TourDate::openFuture()` (scope existente: `status = open` AND `starts_at > now`) + `whereHas('tour', active)` + `with('tour.coverImage')` + `orderBy('starts_at')` + `limit(6)`. Resource público NUEVO `UpcomingDepartureResource` (NO se reutilizó el admin `TourDateDetailResource` para no filtrar notes/guide/route/provider/capacity cruda/price_override al público). `effective_price = price_override ?? tour.base_price`; `cover_image_url` con el patrón http-passthrough/`Storage::disk('public')->url()`. Serialización de fechas `toIso8601String()` (consistente con el resto). Sin filtro extra por cupos: `open` con 0 disponibles no debería existir (full ya cubre lleno). 3 tests nuevos en `HomePageTest` (happy con orden asc + shape + keys prohibidas ausentes, edge con cancelled/full/closed/past/tour-inactivo excluidos + cap 6, tenant isolation). N+1: cubierto por eager load `tour.coverImage`.

## Changelog

- `2026-07-12` — Creación inicial.
- `2026-07-12` — Backend implementado (montree-backend-dev): excepciones de dominio, 4 actions de salida, modificación de `ChangeTourStatusAction`, form requests tenant-aware, controllers de salidas + logística, resources, rutas, 23 tests nuevos. Suite 340/340.
- `2026-07-13` — Backend listado global (montree-backend-dev): enum `TourDateDisplayStatus`, `TourDate::displayStatus()` + scope `withDisplayStatus()`, endpoint `GET /api/v1/admin/tour-dates` (`TourDateIndexController` + `TourDateIndexRequest`), `display_status`/`tour` en `TourDateDetailResource`, 5 tests (`TourDateGlobalIndexTest`). Filtro `TourDate` verde 21/21.
- `2026-07-13` — Agregadas tareas de listado global de salidas (endpoint `GET /api/v1/admin/tour-dates` + tests, enum `TourDateDisplayStatus`, `displayStatus()`, resource ampliado, page `Admin/Departures/Index.vue`, segunda entrada de sidebar "Tours"), marcadas en progreso. Razón: separación Productos vs Tours en admin + estado de presentación derivado.
- `2026-07-13` — Home público "Próximas salidas" (montree-backend-dev): prop deferred `upcomingDepartures` en `HomePageController`, resource público `UpcomingDepartureResource`, 3 tests en `HomePageTest`. `HomePageTest` verde 9/9. Frontend (sección Home) pendiente para `montree-frontend-dev`.
- `2026-07-13` — Home público "Próximas salidas" (montree-frontend-dev): tipo `UpcomingDeparture` (aditivo en `types/home.ts`), sección `Deferred` con skeleton entre "Tours destacados" y "Promociones" en `Home.vue`. Cards grid (1/2/3 cols) con imagen+fallback, Link al detalle (`tourShow`), fecha ES vía `formatTourDate` + "hasta …", badge destructivo "¡Últimos X cupos!" (≤3), precio `formatCurrency`, CTA "Reservar" → `bookingCreate({ query: { tour_date_id } })`. Array vacío → no renderiza sección. eslint OK, types:check OK (solo 6 errores preexistentes ajenos), build OK.
- `2026-07-13` — Spec sincronizada con el delta público (montree-spec-updater): documentada la sección "Próximas salidas" del home en `spec.md` (user story + criterios + componentes UI + out-of-scope), `contracts.md` (prop Inertia deferred `upcomingDepartures` + `UpcomingDepartureResource`, marcada como NO endpoint REST, con claves internas no expuestas) y `plan.md` (resource público nuevo + decisión de no crear página pública de salidas + baja de newsletter). Ítems del home marcados según estado real (backend + frontend listos, newsletter storefront removido). Razón: distinguir productos base del catálogo vs salidas concretas próximas y permitir reservar desde el home sin fragmentar navegación.
