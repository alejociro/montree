# administration-upgrades — Plan técnico

## 1. Resumen

Tres bloques secuenciales sobre la misma rama, con commit por bloque:
**B1 Base + branding + auth + limpieza**, **B2 Super admin (entrar, cobros, gráficas, migración a
Inertia)**, **B3 Productos ↔ rutas ↔ salidas (migración a Inertia)**. Cada bloque deja tests,
Pint, `types:check`, `npm run build` y Wayfinder regenerado.

## 2. Convenciones que se adoptan (tomadas de `microsites`)

Se incorporan a partir de este feature y se aplican a **todo archivo que se cree o se toque**:

1. **Actions con `execute()`** como único método público, inyectadas en el **método** del
   controller (no en el constructor). Composición de actions por constructor promovido.
2. **DataObjects `readonly`** en `app/Data/` construidos con `static fromRequest(FormRequest $r): self`
   cuando la action recibe más de 3 valores. El controller construye el DTO, la action no conoce HTTP.
3. **Query Builders por modelo** en `app/Models/Builders/<Modelo>Builder.php` (extienden
   `Illuminate\Database\Eloquent\Builder`, bindeados con `newEloquentBuilder()` en el modelo).
   Los filtros de listado van en `applyFilters(<Filters DTO>)` como cadena de `when()`; las
   agregaciones para gráficas también viven en el builder. Los `scopeXxx` existentes se
   migran al builder solo cuando se toca el modelo.
4. **Form Requests con accessors tipados** (`search(): ?string`, `from(): ?CarbonImmutable`) y
   `authorize()` real; para listados, un request base `IndexRequest` con `page/sort/direction`.
5. **Resources solo sobreescriben `data(Model): array`** cuando comparten envoltorio; para
   props Inertia se usan Resources con `toArray()` y `->resolve()`.
6. **Tests: una clase por caso de uso** (`EnterTenantTest`, `UpdateTenantCommissionTest`),
   nombres `test_<verbo>_<sujeto>_<contexto>`, data providers estáticos con claves descriptivas,
   factories siempre.
7. **Cero comentarios** salvo un WHY no obvio; sin código muerto; early returns; `final` en
   clases que no se extienden; `declare(strict_types=1)` (esto sí lo mantiene MONTREE).
8. **Front**: props Inertia + `useForm`/`<Form>`; `router.reload({ only })` para refrescar
   parciales; nada de `fetch`/`useApi` para mutar. Listados con paginación del servidor y
   filtros en query string (`preserveState`, `replace`).

## 3. Bloque B1 — Base, branding, auth, limpieza

### Storage / URLs
- `TenantConfigurationResource`: URLs con `Storage::disk('public')->url()`; `php artisan storage:link` en local.
- Unificar en `App\Actions\Tenant\StoreBrandingAssetsAction::execute(TenantConfiguration, BrandingAssetsData)`
  (logo/favicon/hero + flags `remove_*`), reusada por el admin del tenant y el super admin.
  `UpdateTenantBrandingAction` desaparece.

### Configuración del tenant (Inertia)
- `TenantConfigurationPagesController@update` (POST, multipart) con `Admin\Tenant\UpdateTenantConfigurationRequest`
  ampliado y `UpdateTenantConfigurationAction::execute(TenantConfiguration, TenantConfigurationData)`.
  Solo campos presentes (`$request->safe()->only(...)` sobre reglas `sometimes`).
- `Configuration.vue`: `useForm` multipart (`forceFormData`), campos de archivo con preview,
  bloque de contacto (`contact_info`) y quitar los defaults de color: `initialValues` toma el valor
  actual o `null`, y el payload solo incluye colores si cambiaron (`form.isDirty` por campo).
- Eliminar `Api\V1\Admin\TenantConfigurationController`, `Api\V1\Admin\TenantController` y rutas.

### Branding en la UI
- `TenantBrandedLogo`: `@error` → fallback al nombre; siempre `alt` con el nombre.
- `PublicLayout`: header con `TenantBrandedLogo`; footer con `bg-primary-ink`/`text-primary-ink-foreground`
  nuevos tokens derivados en `app.css` con `color-mix()` desde `--primary` (oscurecido) y
  `--primary-readable`. Sin tenant (plataforma) caen a los valores de marca actuales.
- `Home.vue`: logo en hero cuando exista; `hero_image_url` ya se usa.
- `SuperAdminLayout`: invoca `useTenantBranding()` (con tenant `null` reinicia a defaults)
  para que el host de plataforma nunca herede colores.

### Remember me
- `CrossHostLoginHandoff::issue(User, string $redirectTo, bool $remember = false)` guarda
  `remember` en el payload; `consume()` lo devuelve en un `HandoffPayload` readonly.
- `LoginResponse::crossHostHandoff()` pasa `$request->boolean('remember')`.
- `CrossHostLoginController`: `Auth::guard('web')->login($user, $payload->remember)`.
- Test `RememberMeSurvivesHandoffTest`: recaller presente en host destino cuando `remember=true`, ausente si no.

