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
- [x] Migraciones: `route_tour`, `route_stops.latitude/longitude`
- [x] `Tour::routes()`, `Route::tours()`, `SyncTourRoutesAction`, reglas `routes.*` en requests de tour
- [x] `DepartureDefaults` DTO; `route_id` validado contra `route_tour`; `RouteController@destroy` bloquea por tours
- [x] Controllers web admin (tours, status, imágenes, salidas, cancel/restore/guide, departures index, logistics index, routes/providers/hotels); borrar API equivalente y `useTourDepartures.ts`
- [x] `TourDetailResolver` + `PublicTourResource.future_dates[].route|guide`
- [x] `TourForm` sección rutas; `Show` card rutas; `TourDateFormDialog` con defaults y selector limitado (`useForm`)
- [x] `Departures/Index`, `Logistics/Index`, `LogisticsCrudPanel`, `LogisticsRecordDialog` (lat/lng en paradas) con props + `useForm`
- [x] `TourDetail.vue`: ruta/mapa/logística según salida elegida
- [x] Tests: `TourRoutesSyncTest`, `TourDateRouteValidationTest`, `DepartureDefaultsTest`, `RouteInUseDeletionTest`, `PublicTourDepartureRouteTest`, pages tests de departures/logistics; migrar los `Api/V1/Admin/Tour*|TourDate*|Logistics*` a web
- [x] Commit `feat(tours): product routes, departure inheritance, Inertia admin pages`

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

### B3 — 2026-09-15

- `route_tour` no lleva `tenant_id`: los dos extremos ya son tenant-scoped y una
  tercera copia del dato solo añade un sitio donde desalinearse. FK a `tours` en
  cascada (borrar el producto suelta sus rutas) y a `routes` con `restrict` (una
  ruta en uso se borra desde logística, con su mensaje, no por efecto colateral).
- «Un solo `is_default` por tour» se garantiza en `SyncTourRoutesAction` y se
  rechaza en el request: ninguna de las dos bases soporta el índice parcial de
  forma portable. **Sin marca explícita el producto queda sin predeterminada**;
  no se elige una por descarte, que es lo que hace posible el edge case «ruta
  predeterminada eliminada → la próxima salida se crea sin ruta preseleccionada».
- `departureDefaults` viaja **embebido por producto** en el tablero de salidas
  (`tours[].departure_defaults` + `tours[].routes`) y como prop suelta solo en
  `Admin/Tour/Edit`. Son seis escalares más la lista de rutas —que el selector
  necesita igual—, así que un `router.reload({ only })` por apertura del diálogo
  sería un viaje por nada. `DeparturesIndexPageTest` fija el conteo de consultas.
- Las reglas de negocio dejan de responder 409/403 JSON: sobre una visita Inertia
  eso es una página de error que se lleva puesto el formulario. Ahora vuelven con
  `back()->withErrors()` bajo las claves `plan`, `status`, `tour`, `tour_date`,
  `route`, `provider` y `hotel`. `App\Exceptions\LogisticsException` se elimina.
- `Tour`, `Route`, `Provider` y `Hotel` ganan query builder propio
  (`applyFilters`/`matching`), siguiendo la convención del plan §2.3. El buscador
  de los tres catálogos de logística se mudó del controller al builder.
- `GET /admin/logistics` sirve los tres catálogos en la misma visita, con
  paginadores independientes (`routes_page`/`providers_page`/`hotels_page`): la
  pestaña necesita el conteo de las tres bandejas para ser útil.
- `Admin/Tour/Index` pasa a paginación y filtros del servidor (9 por página). El
  selector de orden de la barra combina columna y dirección en un solo valor; la
  página lo traduce a `sort`/`direction` en los dos sentidos.
- Deuda del B2 saldada: la clave del global scope de tenant vive en
  `App\Models\Tenant::SCOPE`. **No pudo ir en el trait como pedía la tarea**: PHP
  no deja leer una constante de trait por el nombre del trait
  (`BelongsToTenant::SCOPE` es un fatal). `TenantScopeConstantTest` prueba que con
  un tenant actual la constante sí devuelve filas de otros tenants y que pasar la
  clase no levanta nada.
