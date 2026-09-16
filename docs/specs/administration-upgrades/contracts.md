# administration-upgrades — Contratos

> Sin API JSON. Cada bloque define la **ruta web**, el **Form Request** y las **props Inertia** o el
> **redirect + flash**. Los shapes de props son los que consumen las pages. Mutaciones con
> `useForm` / `<Form>` de Inertia; nunca `useApi`.

Convenciones de respuesta de mutación:
- Éxito → `redirect()->route(...)->with('success', <mensaje traducido>)`
- Validación → 422 estándar de Inertia (errores en `form.errors`)
- Regla de negocio → `back()->withErrors(['<campo>' => <mensaje>])` o 409/403 con página de error según el caso indicado.

---

## 1. Super admin — tenants

### GET /super-admin/tenants  `super-admin.tenants.index`
Request: `TenantIndexRequest` (`search?`, `status?`, `plan?`, `page?`, `sort?` in `name|created_at`, `direction?`).

Props:
```ts
{
  tenants: Paginated<TenantRow>,
  filters: { search: string|null, status: TenantStatus|null, plan: TenantPlan|null, sort: string, direction: 'asc'|'desc' },
}
TenantRow = {
  id, name, slug, domain: string|null, status, plan, created_at,
  can_enter: boolean,                      // status === 'active'
  commission: { type: 'percentage'|'fixed'|null, value: string|null, currency: string },
  stats: { users_count, tours_count, bookings_count_30d, revenue_30d: string, charges_30d: string },
}
```

### POST /super-admin/tenants  `super-admin.tenants.store`
Request: `StoreTenantRequest` (existente, movido a web). Redirect a `tenants.show` con flash.

### POST /super-admin/tenants/{tenant}/enter  `super-admin.tenants.enter`
Sin body. Acción: emite handoff para el super admin autenticado con `redirect_to=/admin/dashboard`
y responde `redirect()->away("https://{slug}.{platform_host}/auth/handoff/{token}")`.
El front usa un `<form method="post" target="_blank">` con `@csrf` (input hidden con el token
XSRF) en la fila, de modo que el panel del tenant abre en una pestaña nueva. Clic en la fila =
submit de ese form; el nombre del tenant es un `<Link>` al detalle y detiene la propagación.
Errores: 409 `TENANT_NOT_ACTIVE` si status ≠ active (flash error). 403 no super admin.

### GET /super-admin/tenants/{tenant}  `super-admin.tenants.show`
Props:
```ts
{
  tenant: TenantDetail,   // shape actual de TenantResource + commission + stats
  configuration: TenantConfigurationDetail,  // shape actual del super admin
  charges_summary: { total_amount: string, total_count: number, currency: string },
  monthly: { bookings: MonthPoint[], charges: MonthPoint[] },   // 12 meses
  roles: string[],        // TenantRoleCatalog::STAFF_ROLES
}
MonthPoint = { month: 'YYYY-MM', label: string, value: number|string }
```

### PATCH /super-admin/tenants/{tenant}/status · /plan  (existentes → web, mismo Form Request)
### POST  /super-admin/tenants/{tenant}/users (existente → web)
### POST  /super-admin/tenants/{tenant}/configuration (existente → web, multipart)
Todos redirigen a `tenants.show` con flash.

### PUT /super-admin/tenants/{tenant}/commission  `super-admin.tenants.commission.update`
Request: `UpdateTenantCommissionRequest`
| Campo | Reglas |
|---|---|
| `type` | nullable, in:percentage,fixed |
| `value` | required_with:type, numeric, min:0; si `percentage` → max:100 |
`type = null` desactiva el cobro. Redirect a `tenants.show` con flash.

