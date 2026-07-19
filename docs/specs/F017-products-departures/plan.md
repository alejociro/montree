# F017 — Plan técnico

> Decisiones técnicas para implementar este feature.

---

## 1. Resumen

Se evoluciona el modelo existente: `Tour` = Producto, `TourDate` = Salida. Se agregan tres entidades de soporte tenant-scoped (`Route`, `Provider`, `Hotel`), FKs opcionales en `tour_dates` + pivot de hoteles, el CRUD admin de salidas (API + UI) que hoy no existe, y se elimina el requisito de fecha futura para activar un producto. **Requiere `montree-db-architect` primero.**

## 2. Backend

### Modelos

- `Route`, `Provider`, `Hotel` — nuevos, `final`, trait `BelongsToTenant`, `$fillable` explícito. Relación `tourDates()` (hasMany / belongsToMany para Hotel).
- `TourDate` — extendido: `route_id`, `provider_id` en fillable; relaciones `route()`, `provider()`, `hotels()` (belongsToMany, pivot `tour_date_hotels`). **Nuevo método `displayStatus(): TourDateDisplayStatus`** que deriva el estado de presentación (ver "Estado de presentación derivado").
- `Tour` — sin cambios de schema.

### Estado de presentación derivado (sin migración)

- Enum nuevo `App\Enums\TourDateDisplayStatus`: `Open`, `Full`, `Closed`, `Cancelled`, `InProgress`, `Finished` (values `open`/`full`/`closed`/`cancelled`/`in_progress`/`finished`, TitleCase en keys por constitución PHP rules).
- `TourDate::displayStatus()` deriva en request-time (sea `$end = $this->ends_at ?? $this->starts_at`, `$now = now()`):
  1. `status === cancelled` → `Cancelled` (siempre gana).
  2. `$end < $now` → `Finished`.
  3. `$starts_at <= $now <= $end` → `InProgress` (solo posible con `ends_at` asignado).
  4. else → mapear el `status` almacenado (`open`/`full`/`closed`).
- **Racional / decisión:** NO se agregan columnas ni estados persistidos. Los estados `in_progress`/`finished` son función del reloj; persistirlos exigiría jobs de transición programados y podría quedar stale entre corridas. Derivarlos es determinista, sin migración y sin costo operativo. El `status` almacenado sigue siendo fuente de verdad del ciclo de vida no-temporal.
- El filtro `status` del endpoint global filtra sobre `display_status`: como `in_progress`/`finished` son derivados, el filtrado combina un `where` sobre columnas (`starts_at`/`ends_at`/`status`) que reproduce las reglas de derivación en SQL (no se puede filtrar por un accessor). Alternativa aceptada si el volumen lo permite: traer y filtrar en memoria por página — decisión de implementación del backend-dev, priorizar el filtrado en SQL para respetar el paginado.

### Migrations (montree-db-architect)

- `create_routes_table`: tenant_id FK NOT NULL indexada, name, description nullable, distance_km decimal(8,2) nullable, duration_hours decimal(5,1) nullable, timestamps. Índice `[tenant_id, name]`.
- `create_providers_table`: tenant_id, name, service_type nullable, contact_name/phone/email nullables, notes nullable, timestamps.
- `create_hotels_table`: tenant_id, name, address nullable, contact_phone/email nullables, notes nullable, timestamps.
- `create_tour_date_hotels_table`: tour_date_id FK cascade, hotel_id FK cascade, unique compuesto.
- `add_route_and_provider_to_tour_dates`: `route_id`, `provider_id` nullable FKs `nullOnDelete`... **NO**: `nullOnDelete` contradice la regla 409 `RESOURCE_IN_USE`; usar `restrictOnDelete` para que la BD refuerce el check de la app.
- Factories para los 3 modelos nuevos; `TourDateFactory` con states `withRoute()`, `withProvider()`, `withHotels()`.
- Seeder demo: 2 rutas, 2 proveedores, 2 hoteles y condiciones en algunas fechas.