- `useTourDepartures.ts` desaparece; `TourUpcomingDatesList` y
  `TourDeparturesTable` reciben las salidas por props. `Admin/Departures/Index.vue`
  bajó de 1012 a ~636 líneas partiéndose en `DepartureBoardTable` y
  `DepartureBoardFilters`, y ahora también **crea** salidas (antes solo editaba).
- `TourDetail.vue`: una ruta con paradas pero sin coordenadas no cae al producto
  —la salida manda— sino que degrada a lista sin mapa (`TourRouteStopSummary`).
  `TourRouteMapSection` lleva `:key` por ruta porque `useTourRouteMap` no observa
  `stops`; el `watch` de fondo queda pendiente.
- `lang/en.json`: +21 claves nuevas, -13 huérfanas; `lang/es.json` +3 identidades
  de plural. Verificado contra un worktree de `0122050` que `TranslationCatalogTest`
  queda exactamente en las mismas 188 faltantes y 122 huérfanas del landing,
  preexistentes a esta rama.
- `TeamRequestMessagesTest` (×3) sigue rojo por `APP_LOCALE=en` en el `.env` local.
  Preexistente y de entorno: no se tocó.
- Migración de tests API → web (B3): se borró `tests/Feature/Api/V1/Admin/{Tour,TourDate,Logistics}`
  y su contenido se repartió en una clase por caso de uso bajo `tests/Feature/Tours`,
  `tests/Feature/TourDates` y `tests/Feature/Logistics`. Tres casos cambiaron de
  semántica al cambiar el contrato: **(1)** «un `sort` desconocido cae al orden por
  defecto» pasó a `test_index_rejects_an_unknown_sort` —`TourIndexRequest` valida la
  lista y el listado ya no puede degradarse en silencio—; **(2)** el índice por
  producto (`GET tours/{tour}/dates?scope=`) dejó de existir: las dos bandejas se
  verifican ahora como props (`Admin/Tour/Edit` trae todas las salidas,
  `Admin/Tour/Show` solo las futuras); **(3)** `test_status_rejection_body_carries_error_code_at_top_level`
  se volvió `test_a_rejected_transition_returns_to_the_same_page_with_the_reason`:
  no hay `error_code` que comprobar, el motivo viaja en `errors.status`.
- Los tests de rol cambiaron de actor donde el permiso ya no correspondía:
  `operator` **sí** tiene `tours.create/update/images.manage`, así que los casos de
  «no puede» usan `sales`. `operator` sigue sin `tours.delete` ni `departures.delete`.
- `TourIndexQueryCountTest` compara ahora 3 vs 9 productos (la página es de 9, ya no
  hay `per_page`); el invariante sigue siendo el mismo número de consultas, y la cota
  absoluta subió de 10 a 20 porque la página también trae categorías y KPIs.

## Correcciones post-review

Hallazgos del review de la rama sobre `a42441b`. Uno por punto, con el test que lo cubre.

### P0-1 — El cargo de plataforma corría dentro de la transacción del pago
`RecordPlatformChargeOnBookingConfirmed` pasa a `implements ShouldQueue` con
`$afterCommit = true` y `$tries = 3`. `BookingSettlementService` se ejecuta con la
reserva bloqueada dentro del `DB::transaction()` de quien liquida, así que un fallo
del cargo revertía el pago entero: el dinero ya había entrado y la reserva se
quedaba en `pending_payment`.

`SyncQueue::push()` honra `afterCommit` (delega en `db.transactions`) y
`RefreshDatabase` registra su propio `DatabaseTransactionsManager`, así que el
diferido también aplica con `QUEUE_CONNECTION=sync` en la suite: no hizo falta
`DB::afterCommit()` manual.

- `PlatformChargeFailureDoesNotBlockSettlementTest` (nuevo): rompe el cargo por
  donde se rompe de verdad —se elimina la tabla `platform_charges`, el `INSERT`
  revienta— y verifica que el pago queda `completed` y la reserva `confirmed`.
  **Verificado que falla sin el fix**: con el listener síncrono la reserva vuelve a
  `pending_payment`.
