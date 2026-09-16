# administration-upgrades — Tasks

> Un bloque = un commit (mínimo). Al cerrar cada bloque: `wayfinder:generate --with-form`,
> `vendor/bin/pint --dirty --format agent`, `npm run lint && npm run types:check && npm run build`,
> `php artisan test --compact`. Nunca `git checkout/restore/stash`.

## B1 — Base, branding, auth, limpieza
- [x] `storage:link` local; `TenantConfigurationResource` usa disco `public`
- [x] `StoreBrandingAssetsAction` + `BrandingAssetsData` (logo/favicon/hero, remove_*), reusada por admin y super admin; borrar `UpdateTenantBrandingAction`
- [x] `POST /admin/tenant/configuration` web (`TenantConfigurationPagesController@update`), request ampliado (`sometimes`, archivos, contact_info), `UpdateTenantConfigurationAction::execute(…, TenantConfigurationData)`
- [x] Eliminar `Api\V1\Admin\TenantConfigurationController`, `Api\V1\Admin\TenantController`, rutas, wayfinder
- [x] `Configuration.vue`: `useForm` multipart, uploads con preview, contacto, colores sin defaults ni envío si no cambian
- [x] `TenantBrandedLogo` fallback `@error`; `PublicLayout` header con logo; footer con tokens derivados de `--primary`; `Home.vue` logo
- [x] `SuperAdminLayout` invoca `useTenantBranding()`
- [x] Remember me a través del handoff (`HandoffPayload`, `issue(..., $remember)`, `login($user, $remember)`)
- [x] Quitar exportaciones (backend, frontend, permiso `reports.export`, migración, lang, tests)
- [x] Tests: `TenantConfigurationUpdateTest` (happy, 422, archivo inválido, remove_logo, sin permiso, aislamiento), `BrandingUrlsTest`, `RememberMeSurvivesHandoffTest`, `PermissionCatalogSeederTest` actualizado
- [x] Commit `feat(admin): branding uploads via Inertia, remember-me handoff, drop CSV exports`

## B2 — Super admin
- [x] Migraciones: `tenants.commission_type/commission_value`, `platform_charges`
- [x] `CommissionType` enum (+ `enums:typescript`), `PlatformCharge` model + factory + `PlatformChargeBuilder`, `TenantBuilder`, `PaymentBuilder`
- [x] `PlatformChargeCalculator` (unit test) + `RecordPlatformChargeAction` idempotente + hook en confirmación de reserva (evento/listener)
- [x] `EnsureTenantAdmin` deja pasar super admin; `HandleInertiaRequests` permisos completos para super admin
- [x] `EnterTenantController` + `TenantException::notActive()` + fila clicable, sin columna "Detalle"
- [x] `UpdateTenantCommissionController` + request; `PlatformChargePageController@index` + request + page `Tenant/Charges.vue`
- [x] `SuperAdminTenantPageController@index/show` con props completas; controllers web para store/status/plan/users/configuration
- [x] Dashboard: `PlatformMetricsAggregator` sobre builders + `PlatformCharge`; props `charts`; `MonthlySeries` helper (unit test)
- [x] Atoms `charts/BarChart.vue`, `LineChart.vue`, `ChartLegend.vue`; organisms de gráficas; tokens `--chart-n`
- [x] Pages y diálogos de super admin con `useForm`; borrar `Api\V1\SuperAdmin\*`, rutas, wayfinder, `useApi` en super admin
- [x] Tests en `tests/Feature/SuperAdmin/`: `EnterTenantTest`, `UpdateTenantCommissionTest`, `PlatformChargeLedgerTest`, `RecordPlatformChargeTest`, `DashboardPageTest`, `TenantIndexPageTest`, `TenantShowPageTest` + migrar los existentes de `Api/V1/SuperAdmin`
- [x] Commit `feat(super-admin): enter tenant, platform charges, dashboard charts, Inertia pages`

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

### B1 — 2026-09-15

- `php artisan storage:link` corrido en local; `TenantConfigurationResource` resuelve las
  tres URLs con `Storage::disk('public')`.
- `UpdateTenantConfigurationAction::execute(TenantConfiguration, TenantConfigurationData)`
  toma el tenant de `$configuration->tenant` para el límite de plan del `custom_css`.
  `TenantConfigurationData` transporta un mapa disperso (`array<string, mixed>`) porque las
  reglas son `sometimes`: una propiedad por columna convertiría «ausente» en «null».