### GET /super-admin/tenants/{tenant}/charges  `super-admin.tenants.charges.index`
Request: `PlatformChargeIndexRequest` (`from?`, `to?` dates, `page?`).
Props:
```ts
{
  tenant: { id, name, slug },
  charges: Paginated<PlatformChargeRow>,
  filters: { from: string|null, to: string|null },
  totals: { amount: string, count: number, currency: string },
}
PlatformChargeRow = {
  id, charged_at, booking: { id, booking_number, total_amount, currency },
  base_amount: string, type: 'percentage'|'fixed', applied_value: string,
  amount: string, currency: string,
}
```

## 2. Super admin — dashboard

### GET /super-admin/dashboard  `super-admin.dashboard`
Props (todo calculado en servidor, sin request posterior):
```ts
{
  totals: { tenants, active_tenants, users, bookings_this_month, revenue_this_month: string, earnings_this_month: string },
  growth: { tenants_new_this_month, bookings_growth_pct: number|null },
  plan_distribution: { basic, professional, enterprise },
  charts: {
    tenants_per_month: { points: MonthPoint[], average: number },            // 12 meses
    revenue_per_tenant: { months: string[], series: { tenant: string, values: string[] }[] }, // 6 meses, top 8
    earnings_per_month: { points: MonthPoint[], total: string },            // 12 meses
  },
}
```

## 3. Admin tenant — configuración

### GET /admin/tenant/configuration (existente) — props sin cambio + `configuration.logo_url|favicon_url|hero_image_url` correctos.
### POST /admin/tenant/configuration  `admin.tenant.configuration.update`  (multipart, `_method` no necesario: es POST)
Request: `UpdateTenantConfigurationRequest` (el del admin, ampliado)
| Campo | Reglas |
|---|---|
| `primary_color`, `secondary_color` | sometimes, hex `#rrggbb` |
| `logo` | sometimes, image, mimes:png,jpg,jpeg,svg,webp, max:2048 |
| `favicon` | sometimes, image, mimes:png,ico,svg, max:1024 |
| `hero_image` | sometimes, image, mimes:jpg,jpeg,png,webp, max:5120 |
| `remove_logo`, `remove_hero_image` | sometimes, boolean |
| `contact_info.address/email/phone/whatsapp` | sometimes, nullable, string |
| resto | como hoy |
Solo se persisten los campos presentes (`sometimes`). Redirect a la misma página con flash.
Se elimina `PUT /api/v1/admin/tenant/configuration` y `PUT /api/v1/admin/tenant`.

## 4. Auth — handoff

`CrossHostLoginHandoff::issue(User $user, string $redirectTo, bool $remember = false)`; el payload
del token incluye `remember`; `CrossHostLoginController` hace `Auth::login($user, $remember)`.

## 5. Admin tenant — productos, rutas y salidas

### Producto (`admin.tours.*`)
`StoreTourRequest` / `UpdateTourRequest` suman:
| Campo | Reglas |
|---|---|
| `routes` | sometimes, array, max:20 |
| `routes.*.id` | required, distinct, exists:routes,id (mismo tenant) |
| `routes.*.is_default` | boolean; máximo uno en true |
Mutaciones de producto: `POST /admin/tours`, `PUT /admin/tours/{tour}`, `DELETE /admin/tours/{tour}`,
`PATCH /admin/tours/{tour}/status`, imágenes `POST/PATCH/DELETE /admin/tours/{tour}/images/{image?}`
→ web + redirect. Se eliminan las equivalentes de `/api/v1/admin/tours*`.

Props de `Admin/Tour/Edit` y `Show` suman:
```ts
tour.routes: TourRouteRef[]        // { id, name, is_default, kind, difficulty, distance_km, duration_hours, stops_count }
availableRoutes: RouteOption[]     // catálogo del tenant: { id, name, kind, difficulty }
```

