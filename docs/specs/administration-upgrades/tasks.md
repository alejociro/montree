# administration-upgrades — Tasks

> Un bloque = un commit (mínimo). Al cerrar cada bloque: `wayfinder:generate --with-form`,
> `vendor/bin/pint --dirty --format agent`, `npm run lint && npm run types:check && npm run build`,
> `php artisan test --compact`. Nunca `git checkout/restore/stash`.

## B1 — Base, branding, auth, limpieza
- [ ] `storage:link` local; `TenantConfigurationResource` usa disco `public`
- [ ] `StoreBrandingAssetsAction` + `BrandingAssetsData` (logo/favicon/hero, remove_*), reusada por admin y super admin; borrar `UpdateTenantBrandingAction`
- [ ] `POST /admin/tenant/configuration` web (`TenantConfigurationPagesController@update`), request ampliado (`sometimes`, archivos, contact_info), `UpdateTenantConfigurationAction::execute(…, TenantConfigurationData)`
- [ ] Eliminar `Api\V1\Admin\TenantConfigurationController`, `Api\V1\Admin\TenantController`, rutas, wayfinder
- [ ] `Configuration.vue`: `useForm` multipart, uploads con preview, contacto, colores sin defaults ni envío si no cambian
- [ ] `TenantBrandedLogo` fallback `@error`; `PublicLayout` header con logo; footer con tokens derivados de `--primary`; `Home.vue` logo
- [ ] `SuperAdminLayout` invoca `useTenantBranding()`
- [ ] Remember me a través del handoff (`HandoffPayload`, `issue(..., $remember)`, `login($user, $remember)`)
- [ ] Quitar exportaciones (backend, frontend, permiso `reports.export`, migración, lang, tests)
- [ ] Tests: `TenantConfigurationUpdateTest` (happy, 422, archivo inválido, remove_logo, sin permiso, aislamiento), `BrandingUrlsTest`, `RememberMeSurvivesHandoffTest`, `PermissionCatalogSeederTest` actualizado
- [ ] Commit `feat(admin): branding uploads via Inertia, remember-me handoff, drop CSV exports`

## B2 — Super admin
- [ ] Migraciones: `tenants.commission_type/commission_value`, `platform_charges`
- [ ] `CommissionType` enum (+ `enums:typescript`), `PlatformCharge` model + factory + `PlatformChargeBuilder`, `TenantBuilder`, `PaymentBuilder`
- [ ] `PlatformChargeCalculator` (unit test) + `RecordPlatformChargeAction` idempotente + hook en confirmación de reserva (evento/listener)
- [ ] `EnsureTenantAdmin` deja pasar super admin; `HandleInertiaRequests` permisos completos para super admin
- [ ] `EnterTenantController` + `TenantException::notActive()` + fila clicable, sin columna "Detalle"
- [ ] `UpdateTenantCommissionController` + request; `PlatformChargePageController@index` + request + page `Tenant/Charges.vue`
- [ ] `SuperAdminTenantPageController@index/show` con props completas; controllers web para store/status/plan/users/configuration
- [ ] Dashboard: `PlatformMetricsAggregator` sobre builders + `PlatformCharge`; props `charts`; `MonthlySeries` helper (unit test)
- [ ] Atoms `charts/BarChart.vue`, `LineChart.vue`, `ChartLegend.vue`; organisms de gráficas; tokens `--chart-n`
- [ ] Pages y diálogos de super admin con `useForm`; borrar `Api\V1\SuperAdmin\*`, rutas, wayfinder, `useApi` en super admin
- [ ] Tests en `tests/Feature/SuperAdmin/`: `EnterTenantTest`, `UpdateTenantCommissionTest`, `PlatformChargeLedgerTest`, `RecordPlatformChargeTest`, `DashboardPageTest`, `TenantIndexPageTest`, `TenantShowPageTest` + migrar los existentes de `Api/V1/SuperAdmin`
- [ ] Commit `feat(super-admin): enter tenant, platform charges, dashboard charts, Inertia pages`

## B3 — Productos, rutas y salidas
- [ ] Migraciones: `route_tour`, `route_stops.latitude/longitude`
- [ ] `Tour::routes()`, `Route::tours()`, `SyncTourRoutesAction`, reglas `routes.*` en requests de tour
- [ ] `DepartureDefaults` DTO; `route_id` validado contra `route_tour`; `RouteController@destroy` bloquea por tours
- [ ] Controllers web admin (tours, status, imágenes, salidas, cancel/restore/guide, departures index, logistics index, routes/providers/hotels); borrar API equivalente y `useTourDepartures.ts`
- [ ] `TourDetailResolver` + `PublicTourResource.future_dates[].route|guide`
- [ ] `TourForm` sección rutas; `Show` card rutas; `TourDateFormDialog` con defaults y selector limitado (`useForm`)
- [ ] `Departures/Index`, `Logistics/Index`, `LogisticsCrudPanel`, `LogisticsRecordDialog` (lat/lng en paradas) con props + `useForm`
- [ ] `TourDetail.vue`: ruta/mapa/logística según salida elegida
- [ ] Tests: `TourRoutesSyncTest`, `TourDateRouteValidationTest`, `DepartureDefaultsTest`, `RouteInUseDeletionTest`, `PublicTourDepartureRouteTest`, pages tests de departures/logistics; migrar los `Api/V1/Admin/Tour*|TourDate*|Logistics*` a web
- [ ] Commit `feat(tours): product routes, departure inheritance, Inertia admin pages`

## Notas durante implementación