### Quitar exportación
- Borrar: `TourPassengerExportController`, `Guide\TourDatePassengerExportController`,
  `ExportPassengerManifestAction`, `RevenueReportController`, `ExportRevenueReportAction`,
  `ExportRevenueRequest`, `ExportRevenueButton.vue`, `exportUrl` en `usePassengerManifest.ts`,
  botón en `PassengerManifest.vue`, rutas, wayfinder generado, tests `PassengerExportTest`,
  `RevenueReportControllerTest`, aserciones CSV en `MedicalPermissionTest`.
- Permiso `reports.export`: quitar del seeder, `PermissionCatalog`, `permissions.ts`;
  actualizar `CATALOG_SIZE` y tests que lo enumeran. Migración que borra el permiso de
  `permissions` (idempotente).
- Claves de idioma huérfanas fuera de `lang/*.json` (`TranslationCatalogTest` valida sincronía).

## 4. Bloque B2 — Super admin

### Schema (`montree-db-architect` o el agente del bloque, en migraciones separadas)
- `tenants`: `commission_type` (string nullable, enum `CommissionType { Percentage, Fixed }`),
  `commission_value` decimal(12,2) nullable.
- `platform_charges`: `id`, `tenant_id` FK, `booking_id` FK unique, `payment_id` FK nullable,
  `base_amount` decimal(12,2), `commission_type`, `applied_value` decimal(12,2), `amount` decimal(12,2),
  `currency` char(3), `charged_at` timestamp, timestamps. Índices `(tenant_id, charged_at)`.
  Modelo landlord (sin `BelongsToTenant`: lo consulta la plataforma) con `Builder` propio.
- Eliminar el permiso `reports.export` (B1) — misma tanda de migraciones.

### Cargo por reserva
- `App\Actions\Platform\RecordPlatformChargeAction::execute(Booking): ?PlatformCharge` —
  idempotente por `booking_id` (unique + `firstOrCreate`), calcula con `PlatformChargeCalculator`
  (`app/Services/Platform/`, puro, con unit test), devuelve `null` si el tenant no cobra.
- Se invoca desde el punto donde la reserva pasa a `Confirmed` (`BookingSettlementService` /
  `ResolvePaymentAction`; también el pago manual `RegisterManualPaymentAction`). Un solo lugar:
  listener `RecordPlatformChargeOnBookingConfirmed` sobre un evento `BookingConfirmed`
  si ya existe; si no existe, se crea el evento y se dispara desde el servicio de liquidación.

### Entrar al tenant
- `EnterTenantController` (`__invoke`): valida `status === active` (`TenantException::notActive()`),
  `CrossHostLoginHandoff::issue($request->user(), '/admin/dashboard')`, `Inertia::location(url)`.
- `EnsureTenantAdmin`: si `isSuperAdmin()` → pasa sin membresía. `HandleInertiaRequests`:
  para super admin `auth.permissions` = catálogo completo (`PermissionCatalog::all()`), así el
  sidebar del tenant muestra todo. `RoleHomeRedirectController` no se toca (se entra por
  `/admin/dashboard` directo).
- `TenantTable.vue`: fila clicable → `<form method="post" target="_blank">` a `enter`, nombre → `Link` a `show`;
  se elimina la columna "Detalle".

### Migración de pantallas a Inertia
- `SuperAdminTenantPageController@index/show` con props completas (usa `TenantBuilder::applyFilters`,
  `PlatformMetricsAggregator` reescrito sobre builders y `PlatformCharge`).