### Salidas
`GET /admin/departures` pasa a controlador con props (`departures: Paginated<DepartureRow>`, `filters`, `stats`, `tours`, `guides`, `providers`, `hotels`).
`StoreTourDateRequest` / `UpdateTourDateRequest`: `route_id` → `nullable, exists en route_tour para el tour`.
Props para el diálogo de salida (dentro de `Admin/Tour/Edit` y `Admin/Departures/Index`):
```ts
departureDefaults: { guide_id: number|null, capacity: number, route_id: number|null, base_price: string, min_payment_pct: number, currency: string }
tourRoutes: TourRouteRef[]
```
Mutaciones: `POST /admin/tours/{tour}/dates`, `PUT /admin/tour-dates/{tourDate}`,
`PATCH .../cancel|restore|guide`, `DELETE /admin/tour-dates/{tourDate}` → web + redirect. Se eliminan las API.

### Logística (rutas)
`GET /admin/logistics` pasa a controlador con props (`routes`, `providers`, `hotels`, paginados + `filters`).
Mutaciones `POST/PUT/DELETE /admin/routes|providers|hotels` → web + redirect. `DELETE` de ruta en uso
(salidas **o productos**) → `back()->withErrors(['route' => msg])` con el detalle.
`route_stops` suma `latitude`/`longitude` (nullable, decimal:7) y el formulario de paradas de ruta los admite.

### Público
`PublicTourResource.future_dates[]` suma:
```ts
route: null | { id, name, kind, difficulty, distance_km, duration_hours, description,
                stops: { position, name, kind, time_label, latitude, longitude }[] }
guide: null | { name }
```

## 6. Eliminaciones

Rutas API que desaparecen (con controller, request, action, wayfinder y tests):
`GET admin/tours/{tour}/passengers/export`, `GET guide/tour-dates/{tourDate}/passengers/export`,
`GET admin/reports/revenue`, `PUT admin/tenant`, `PUT admin/tenant/configuration`,
`admin/tours*` (apiResource + status + images), `admin/tour-dates*`, `admin/tours/{tour}/dates*`,
`admin/routes|providers|hotels*`, todo `super-admin/*`.
Lecturas auxiliares que se conservan como API porque las consume un buscador asíncrono:
`GET admin/geocode`, `GET admin/guides/availability`, `GET admin/tours/{tour}/passengers`.

## 7. Ajustes 2026-09-16

### Moneda
- `StoreTourRequest`/`UpdateTourRequest`: se elimina `currency`; `CreateTourAction`/`UpdateTourAction` fijan `tours.currency = configuration.currency`.
- `StoreTenantRequest` (super admin) suma `currency` (required, in: lista soportada, default `COP`); se crea la `TenantConfiguration` con esa moneda.
- Prop compartida `tenantConfiguration.currency` es la fuente para `formatCurrency` en todo el front del tenant; ningún componente cae a `'USD'`.
- Dashboard de plataforma: `totals.revenue_this_month` y `totals.earnings_this_month` pasan a `{ currency: string, amount: string }[]`; `charts.earnings_per_month` y `revenue_per_tenant` llevan `currency` por serie. `platform_currency` desaparece de la config.

### Rutas del producto
- Schema: `routes.tour_id` (FK cascade, NOT NULL, index `(tenant_id, tour_id)`), `routes.is_default` bool. Se elimina la tabla `route_tour` (se reescriben las migraciones de esta rama `2026_09_15_200000_*` en vez de agregar otra: el proyecto se levanta con `migrate:fresh --seed`).
- `POST /admin/tours/{tour}/routes` `admin.tours.routes.store`, `PUT /admin/routes/{route}` `admin.routes.update`, `DELETE /admin/routes/{route}` `admin.routes.destroy`, `PATCH /admin/routes/{route}/default` `admin.routes.default`. Request `StoreRouteRequest`/`UpdateRouteRequest` (reglas actuales de `LogisticsRules::route()` + `is_default` boolean + `stops.*.latitude/longitude`). Permisos: `tours.update`. Redirect a `admin.tours.edit` con flash.
- Se eliminan las reglas `routes.*` de los requests de tour, `SyncTourRoutesAction`, `TourRoutesData`, el pivote y las rutas web `admin.routes.*` que vivían bajo `logistics.manage`.
- Props de `Admin/Tour/Edit`: `tour.routes: TourRoute[]` (shape completo con `stops`), sin `availableRoutes`. `Admin/Tour/Show`: `tour.routes` resumido. `LogisticsPagesController@index` deja de enviar `routes`.
- `StoreTourDateRequest`/`UpdateTourDateRequest`: `route_id` → `Rule::exists('routes','id')->where('tour_id', $tourId)`.