### Actions

- `App\Actions\TourDate\CreateTourDateAction`
- `App\Actions\TourDate\UpdateTourDateAction` (valida capacity ≥ booked_count; bloquea starts_at con reservas)
- `App\Actions\TourDate\CancelTourDateAction` (409 si ya cancelada)
- `App\Actions\TourDate\DeleteTourDateAction` (409 si tiene bookings)
- `App\Actions\Tour\ChangeTourStatusAction` — **modificar**: quitar requisito de fecha futura (mantener ≥1 imagen).
- CRUD de routes/providers/hotels: operaciones triviales → sin Action (regla constitución: "Operaciones triviales (índice CRUD)"), lógica directa en controller ≤10 líneas usando el modelo, salvo el delete con check de uso → `App\Actions\Logistics\DeleteLogisticsResourceAction` genérica NO — regla del 3 aplica distinto: el check "tiene salidas → 409" se repite en 3 recursos → un `App\Services\Logistics\ResourceUsageGuard` es sobreingeniería; preferir método `assertNotInUse()` por controller o excepción de dominio compartida `LogisticsException::inUse()`. Decisión final: excepción de dominio compartida + check inline (3 líneas por controller).

### Excepciones de dominio

- `App\Exceptions\TourDateException`: `hasBookings()` (409 `TOUR_DATE_HAS_BOOKINGS`), `alreadyCancelled()` (409 `TOUR_DATE_ALREADY_CANCELLED`), `cancelled()` (409 `TOUR_DATE_CANCELLED`).
- `App\Exceptions\LogisticsException`: `inUse(string $resource, int $count)` (409 `RESOURCE_IN_USE`).

### Form Requests

- `App\Http\Requests\Admin\TourDate\IndexTourDatesRequest` — valida filtros del listado global: `status` (`Rule::enum(TourDateDisplayStatus::class)` o `in:` whitelist), `tour_id` (exists tenant), `from`/`to` (date, `to` con `after_or_equal:from`), `direction` (`in:asc,desc`), `per_page` (`integer|max:100`).
- `App\Http\Requests\Admin\TourDate\StoreTourDateRequest` / `UpdateTourDateRequest` (reglas del contrato; `guide_id` valida miembro del tenant con rol guide como hace `AssignGuideAction`; `route_id`/`provider_id`/`hotel_ids.*` con `Rule::exists` — el global scope de tenant NO aplica en exists, usar closure/where tenant_id).
- `App\Http\Requests\Admin\TourDate\CancelTourDateRequest` (reason nullable).
- `App\Http\Requests\Admin\Logistics\{StoreRouteRequest, UpdateRouteRequest, StoreProviderRequest, UpdateProviderRequest, StoreHotelRequest, UpdateHotelRequest}`.

### Controllers

- `App\Http\Controllers\Api\V1\Admin\TourDateController` — `index` (nested en tour), `store` (nested), `update`, `destroy`.
- `App\Http\Controllers\Api\V1\Admin\AdminDeparturesController` (o `TourDateIndexController` invokable) — `__invoke`: listado global cross-producto con filtros de `IndexTourDatesRequest`, eager-load `tour:id,name,slug` + guide/route/provider/hotels (sin N+1), paginado. Ruta `GET /api/v1/admin/tour-dates`.
- `App\Http\Controllers\Api\V1\Admin\CancelTourDateController` — `__invoke`.
- `App\Http\Controllers\Api\V1\Admin\{RouteController, ProviderController, HotelController}` — `index, store, update, destroy`.
- Rutas en `routes/api.php` dentro del grupo admin existente (mismo middleware que `apiResource tours`).

### Resources

