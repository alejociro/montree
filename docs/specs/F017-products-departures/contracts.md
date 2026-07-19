# F017 — Contratos de API

> Shapes exactos de request y response. Es CONTRATO: backend y frontend
> se basan en este archivo. Modificar requiere acuerdo de ambos lados.

---

## GET /api/v1/admin/tour-dates  (listado global cross-producto)

**Auth:** required (admin/operator del tenant)
**Permission:** gestión de tours (mismo gate que F003)

Listado global de TODAS las salidas del tenant, sin anidar en un producto. Alimenta la page `Admin/Departures/Index` (sidebar "Tours").

Query params:

| Param | Tipo | Reglas |
|---|---|---|
| `status` | string | nullable, whitelist: `open` \| `full` \| `closed` \| `cancelled` \| `in_progress` \| `finished`. Filtra sobre el **estado de presentación** (`display_status`), no sobre el `status` almacenado. |
| `tour_id` | integer | nullable, exists tours del tenant |
| `from` | date | nullable, filtra `starts_at >= from` |
| `to` | date | nullable, filtra `starts_at <= to`; `after_or_equal:from` |
| `direction` | string | nullable, `asc` \| `desc` (default `desc`) — orden por `starts_at` |
| `per_page` | integer | nullable, default 15, max 100 |

Orden por defecto: `starts_at` desc.

### Response 200

Paginado estándar. Cada item tiene el **mismo shape que el index anidado** (ver abajo) MÁS `display_status` y `tour`.

```json
{
  "data": [
    {
      "id": 7,
      "starts_at": "2026-08-03T20:26:00Z",
      "ends_at": "2026-08-04T02:26:00Z",
      "capacity": 12,
      "booked_count": 4,
      "available_seats": 8,
      "price_override": "950.00",
      "effective_price": "950.00",
      "status": "open",
      "display_status": "open",
      "notes": "string|null",
      "guide": { "id": 3, "name": "Demo Guide" },
      "route": { "id": 1, "name": "Ruta El Mirador" },
      "provider": { "id": 2, "name": "Transportes Andinos" },
      "hotels": [{ "id": 1, "name": "Ecohotel La Montaña" }],
      "tour": { "id": 5, "name": "Cascadas de Montree", "slug": "cascadas-de-montree", "currency": "USD" }
    }
  ],
  "links": {},
  "meta": {}
}
```

`display_status` es el estado de presentación derivado (ver `spec.md` § "Estado de presentación derivado"): `open` | `full` | `closed` | `cancelled` | `in_progress` | `finished`. `tour` nunca es `null` (toda salida pertenece a un producto).

### Errores

| Status | Caso | error_code |
|---|---|---|
| 401 | No autenticado | — |
| 403 | Sin permiso admin/operator del tenant | — |
| 422 | Filtros inválidos (status fuera de whitelist, tour_id de otro tenant, fechas mal formadas, per_page > 100) | — |

---

## GET /api/v1/admin/tours/{tour}/dates

**Auth:** required (admin/operator del tenant)
**Permission:** gestión de tours (mismo gate que F003)

Query params: `scope` opcional (`upcoming` default | `past` | `all`), `per_page` (default 15, max 100).

### Response 200

```json
{
  "data": [
    {
      "id": 7,
      "starts_at": "2026-08-03T20:26:00Z",
      "ends_at": "2026-08-04T02:26:00Z",
      "capacity": 12,
      "booked_count": 4,
      "available_seats": 8,
      "price_override": "950.00",
      "effective_price": "950.00",
      "status": "open",
      "display_status": "open",
      "notes": "string|null",
      "guide": { "id": 3, "name": "Demo Guide" },
      "route": { "id": 1, "name": "Ruta El Mirador" },
      "provider": { "id": 2, "name": "Transportes Andinos" },
      "hotels": [{ "id": 1, "name": "Ecohotel La Montaña" }],
      "tour": { "id": 5, "name": "Cascadas de Montree", "slug": "cascadas-de-montree", "currency": "USD" }
    }
  ],
  "links": {},
  "meta": {}
}
```

`guide`, `route`, `provider` son `null` si no están asignados; `hotels` puede ser `[]`.

**Adición no-breaking (2026-07-13):** `display_status` (estado de presentación derivado) y `tour: { id, name, slug, currency }` se agregan también a este endpoint anidado. Son campos nuevos; los consumidores existentes no se rompen.

---

## POST /api/v1/admin/tours/{tour}/dates

**Auth:** required (admin/operator del tenant)

### Request

```json
{
  "starts_at": "2026-08-03T20:26:00Z",
  "ends_at": "2026-08-04T02:26:00Z",
  "capacity": 12,
  "price_override": "950.00",
  "notes": "string",
  "guide_id": 3,
  "route_id": 1,
  "provider_id": 2,
  "hotel_ids": [1, 4]
}
```