## Cambios al contrato

- `2026-09-15` — Creación.
- `2026-09-15` (B1) — `CrossHostLoginHandoff::issue()` queda
  `issue(User $user, string $redirectTo, bool $remember = false, ?int $ttlSeconds = null)`.
  El §4 omitía el `$ttlSeconds` que ya existía y usa el enlace de acceso a la reserva
  (30 min, `Api\V1\BookingController`); se conserva como cuarto parámetro con nombre.
  `consume()` devuelve `App\Data\HandoffPayload` (readonly: `userId`, `redirectTo`,
  `remember`) en lugar de un array.
- `2026-09-15` (B1) — `GET /admin/tenant/configuration` pasa de controlador invocable a
  `TenantConfigurationPagesController@index`: la misma clase sirve la página y el `POST`
  de §3. El nombre de ruta (`admin.tenant.configuration`) no cambia.
- `2026-09-15` (B1) — `contact_info.email` se valida como `email` (el §3 decía solo
  `string`); `contact_info.phone` y `contact_info.whatsapp` topan en 40 caracteres.
- `2026-09-15` (B1) — con la exportación desaparece también la prop
  `snapshot.permissions.can_export_reports` del dashboard del tenant (§6 solo listaba las
  rutas). `DashboardResource` deja de recibir el flag y `DashboardPolicy::exportReports`
  se elimina.
- `2026-09-15` (B2) — `POST /super-admin/tenants/{tenant}/users` deja de responder 409
  `TEAM_ALREADY_MEMBER`: al pasar a ruta web, un 409 JSON rompe el `useForm` del
  diálogo. El controller captura `TeamException` y vuelve con
  `back()->withErrors(['email' => ...])`, que es lo que el §1 pide para reglas de
  negocio. El 409 sigue siendo el del entrar al tenant (`TENANT_NOT_ACTIVE`), que
  el navegador ve como página de error Inertia porque abre en pestaña nueva.
- `2026-09-15` (B2) — `GET /super-admin/tenants` valida `sort` contra
  `name|created_at` y `direction` contra `asc|desc`: un valor fuera de la lista
  ahora es 422, no un silencioso `created_at`. El `per_page` del endpoint API
  desaparece con él (la página pagina de a 15).
- `2026-09-15` (B2) — el dashboard suma la prop `currency`
  (`config('montree.platform_currency')`, `USD` por defecto). El §2 no la listaba
  y el front no tiene de dónde sacar la moneda del agregado: los cargos se guardan
  en la moneda de cada agencia y no se convierten, así que esto es la etiqueta del
  total, no una conversión.
- `2026-09-15` (B2) — `charts.revenue_per_tenant.months` viaja como etiquetas ya
  formateadas (`sep 2026`), no como claves `YYYY-MM`. Es el texto del eje y la
  localización del mes ya se resuelve en el servidor para `MonthPoint.label`.
- `2026-09-15` (B2) — `TenantRow.stats` incluye `users_count` y `tours_count`
  dentro de `stats` (el §1 los listaba ahí, pero el shape viejo los tenía sueltos
  en la raíz). `GET /super-admin/tenants/{tenant}` devuelve `charges_summary` con
  `total_amount`/`total_count`, mientras que `/charges` devuelve `totals` con
  `amount`/`count`: son dos shapes distintos y el front los tipa por separado.
- `2026-09-15` (B2) — `RecordPlatformChargeAction::execute()` acepta un segundo
  parámetro opcional `?Payment $payment = null` para poblar `platform_charges.payment_id`,
  que el plan §4 pedía en el schema pero no en la firma.
