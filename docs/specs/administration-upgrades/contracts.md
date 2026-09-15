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

## Cambios al contrato

- `2026-09-15` — Creación.
