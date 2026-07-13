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

## Changelog

- `2026-07-12` — Creación inicial.
- `2026-07-12` — Backend implementado (montree-backend-dev): excepciones de dominio, 4 actions de salida, modificación de `ChangeTourStatusAction`, form requests tenant-aware, controllers de salidas + logística, resources, rutas, 23 tests nuevos. Suite 340/340.