- `2026-09-15` (B2) — se comparte la prop Inertia `csrfToken`. El «entrar al
  tenant» es un `<form method="post" target="_blank">` nativo y necesita el token
  en un input; Inertia solo lo pone en la cabecera de sus propias visitas.
- `2026-09-15` (B3) — `route_tour` no lleva `tenant_id`: el aislamiento lo ponen los
  dos extremos (`tours` y `routes` son tenant-scoped) y una columna más sería un
  tercer sitio donde el dato puede quedar desalineado. `routes.*.id` se valida con
  `exists:routes,id` acotado al tenant actual.
- `2026-09-15` (B3) — un producto puede tener rutas **sin** predeterminada. El §5
  no lo decía y la action no elige una por descarte: es lo que hace posible el edge
  case «ruta predeterminada eliminada del producto → la próxima salida se crea sin
  ruta preseleccionada».
- `2026-09-15` (B3) — `departureDefaults` viaja embebido por producto en el tablero
  de salidas (`tours[].departure_defaults` + `tours[].routes`), y como prop suelta
  solo en `Admin/Tour/Edit`, donde el producto es uno. Son seis escalares más la
  lista de rutas —que el selector necesita igual—, así que resolverlos con
  `router.reload({ only })` en cada apertura del diálogo sería un viaje por nada.
- `2026-09-15` (B3) — el diálogo de salida recibe `tourRoutes`; en `Admin/Tour/Edit`
  la página se lo pasa desde `tour.routes` en vez de duplicar el array en una prop
  propia.
- `2026-09-15` (B3) — las reglas de negocio de producto y salida dejan de responder
  409/403 JSON y vuelven con `back()->withErrors()`, que es lo que el §1 pide: límite
  de plan → `plan`; transición de estado inválida, falta de imagen o de guía →
  `status`; borrar un producto con reservas activas → `tour`; salida cancelada o con
  reservas → `tour_date`; ruta/proveedor/hotel en uso → `route`/`provider`/`hotel`.
  `App\Exceptions\LogisticsException` se elimina: era el 409 de logística y ya no
  lo consume nadie.
- `2026-09-15` (B3) — `GET /admin/tours` valida `sort` contra
  `created_at|name|base_price|status|next_departure|occupancy|revenue` y `direction`
  contra `asc|desc`; un valor fuera de lista es 422. El `per_page` del endpoint API
  desaparece con él: la rejilla pagina de a 9 y el tablero de salidas de a 15.
- `2026-09-15` (B3) — `GET /admin/logistics` sirve los **tres** catálogos en la misma
  visita, con paginadores independientes (`routes_page`, `providers_page`,
  `hotels_page`, 12 por página) y un solo `search`. El §5 hablaba de «paginados» sin
  decir que la pestaña necesita el conteo de las tres bandejas para ser útil.
- `2026-09-15` (B3) — `RouteResource` suma `tours_count` (los productos también
  bloquean el borrado) y `RouteStopResource` suma `latitude`/`longitude`.
  `TourResource` suma `routes` (`TourRouteRef[]`) cuando la relación está cargada.
- `2026-09-15` (B3) — `Admin/Tour/Show` suma la prop `departures` (solo futuras) y
  `Admin/Tour/Edit` las props `departures`, `departureOptions` y `availableRoutes`:
  al desaparecer `useTourDepartures.ts` esas listas ya no se pueden pedir desde el
  componente.
- `2026-09-15` (B3) — `PATCH /admin/tours/{tour}/images/{image}` conserva el verbo
  de la API (no lleva archivo); el alta sigue siendo `POST` multipart.
- `2026-09-15` (B3) — la clave del global scope de tenant vive en
  `App\Models\Tenant::SCOPE` y no en el trait: PHP no deja leer una constante de
  trait por el nombre del trait, que era la forma que pedía la tarea.
- `2026-09-15` (B3) — `tours[].duration_hours` viaja en el tablero de salidas: el
  diálogo deriva el fin de la salida con la duración del producto y, al crear desde
  el tablero, no tiene otra fuente.