- `App\Http\Resources\Admin\TourDateDetailResource` — shape del contrato (guide/route/provider/hotels con `whenLoaded`). **Ampliado (2026-07-13):** agrega `display_status` (`$this->displayStatus()->value`) y `tour` (`whenLoaded('tour')` → `{ id, name, slug, currency }`). Sirve tanto al index anidado como al global.
- `App\Http\Resources\Admin\{RouteResource, ProviderResource, HotelResource}` — incluye `tour_dates_count` via `withCount`.
- `App\Http\Resources\Catalog\UpcomingDepartureResource` — **NUEVO (2026-07-13), público.** Alimenta la prop deferred `upcomingDepartures` del home. Shape mínimo para el viajero: `id`, `starts_at`, `ends_at`, `available_seats`, `effective_price`, `tour: { name, slug, currency, cover_image_url }`. **Decisión: NO reutilizar `TourDateDetailResource`** (admin) para evitar filtrar información operativa interna (notes, guide, route, provider, hotels, booked_count, capacity cruda, price_override) al público. `effective_price = price_override ?? tour.base_price`; `cover_image_url` con el patrón http-passthrough / `Storage::disk('public')->url()`.

### Home público — sección "Próximas salidas"

- **Backend:** prop deferred `upcomingDepartures` en `HomePageController` (método privado siguiendo el patrón de las demás props, envuelto en `Inertia::defer`). Query: `TourDate::openFuture()` (scope existente `status = open` AND `starts_at > now`) + `whereHas('tour', active)` + `with('tour.coverImage')` + `orderBy('starts_at')` + `limit(6)`. **No es endpoint API** — es prop de Inertia (ver `contracts.md` § "Prop Inertia `upcomingDepartures`").
- **Decisión de producto: NO crear página/tab pública de "Salidas".** El home ("Próximas salidas") más el catálogo `/tours` (productos base, sin cambios) cubren la necesidad del viajero. Fragmentar la navegación con una tercera vista pública no aporta valor.
- **Newsletter storefront eliminado:** se quita la sección de newsletter del home público (`Home.vue`) por falta de soporte de envío de correos. El módulo admin de newsletter (F013) y su endpoint API quedan intactos — solo se remueve la sección del storefront.

### Policies

- Reutilizar el gate/middleware admin existente de F003 (los controllers admin actuales no usan Policy por modelo; seguir esa convención).

## 3. Frontend

### Pages

- `resources/js/pages/Admin/Tour/Edit.vue` — agregar sección/tab "Salidas" que monta `TourDatesPanel`.
- `resources/js/pages/Admin/Logistics/Index.vue` — nueva page con tabs Rutas / Proveedores / Hoteles; entrada en el sidebar admin ("Logística").
- `resources/js/pages/Admin/Departures/Index.vue` — **NUEVA:** listado global de salidas del tenant. Tabla: producto (link a show admin), fecha, ocupación (reservados/capacidad con barra), precio efectivo, guía, condiciones (ruta/proveedor/hoteles), estado (`display_status`). Filtros: estado / producto / rango de fechas. Acciones por fila: editar (reutiliza `TourDateFormDialog`) y cancelar (mismo flujo de `TourDatesPanel`). Consume `GET /api/v1/admin/tour-dates` vía `useApi()`/fetch de solo lectura + Wayfinder.
- Sidebar admin: dos entradas — "Productos" (`/admin/tours`, catálogo existente) y "Tours" (`/admin/departures`, listado global nuevo).
- Renombrar labels del panel admin: nav "Tours" (catálogo) → "Productos" (solo copy; rutas/archivos no se renombran).
- `resources/js/pages/TourDetail.vue` — cuando `dates` está vacío: card "Sin fechas disponibles" en lugar del selector (verificar comportamiento actual y ajustar copy).
- `resources/js/pages/Home.vue` — **NUEVA sección "Próximas salidas"** entre "Tours destacados" y "Promociones". Consume la prop deferred `upcomingDepartures` (patrón partial reload de Inertia v3, NO `useApi`/`fetch`). Cards: imagen del producto, nombre (link a detalle público por `slug`), fecha en español, cupos con badge de urgencia (≤3), precio efectivo, CTA "Reservar" → `/booking/new?tour_date_id=X`. Skeleton animado mientras resuelve el deferred; si la lista viene vacía la sección no se renderiza. **Se elimina la sección de newsletter** del storefront (sin soporte de correos).