- Controllers web de acción única en `App\Http\Controllers\SuperAdmin\`: `StoreTenantController`,
  `UpdateTenantStatusController`, `UpdateTenantPlanController`, `StoreTenantUserController`,
  `UpdateTenantConfigurationController`, `UpdateTenantCommissionController`, `EnterTenantController`,
  `PlatformChargePageController@index`. Reusan las actions y requests existentes (movidos de
  `Requests\SuperAdmin`, sin cambios de reglas salvo commission).
- Pages: `Dashboard.vue`, `Tenant/Index.vue`, `Tenant/Detail.vue`, `Tenant/Charges.vue` (nueva),
  diálogos `CreateTenantDialog`, `AddTenantUserDialog`, `TenantDetailPanel` con `useForm`.
- Eliminar `Api\V1\SuperAdmin\*`, rutas, wayfinder y mover tests a `tests/Feature/SuperAdmin/*`
  (un archivo por caso de uso).

### Gráficas
- Atoms SVG en `resources/js/components/atoms/charts/`: `BarChart.vue` (series simples y apiladas),
  `LineChart.vue`, `ChartLegend.vue`. Props tipadas (`MonthPoint[]`, `series`), responsive
  (`viewBox`), colores desde tokens (`--primary`, `--secondary`, `--chart-n` en `app.css`).
- Organisms: `TenantsPerMonthChart`, `RevenueByTenantChart`, `EarningsChart`, `TenantActivityCharts`.
- Datos: `PlatformChargeBuilder::monthlyTotals()`, `PaymentBuilder::monthlyRevenueByTenant()`,
  `TenantBuilder::registeredPerMonth()`; agrupación por mes portable (SQLite en tests):
  `strftime`/`DATE_FORMAT` se evita agrupando en PHP sobre `selectRaw` de `year/month` vía
  `whereBetween` + `->get()` de columnas mínimas, o `Query\MonthlySeries` helper puro con test.

## 5. Bloque B3 — Productos, rutas y salidas

### Schema
- `route_tour` pivot: `tour_id`, `route_id`, `is_default` bool, `position` int, timestamps;
  unique `(tour_id, route_id)`; índice parcial lógico "un default por tour" se garantiza en la action.
- `route_stops`: `latitude`, `longitude` decimal(10,7) nullable.

### Backend
- `Tour::routes()` BelongsToMany con pivot; `Route::tours()`.
- `SyncTourRoutesAction::execute(Tour, TourRoutesData)`; llamada desde `CreateTourAction`/`UpdateTourAction`.
- `StoreTourDateRequest`/`UpdateTourDateRequest`: regla `Rule::exists('route_tour','route_id')->where('tour_id', ...)`.
- `DepartureDefaults` DTO (`fromTour(Tour, TenantConfiguration)`) → prop `departureDefaults`.
- `RouteController@destroy` (web): bloquea si `tours()->exists()` o `tourDates()->exists()`.
- `TourDetailResolver`: `dates.route.stops`, `dates.guide`; `PublicTourResource.future_dates[].route|guide`.
- Controllers web: `TourPagesController` gana `store/update/destroy`; `TourStatusController`,
  `TourImageController`, `TourDatePagesController` (store/update/destroy), `CancelTourDateController`,
  `RestoreTourDateController`, `AssignGuideController`, `DeparturePagesController@index`,
  `LogisticsPagesController@index`, `RouteController`/`ProviderController`/`HotelController` web.
  Todos en `App\Http\Controllers\Admin\`. Se eliminan los `Api\V1\Admin` equivalentes.

### Frontend
- `TourForm.vue`: sección `routes` (multi-select con checkbox + radio "predeterminada"), en `Create`/`Edit`.
- `Show.vue`: card "Rutas del producto".
- `TourDateFormDialog.vue`: recibe `departureDefaults` + `tourRoutes`; precarga y selector limitado;
  `useForm` → `post/put` web. `useTourDepartures.ts` desaparece (props + `router.reload({only})`).
- `Departures/Index.vue`, `Logistics/Index.vue`, `LogisticsCrudPanel.vue`: props + `useForm`;
  paradas de ruta con lat/lng (reusa `TourPlaceField`).
- `TourDetail.vue`: `selectedDate.route` → `TourRouteMapSection` y `TourLogisticsCard` reciben
  las paradas de la ruta elegida (fallback a `tour.stops`); `TourBookingCard` muestra nombre de ruta.

## 6. Tests

Por bloque, una clase por caso de uso en `tests/Feature/<Dominio>/`. Mínimo por endpoint:
happy, failure, edge, tenant isolation. Unit: `PlatformChargeCalculatorTest`, `MonthlySeriesTest`.
Transversales a re-correr: `CurrentAdminAccessMatrixTest`, `TenantIsolationTest`,
`TranslationCatalogTest`, `TypeScriptEnumsAreInSyncTest`, `PermissionCatalogSeederTest`.

## 7. Decisiones

- **Sin librería de gráficas**: SVG propio. Razón: no cambiar dependencias sin aprobación; las 5 gráficas son barras/líneas simples.
- **Cargo al confirmar, no al pagar**: una reserva = un cargo, aunque tenga varios pagos (abonos).
- **Super admin entra como él mismo** (no suplanta a un admin del tenant): trazabilidad y sin tocar `tenant_user`.
- **Migración a Inertia acotada** a lo que este feature toca; el resto queda en `api-to-inertia-migration`.
- **Rutas del producto son las de logística** (catálogo compartido), no una entidad nueva: evita duplicar el editor de paradas.

## 8. Riesgos

| Riesgo | Mitigación |
|---|---|
| `lang/en.json` se pisa entre bloques | commit por bloque; nunca `git checkout/restore/stash` |
| Agrupar por mes en SQLite vs MySQL | helper en PHP con test, sin SQL dialect-specific |
| Super admin en host de tenant rompe middlewares/props | tests `EnterTenantTest` + `CurrentAdminAccessMatrixTest` |
| Wayfinder desactualizado tras borrar API | `php artisan wayfinder:generate --with-form` al cierre de cada bloque + `types:check` |