**Validación:**
| Campo | Tipo | Reglas |
|---|---|---|
| `starts_at` | datetime ISO | required, after:now |
| `ends_at` | datetime ISO | nullable, after:starts_at |
| `capacity` | integer | required, min:1, max:500 |
| `price_override` | decimal string | nullable, numeric, min:0 |
| `notes` | string | nullable, max:1000 |
| `guide_id` | integer | nullable, exists en users miembros del tenant con rol guide |
| `route_id` | integer | nullable, exists routes del tenant |
| `provider_id` | integer | nullable, exists providers del tenant |
| `hotel_ids` | int[] | nullable array, cada uno exists hotels del tenant, sin duplicados |

### Response 201

Shape de item igual al index. Status inicial siempre `open`, `booked_count: 0`.

### Errores

| Status | Caso | error_code |
|---|---|---|
| 422 | Validación (fecha pasada, capacity < 1, IDs de otro tenant → exists falla como validación) | — |
| 404 | Tour de otro tenant / no existe | — |

---

## PUT /api/v1/admin/tour-dates/{tourDate}

**Auth:** required (admin/operator del tenant)

### Request

Mismos campos del POST, todos opcionales excepto los enviados se validan igual (`sometimes`). `hotel_ids` reemplaza el set completo.

**Regla extra:** `capacity` ≥ `booked_count` actual → si no, 422 con mensaje en `capacity`.
`starts_at` solo editable si la salida no tiene reservas activas; con reservas → 409 `TOUR_DATE_HAS_BOOKINGS` (cambiar la fecha con gente reservada requiere cancelar y crear otra).

### Response 200

Item completo actualizado.

### Errores

| Status | Caso | error_code |
|---|---|---|
| 422 | capacity < booked_count | — |
| 409 | Editar starts_at con reservas activas | `TOUR_DATE_HAS_BOOKINGS` |
| 409 | Salida cancelada | `TOUR_DATE_CANCELLED` |
| 404 | Otro tenant | — |

---

## PATCH /api/v1/admin/tour-dates/{tourDate}/cancel

**Auth:** required (admin/operator del tenant)

### Request

```json
{ "reason": "string" }
```

| Campo | Tipo | Reglas |
|---|---|---|
| `reason` | string | nullable, max:500 (se guarda en `notes` con prefijo o columna propia según plan) |

### Response 200

Item con `status: "cancelled"`.

### Errores

| Status | Caso | error_code |
|---|---|---|
| 409 | Ya cancelada | `TOUR_DATE_ALREADY_CANCELLED` |
| 404 | Otro tenant | — |

---

## DELETE /api/v1/admin/tour-dates/{tourDate}

**Auth:** required (admin/operator del tenant)

### Response 204

Solo si `booked_count = 0` y sin bookings históricos asociados.

### Errores

| Status | Caso | error_code |
|---|---|---|
| 409 | Tiene reservas (activas o históricas) | `TOUR_DATE_HAS_BOOKINGS` |
| 404 | Otro tenant | — |

---

## CRUD de catálogos de soporte

Tres recursos idénticos en forma: `routes`, `providers`, `hotels`.

### GET /api/v1/admin/{resource}

**Auth:** required (admin/operator). Paginado estándar. `?search=` opcional sobre `name`.

```json
{ "data": [{ "id": 1, "name": "string", "description": "string|null", "...": "campos propios", "tour_dates_count": 3 }] }
```

### POST /api/v1/admin/{resource} → 201 · PUT /api/v1/admin/{resource}/{id} → 200 · DELETE → 204

**Campos por recurso:**

| Recurso | Campos |
|---|---|
| `routes` | `name` (required, max:255), `description` (nullable, max:2000), `distance_km` (nullable, numeric, min:0), `duration_hours` (nullable, numeric, min:0) |
| `providers` | `name` (required, max:255), `service_type` (nullable, max:255 — ej. transporte, alimentación), `contact_name` (nullable, max:255), `contact_phone` (nullable, max:50), `contact_email` (nullable, email), `notes` (nullable, max:2000) |
| `hotels` | `name` (required, max:255), `address` (nullable, max:500), `contact_phone` (nullable, max:50), `contact_email` (nullable, email), `notes` (nullable, max:2000) |

### Errores comunes

| Status | Caso | error_code |
|---|---|---|
| 409 | DELETE con salidas asociadas | `RESOURCE_IN_USE` (mensaje incluye cantidad de salidas) |
| 404 | Otro tenant | — |
| 422 | Validación | — |

---

## Cambio de comportamiento: PATCH /api/v1/admin/tours/{tour}/status

La regla de activación pasa de "≥1 imagen Y ≥1 fecha futura open" a **solo "≥1 imagen"**. El error `TOUR_WITHOUT_FUTURE_DATES` (o equivalente actual) desaparece del contrato de F003.

