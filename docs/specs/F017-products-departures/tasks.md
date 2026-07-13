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

- [ ] `TourDateException` y `LogisticsException` (códigos del contrato)
- [ ] `CreateTourDateAction` / `UpdateTourDateAction` / `CancelTourDateAction` / `DeleteTourDateAction`
- [ ] Modificar `ChangeTourStatusAction`: activar requiere solo ≥1 imagen (actualizar tests F003 afectados)
- [ ] Form Requests de salidas (store/update/cancel) con validación tenant-aware de guide/route/provider/hotels
- [ ] Form Requests de routes/providers/hotels (store/update × 3)
- [ ] `TourDateController` (index/store/update/destroy) + `CancelTourDateController` invokable
- [ ] `RouteController`, `ProviderController`, `HotelController` (index/store/update/destroy, delete con check de uso → 409)
- [ ] `TourDateDetailResource`, `RouteResource`, `ProviderResource`, `HotelResource` (con `tour_dates_count`)
- [ ] Rutas en `routes/api.php` (grupo admin existente)
- [ ] Tests: salidas (happy/failure/edge/tenant-isolation por endpoint, según plan §4)
- [ ] Tests: logística CRUD + `RESOURCE_IN_USE`
- [ ] Test: activar producto sin fechas pasa; catálogo público lo muestra con `has_future_dates: false`
- [ ] `php artisan wayfinder:generate`
- [ ] Pint + suite verde

## Frontend (`montree-frontend-dev`)

- [ ] Types en `resources/js/types/logistics.ts`
- [ ] `TourDatesPanel.vue` (tabla próximas/pasadas/canceladas + acciones)
- [ ] `TourDateFormDialog.vue` (crear/editar con condiciones: guía, ruta, proveedor, hoteles)
- [ ] Integrar panel de salidas en `Admin/Tour/Edit.vue`
- [ ] `LogisticsCrudPanel.vue` + page `Admin/Logistics/Index.vue` (tabs Rutas/Proveedores/Hoteles)
- [ ] Entrada "Logística" en sidebar admin + labels "Productos"/"Salidas" en el panel
- [ ] `TourDetail.vue` público: card "Sin fechas disponibles" cuando `dates` vacío
- [ ] Llamadas API vía `useApi()` + Wayfinder (cero URLs hardcodeadas)
- [ ] Estados loading/error/empty en panel y dialogs
- [ ] `npm run types:check` + lint + build
- [ ] Probar en navegador: crear salida con condiciones + editar + cancelar + logística CRUD

## Review (`montree-reviewer`)

- [ ] Tests pasan (suite completa)
- [ ] Pint / types / ESLint pasan
- [ ] Spec F017 cubierta 100%
- [ ] Constitución respetada (controllers ≤10 líneas, sin validación inline, N+1 check en index de salidas)
- [ ] Sin código muerto

## Changelog

- `2026-07-12` — Creación inicial.