### Organisms / Molecules

- `TourDatesPanel.vue` — tabla de salidas (próximas/pasadas/canceladas), acciones editar/cancelar/eliminar, botón "Nueva salida".
- `TourDateFormDialog.vue` — form crear/editar: fechas, capacidad (`CounterStepper` reutilizado o input numérico existente `CapacityInput`), precio override, notas, selects de guía/ruta/proveedor, multi-select de hoteles.
- `LogisticsCrudPanel.vue` — genérico parametrizado por recurso (columnas + campos de form por config) para las 3 tabs.

### Composables / Types

- `useApi()` existente para todas las llamadas a `/api/v1/admin/*` (constitución §4.2) + Wayfinder para URLs.
- `resources/js/types/logistics.ts` — `TourDateAdmin`, `RouteResource`, `ProviderResource`, `HotelResource`, inputs de forms.

## 4. Tests

Por endpoint: happy + failure + edge + tenant isolation (testing-policy). Puntos críticos:

- Store salida: fecha pasada 422; guide de otro tenant 422; hotel de otro tenant 422.
- Update: capacity < booked_count 422; starts_at con reservas 409.
- Cancel: happy + ya cancelada 409.
- Delete: sin reservas 204; con reservas 409.
- Delete ruta/proveedor/hotel en uso: 409 `RESOURCE_IN_USE`.
- ChangeTourStatus: activar sin fechas ahora pasa (actualizar tests de F003 que exigían fecha futura).
- Catálogo público: producto activo sin fechas aparece con `has_future_dates: false` (probablemente ya cubierto en F004 — verificar).
- **Listado global `GET /api/v1/admin/tour-dates`**: happy (retorna salidas de todos los productos del tenant); tenant isolation (no filtra salidas de otro tenant); filtro `status=in_progress`/`finished` derivado (salida con `ends_at` pasado → finished; salida en curso → in_progress); filtro `tour_id`/`from`/`to`; 422 con `status` fuera de whitelist o `tour_id` de otro tenant.
- **`displayStatus()` unit/feature**: cancelled gana; ends_at pasado → finished; en curso → in_progress; futuro → status almacenado.

## 5. Orden de ejecución

1. `montree-db-architect` — migraciones + modelos nuevos + factories + seeder.
2. `montree-backend-dev` — actions/requests/controllers/resources/rutas + tests + cambio en `ChangeTourStatusAction`.
3. `montree-frontend-dev` — panel salidas + logística + labels "Productos" + detalle público sin fechas.
4. `montree-reviewer` — go/no-go.

## Changelog

- `2026-07-12` — Creación inicial.
- `2026-07-13` — Enum derivado `TourDateDisplayStatus` + `TourDate::displayStatus()` (sin migración), controller/ruta del listado global `GET /api/v1/admin/tour-dates` con `IndexTourDatesRequest`, `TourDateDetailResource` ampliado (`display_status` + `tour`), page frontend `Admin/Departures/Index.vue` y segunda entrada de sidebar "Tours". Razón: separación Productos vs Tours en admin + estado de presentación derivado.
- `2026-07-13` — Lado público del home: prop deferred `upcomingDepartures` en `HomePageController` (no endpoint API), resource público NUEVO `App\Http\Resources\Catalog\UpcomingDepartureResource` (shape mínimo, sin datos operativos internos), sección "Próximas salidas" en `Home.vue` y eliminación de la sección newsletter del storefront. Decisión: NO crear página/tab pública de "Salidas" (home + catálogo cubren la necesidad). Razón: distinguir productos base vs salidas concretas y permitir reservar desde el home sin fragmentar navegación.