## Cambio de comportamiento: catálogo y detalle públicos

- `GET /api/v1/tours` (F004): sin cambios de shape — `has_future_dates: false` ya existe; los productos activos sin fechas siguen apareciendo.
- `GET /api/v1/tours/{slug}` (F005): sin cambios de shape — `dates: []` cuando no hay salidas; el frontend muestra "Sin fechas disponibles" sin CTA de reserva.

---

## Prop Inertia `upcomingDepartures` (home público — NO es endpoint REST)

> **Importante:** esto NO es un endpoint de la API `/api/v1/*`. Es una **prop de Inertia deferred** que sirve `HomePageController` al renderizar `resources/js/pages/Home.vue`. Se documenta acá por ser un contrato backend↔frontend, pero no tiene ruta REST, ni versionado, ni response envelope de la API. Se consume vía partial reload del prop deferred (patrón Inertia v3), no con `fetch`/`useApi`.

**Origen:** `HomePageController` (home del tenant, resuelto por subdominio).
**Tipo de prop:** `Inertia::defer(...)` — se carga en un request de continuación tras el render inicial; el frontend muestra skeleton mientras resuelve.
**Resource:** `App\Http\Resources\Catalog\UpcomingDepartureResource` (público, nuevo — NO reutiliza el admin `TourDateDetailResource`).

**Reglas de selección (backend):**
- Solo salidas con `status = open` y `starts_at > now`.
- Solo de productos (`tour`) activos del tenant actual.
- Orden `starts_at` ascendente.
- Límite: hasta 6 salidas.
- Eager load `tour.coverImage` (sin N+1).

**Shape de cada item:**

```json
{
  "id": 7,
  "starts_at": "2026-08-03T20:26:00+00:00",
  "ends_at": "2026-08-04T02:26:00+00:00",
  "available_seats": 8,
  "effective_price": "950.00",
  "tour": {
    "name": "Cascadas de Montree",
    "slug": "cascadas-de-montree",
    "currency": "USD",
    "cover_image_url": "https://.../cover.jpg"
  }
}
```

- `ends_at`: ISO 8601 o `null`.
- `tour.cover_image_url`: URL o `null`.
- `effective_price` = `price_override ?? tour.base_price` (decimal string).
- Fechas serializadas con `toIso8601String()` (offset `+00:00`), consistente con el resto de la app.

**Claves deliberadamente NO expuestas al público** (información operativa interna): `notes`, `guide`, `route`, `provider`, `hotels`, `booked_count`, `capacity` (cruda) y `price_override`. Un test verifica su ausencia.

**Consumo frontend:** cada card enlaza al detalle público del tour (por `slug`) y su CTA "Reservar" apunta a `/booking/new?tour_date_id={id}`. Si la lista viene vacía, la sección no se renderiza.

---

## Eventos / Side-effects

- Cancelar una salida NO notifica automáticamente a los viajeros (out of scope, F008 futuro). Las reservas asociadas no cambian de estado en esta iteración.
- Crear/editar/cancelar salidas no toca `tours.status`.

---

## Cambios al contrato

- `2026-07-12` — Creación inicial.
- `2026-07-13` — Nuevo endpoint global + campos derivados. Razón: separación Productos vs Tours en admin + estado de presentación derivado.
  - **Endpoint NUEVO:** `GET /api/v1/admin/tour-dates` (listado global cross-producto, con filtros `status`/`tour_id`/`from`/`to`/`direction`/`per_page`).
  - **Adición no-breaking** a `GET /api/v1/admin/tour-dates`, `GET /api/v1/admin/tours/{tour}/dates`, `POST /api/v1/admin/tours/{tour}/dates` (respuesta 201 hereda el shape del index) y `PUT /api/v1/admin/tour-dates/{tourDate}`: se agregan `display_status` (string derivado) y `tour: { id, name, slug, currency }` al shape del item.
  - **Impacto backend/frontend:** son adiciones (no cambian ni renombran campos existentes) → NO breaking. Endpoints afectados por la adición de campos: los 4 listados arriba. Consumidores actuales del index anidado (`TourDatesPanel`, `TourDateFormDialog`) siguen funcionando sin cambios; opcionalmente pueden empezar a leer `display_status`.
- `2026-07-13` — Nueva **prop Inertia deferred** `upcomingDepartures` (home público, `HomePageController`) + resource público `UpcomingDepartureResource`. Razón: sección "Próximas salidas" en el home del tenant. **No es endpoint REST**: no impacta el contrato de la API `/api/v1/*` ni versionado. No breaking para consumidores existentes (prop nueva). Contrato de shape documentado en la sección "Prop Inertia `upcomingDepartures`" de este archivo, con las claves internas explícitamente NO expuestas al público.