- `RecordPlatformChargeTest::test_a_gateway_payment_records_the_charge_too` (nuevo):
  el caso pasarela, que faltaba —solo había pago manual—, vía la notificación de
  PlacetoPay con `FakeCheckout`.

### P1-1 — Regla "recurso en uso" fuera de los controllers
Nuevas `DeleteRouteAction`, `DeleteHotelAction`, `DeleteProviderAction` en
`app/Actions/Logistics/`, que lanzan `LogisticsRecordInUseException` (409, con
constructores nombrados que llevan el detalle: qué productos o cuántas salidas).
Los `destroy` quedan en `try/catch → back()->withErrors()`, el mismo patrón de
`TourPagesController::destroy` con `TourHasActiveBookingsException`.
Cubren: `RouteInUseDeletionTest`, `DeleteRouteTest`, `DeleteHotelTest`,
`DeleteProviderTest` (sin cambios: la redacción de los mensajes se conservó literal).

### P1-2 — Métodos de controller sobre 10 líneas
Props extraídas a un método privado `props(...)` (y `editProps`/`paginated` donde
hacía falta) en `DeparturePagesController`, `LogisticsPagesController`,
`TenantConfigurationPagesController`, `PlatformChargePageController` y
`TourPagesController`. Los tres `destroy` de logística se resolvieron con P1-1.
Sin cambio de contrato: los tests de props existentes quedan verdes.

### P1-3 — `Gate::authorize('logistics.manage')` duplicado
Eliminado de `RouteController`, `HotelController` y `ProviderController`. Verificado
en `routes/web.php:144` que los nueve endpoints de logística viven dentro del grupo
`Route::middleware('can:logistics.manage')`. Cubren los casos de rol ya existentes
(`test_an_operator_without_the_logistics_permission_cannot_delete`,
`test_a_sales_member_cannot_delete_a_route`).

### P1-4 + B2 — Fila del tenant clicable
`TenantTable.vue`: la fila envía el `<form method="post" target="_blank">` de
"Entrar" con `requestSubmit()` (respeta el `target`), solo si `can_enter`;
`cursor-pointer` y `title` condicionados, `aria-disabled` cuando no se puede entrar.
El `<Link>` del nombre y el botón llevan `@click.stop`. Sin test automatizado: no
hay runner de JS en el proyecto y no se agregan dependencias.

### B1 — Panel del tenant sin menú para el super admin
**La causa real no era el backend.** `AuthUserResource` ya le manda el catálogo
completo al super admin y `HandleInertiaRequests` ya lo expone en `auth.permissions`
(`InertiaAuthUserPropTest` lo probaba). El bug estaba entero en
`resources/js/config/navigation.ts:447`: el filtro de secciones era un XOR **por rol**

```ts
.filter((section) => (section.superAdminOnly === true) === isSuperAdmin)
```

así que un super admin se quedaba solo con la sección "Plataforma" y el grupo
"Administración" se caía **antes** de evaluar ningún permiso. El mismo flag hacía
que `resolveHomeUrl()` devolviera `PLATFORM_HOME` (`/super-admin/dashboard`,
host-relativo y atado a `Route::domain(platform_host)`) como destino del brand.

La autorización del backend es **por host**, no por rol: `EnsureTenantAdmin:40` deja
pasar al super admin en `admin/*` cuando hay tenant resuelto. `NavContext` no tenía
cómo expresarlo. Se le agrega `hasTenant` (de `page.props.tenant !== null`) y un
`isOnPlatform()` que reemplaza a `isSuperAdmin` en el filtro de secciones, en
`resolveHomeUrl()` y en `resolveWorkspaceLink()`. Comentarios obsoletos corregidos
(decían que `admin/*` le responde 403 al super admin; ya no).

- `InertiaAuthUserPropTest::test_super_admin_props_carry_the_whole_permission_catalog`
  reforzado: ahora asevera también `auth.permissions` (la prop que el sidebar lee de
  verdad) y que el host resuelve el tenant.
- `test_authenticated_user_inertia_props_include_the_permissions_of_their_roles` ya
  cubría que un miembro normal recibe solo los suyos (13, sin `team.view`).

