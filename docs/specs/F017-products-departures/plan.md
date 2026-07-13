# F017 — Plan técnico

> Decisiones técnicas para implementar este feature.

---

## 1. Resumen

Se evoluciona el modelo existente: `Tour` = Producto, `TourDate` = Salida. Se agregan tres entidades de soporte tenant-scoped (`Route`, `Provider`, `Hotel`), FKs opcionales en `tour_dates` + pivot de hoteles, el CRUD admin de salidas (API + UI) que hoy no existe, y se elimina el requisito de fecha futura para activar un producto. **Requiere `montree-db-architect` primero.**

## 2. Backend

### Modelos

- `Route`, `Provider`, `Hotel` — nuevos, `final`, trait `BelongsToTenant`, `$fillable` explícito. Relación `tourDates()` (hasMany / belongsToMany para Hotel).
- `TourDate` — extendido: `route_id`, `provider_id` en fillable; relaciones `route()`, `provider()`, `hotels()` (belongsToMany, pivot `tour_date_hotels`).
- `Tour` — sin cambios de schema.

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

- `App\Http\Requests\Admin\TourDate\StoreTourDateRequest` / `UpdateTourDateRequest` (reglas del contrato; `guide_id` valida miembro del tenant con rol guide como hace `AssignGuideAction`; `route_id`/`provider_id`/`hotel_ids.*` con `Rule::exists` — el global scope de tenant NO aplica en exists, usar closure/where tenant_id).
- `App\Http\Requests\Admin\TourDate\CancelTourDateRequest` (reason nullable).
- `App\Http\Requests\Admin\Logistics\{StoreRouteRequest, UpdateRouteRequest, StoreProviderRequest, UpdateProviderRequest, StoreHotelRequest, UpdateHotelRequest}`.

### Controllers

- `App\Http\Controllers\Api\V1\Admin\TourDateController` — `index` (nested en tour), `store` (nested), `update`, `destroy`.
- `App\Http\Controllers\Api\V1\Admin\CancelTourDateController` — `__invoke`.
- `App\Http\Controllers\Api\V1\Admin\{RouteController, ProviderController, HotelController}` — `index, store, update, destroy`.
- Rutas en `routes/api.php` dentro del grupo admin existente (mismo middleware que `apiResource tours`).

### Resources

- `App\Http\Resources\Admin\TourDateDetailResource` — shape del contrato (guide/route/provider/hotels con `whenLoaded`).
- `App\Http\Resources\Admin\{RouteResource, ProviderResource, HotelResource}` — incluye `tour_dates_count` via `withCount`.

### Policies

- Reutilizar el gate/middleware admin existente de F003 (los controllers admin actuales no usan Policy por modelo; seguir esa convención).

## 3. Frontend

### Pages

- `resources/js/pages/Admin/Tour/Edit.vue` — agregar sección/tab "Salidas" que monta `TourDatesPanel`.
- `resources/js/pages/Admin/Logistics/Index.vue` — nueva page con tabs Rutas / Proveedores / Hoteles; entrada en el sidebar admin ("Logística").
- Renombrar labels del panel admin: nav "Tours" → "Productos" (solo copy; rutas/archivos no se renombran).
- `resources/js/pages/TourDetail.vue` — cuando `dates` está vacío: card "Sin fechas disponibles" en lugar del selector (verificar comportamiento actual y ajustar copy).

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

## 5. Orden de ejecución

1. `montree-db-architect` — migraciones + modelos nuevos + factories + seeder.
2. `montree-backend-dev` — actions/requests/controllers/resources/rutas + tests + cambio en `ChangeTourStatusAction`.
3. `montree-frontend-dev` — panel salidas + logística + labels "Productos" + detalle público sin fechas.
4. `montree-reviewer` — go/no-go.

## Changelog

- `2026-07-12` — Creación inicial.
