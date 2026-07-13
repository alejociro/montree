# F017 — Contratos de API

> Shapes exactos de request y response. Es CONTRATO: backend y frontend
> se basan en este archivo. Modificar requiere acuerdo de ambos lados.

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
      "notes": "string|null",
      "guide": { "id": 3, "name": "Demo Guide" },
      "route": { "id": 1, "name": "Ruta El Mirador" },
      "provider": { "id": 2, "name": "Transportes Andinos" },
      "hotels": [{ "id": 1, "name": "Ecohotel La Montaña" }]
    }
  ],
  "links": {},
  "meta": {}
}
```

`guide`, `route`, `provider` son `null` si no están asignados; `hotels` puede ser `[]`.

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

## Eventos / Side-effects

- Cancelar una salida NO notifica automáticamente a los viajeros (out of scope, F008 futuro). Las reservas asociadas no cambian de estado en esta iteración.
- Crear/editar/cancelar salidas no toca `tours.status`.

---

## Cambios al contrato

- `2026-07-12` — Creación inicial.