### B3 — "Editar salida" abría vacío la primera vez
`TourDateFormDialog.vue`: el `watch(() => props.open, …)` pasa a `{ immediate: true }`.
El tablero monta el diálogo con `v-if="selectedTour"` y `open` ya en `true`, así que
en esa primera apertura el watch no corría nunca y guardar borraba guía, ruta,
proveedor, hoteles y notas.

### P2-1 — `CrossHostLoginHandoff::consume()` no atómico
`Cache::get()` + `Cache::forget()` → `Cache::pull()`. Cubre `RememberMeSurvivesHandoffTest`
y los tests de un solo uso del handoff.

### P2-3 — `PlatformChargePageController::index` armaba la query tres veces
El builder se resuelve una vez en `props()`. Se le pasa un `clone()` a `paginated()`
porque paginar le pega `limit`/`offset` al builder y los totales son sobre toda la
selección; `totalAmount()` y `count()` no mutan.

### P2-5 — Salidas con `route_id` huérfano
`SyncTourRoutesAction` pone `route_id = null` en las salidas **futuras y no
canceladas** del producto cuya ruta dejó de estar asociada. Las pasadas y las
canceladas conservan la ruta: ahí el dato es histórico. Edge case agregado a
`spec.md` con su entrada de Changelog.

- `TourRoutesSyncTest::test_detaching_a_route_clears_it_only_from_future_open_departures`
- `TourRoutesSyncTest::test_detaching_every_route_clears_the_future_departures`

### P2-7 — `can_enter` duplicado
Nuevo `Tenant::canBeEntered(): bool`, usado por `SuperAdminTenantResource` y
`EnterTenantController`. Cubren `EnterTenantTest` y los tests del listado.

## B4 — Moneda única por tenant + rutas dentro del producto (2026-09-16)
- [ ] Migración de tours: sin cambio de columna; quitar `currency` del form/request de producto; actions fijan la del tenant; factory usa la del tenant actual
- [ ] `StoreTenantRequest` + `CreateTenantAction` con `currency`; `CreateTenantDialog.vue` con selector de moneda
- [ ] Front: `formatCurrency` siempre con `tenantConfiguration.currency` (catálogo, home, detalle, booking, salidas, transacciones, dashboard, logística); sin fallback `'USD'`
- [ ] Proveedores/hoteles: moneda por defecto = la del tenant
- [ ] Plataforma: totales y gráficas agrupados por moneda; eliminar `montree.platform_currency`
- [ ] Reescribir `2026_09_15_200000_create_route_tour_table` → `add_tour_to_routes_table` (`tour_id`, `is_default`); `Route::tour()`, `Tour::routes()` HasMany, `Tour::defaultRoute()`
- [ ] `SaveRouteAction` recibe `Tour`; `SetDefaultRouteAction`; `DeleteRouteAction` bloquea por salidas futuras no canceladas y desasocia pasadas/canceladas
- [ ] Controllers web `TourRouteController` (store/update/destroy/default) bajo `can:tours.update`; borrar rutas y UI de rutas de Logística (`LogisticsCrudPanel` solo providers/hotels), `TourRoutesSelector.vue`, `SyncTourRoutesAction`
- [ ] `Admin/Tour/Edit.vue`: panel "Rutas del producto" con diálogo crear/editar (reusar el schema de ruta de `logistics-form.ts` + editor de paradas con coordenadas), marcar predeterminada, eliminar; `Create.vue` muestra aviso; `Show.vue` lista rutas
- [ ] `TourDateFormDialog` y salidas: `route_id` validado contra `routes.tour_id`
- [ ] Seeder demo: rutas colgando de productos; `php artisan migrate:fresh --seed` en local
- [ ] Tests: `TourCurrencyFollowsTenantTest`, `TenantCurrencyConfigurationTest` (admin y super admin), `PlatformTotalsByCurrencyTest`, `TourRouteCrudTest` (happy/422/aislamiento/otro producto), `DefaultRouteTest`, `DeleteRouteWithDeparturesTest`; migrar `TourRoutesSyncTest`, `RouteInUseDeletionTest`, `DeleteRouteTest`, `LogisticsIndexPageTest`, `TourDateRouteValidationTest`
- [ ] Commit `feat(admin): tenant-wide currency and product-owned routes`