- `UpdateTenantAction` y `Admin\Tenant\UpdateTenantRequest` se borran con
  `Api\V1\Admin\TenantController`: nadie más los usaba.
- `CurrentAdminAccessMatrixTest` pierde el proveedor `tenantSettingsRoutes`: la matriz
  resuelve rutas con el prefijo `api.v1.admin.` y esos dos endpoints ya no son API. El
  límite de permiso lo cubre `TenantConfigurationUpdateTest`.
- El permiso `reports.export` baja `CATALOG_SIZE` de 40 a 39; la migración
  `2026_09_15_090000_remove_reports_export_permission` borra la fila y sus pivotes, y es
  idempotente (sale temprano si el permiso ya no está).
- `lang/en.json`: +11 claves nuevas, -23 huérfanas por la exportación. Verificado contra un
  worktree de HEAD que el feature no agrega deriva (`TranslationCatalogTest` sigue rojo por
  188 claves y 122 huérfanas del landing, todas preexistentes a esta rama).
- `TeamRequestMessagesTest` falla en local porque el `.env` tiene `APP_LOCALE=en` (el
  proyecto asume `es`). Preexistente y de entorno: no se tocó.
- Tokens nuevos de pie de página en `app.css`: `--primary-ink`, `--primary-ink-foreground`
  y `--primary-ink-line`, mezclados con `color-mix()` desde `--primary` y `--brand-ink`.
  Sin tenant, `--primary` es el verde de marca y el pie queda como estaba.
- El pie deja de pintar los datos de contacto de ejemplo («Calle 123, Siempre Viva»): ahora
  que el admin los edita, cada línea aparece solo si tiene valor. Esos tres literales
  salen también de `NON_COPY_LITERALS` en `TranslationCatalogTest`.

### B2 — 2026-09-15

- El cargo se engancha en `BookingSettlementService::apply()`, que es el único
  punto donde una reserva pasa a `Confirmed` (lo usan `ResolvePaymentAction` y
  `RegisterManualPaymentAction`). Dispara `BookingConfirmed`, que escucha
  `RecordPlatformChargeOnBookingConfirmed`. Se eligió evento y no llamada directa
  porque el servicio corre dentro de la transacción con `lockForUpdate()` del
  caller: el listener escribe el cargo en esa misma transacción, así que un
  rollback del pago no deja un cargo huérfano.
- `withoutGlobalScope(BelongsToTenant::class)` es decorativo en todo el código:
  el scope se registra con la clave `'tenant'`. No hace falta igual, porque el
  panel de plataforma corre sin `Tenant::current()`. Se conservó el idiom para no
  mezclar un cambio transversal con este bloque.
- `statsForTenant()` (una consulta por tenant, ×4) se reemplaza por
  `statsForTenants(array $ids)` con tres consultas agrupadas: el listado paginado
  de 15 filas pasaba de 60 consultas a 3, más el `withCount(['users','tours'])`.
- `MonthlySeries` vive en `app/Support/` (no `app/Query/` como decía el plan §4):
  es un helper puro sin nada de Eloquent y `app/Support` ya existía.
- Tokens `--chart-1..8` en `app.css`: los cinco anteriores estaban clavados en la
  paleta de marca y no los consumía nadie, así que se redefinen derivados de
  `--primary`/`--secondary` con `color-mix()`, más tres nuevos. La identidad de
  serie nunca depende solo del color: toda gráfica lleva `role="img"` con
  `aria-label`, tabla `sr-only` con los valores y leyenda cuando hay 2+ series.
- La moneda del agregado de plataforma es `config('montree.platform_currency')`
  (nueva, `USD` por defecto). Los cargos se guardan en la moneda de cada agencia
  y no se convierten.
- El panel del super admin recupera redes sociales y contacto reusando
  `SocialLinksEditor` y `ContactInfoEditor` en vez de duplicar los campos.
- `lang/en.json`: +54 claves nuevas, -45 huérfanas (el listado de agencias dejó de
  hablar de «tenants»). Verificado contra un worktree de HEAD que
  `TranslationCatalogTest` sigue exactamente en 188 claves faltantes y 122
  huérfanas, todas del landing y preexistentes a esta rama.
- `TeamRequestMessagesTest` (×3) sigue rojo por `APP_LOCALE=en` en el `.env` local.
  Preexistente y de entorno: no se tocó.
