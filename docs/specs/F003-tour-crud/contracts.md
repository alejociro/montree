# F003 — Contratos de API

## GET /api/v1/admin/tours

**Auth:** required. **Permission:** `role:admin` or `role:operator` in current tenant.

Lista tours del tenant con filtros y paginación.

### Query params
- `status?: 'draft' | 'active' | 'paused' | 'archived'`
- `category_id?: int`
- `search?: string` (matches en `name` o `description`)
- `sort?: 'created_at' | 'name' | 'price_base'` (default `created_at`)
- `direction?: 'asc' | 'desc'` (default `desc`)
- `per_page?: int` (default 15, max 100)
- `page?: int`

### Response 200
```json
{
  "data": [
    {
      "id": 1,
      "slug": "senderismo-cocora",
      "name": "Senderismo Cocora",
      "short_description": "...",
      "status": "active",
      "price_base": "120000.00",
      "currency": "COP",
      "duration_hours": 6,
      "difficulty": "moderate",
      "max_capacity": 12,
      "category": { "id": 1, "name": "Senderismo", "slug": "senderismo" },
      "cover_image_url": "https://...",
      "images_count": 4,
      "future_dates_count": 3,
      "bookings_count": 12,
      "rating_average": "4.7",
      "rating_count": 9,
      "created_at": "2026-05-17T10:00:00Z",
      "updated_at": "2026-05-17T10:00:00Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

---

## POST /api/v1/admin/tours

**Auth:** required. **Permission:** `role:admin` or `role:operator`.

Crea tour en status `draft`.

### Request
```json
{
  "name": "Senderismo Cocora",
  "short_description": "Texto corto",
  "description": "Texto largo",
  "category_id": 1,
  "price_base": "120000.00",
  "currency": "COP",
  "duration_hours": 6,
  "difficulty": "moderate",
  "max_capacity": 12,
  "min_capacity": 2,
  "booking_advance_hours": 24,
  "min_partial_payment_pct": 30,
  "requirements": ["Calzado adecuado", "Hidratación"],
  "includes": ["Guía", "Snacks"],
  "meeting_point": "Plaza Cocora",
  "meeting_lat": 4.6371,
  "meeting_lng": -75.5096,
  "itinerary": [
    { "step_number": 1, "title": "Salida", "description": "...", "duration_minutes": 30 },
    { "step_number": 2, "title": "Caminata", "description": "...", "duration_minutes": 240 }
  ]
}
```

**Validación:**
| Campo | Reglas |
|---|---|
| `name` | required, string, max:120 |
| `short_description` | nullable, string, max:200 |
| `description` | nullable, string, max:5000 |
| `category_id` | nullable, exists:categories,id (scoped al tenant) |
| `price_base` | required, decimal, min:0 |
| `currency` | required, size:3, in:USD,COP,EUR,MXN,ARS,PEN,CLP,BRL |
| `duration_hours` | required, numeric, min:0.5, max:240 |
| `difficulty` | required, in:easy,moderate,hard,expert |
| `max_capacity` | required, integer, min:1, max:500 |
| `min_capacity` | required, integer, min:1, lte:max_capacity |
| `booking_advance_hours` | required, integer, min:0 |
| `min_partial_payment_pct` | nullable, integer, min:0, max:100 |
| `requirements`, `includes` | nullable, array of strings, max:20 items |
| `meeting_point` | nullable, string, max:255 |
| `meeting_lat`, `meeting_lng` | nullable, numeric, valid lat/lng ranges |
| `itinerary.*.step_number` | required, integer, min:1, distinct |
| `itinerary.*.title` | required, string, max:120 |
| `itinerary.*.description` | nullable, string, max:1000 |
| `itinerary.*.duration_minutes` | required, integer, min:1 |

### Response 201
Mismo shape que el item de GET (resource con todas las relaciones cargadas).

### Errores
| Status | Caso | error_code |
|---|---|---|
| 422 | Validación | — |
| 403 | Sin permiso | — |
| 403 | Plan max_tours alcanzado | `PLAN_LIMIT_TOURS_REACHED` |
| 422 | category_id de otro tenant | (validation message) |

---

## GET /api/v1/admin/tours/{id}

Mismo shape que item de GET pero incluye `itinerary`, `images` ordenadas, `requirements`, `includes`.

---

## PUT /api/v1/admin/tours/{id}

Igual que POST pero todos los fields opcionales (patch).

### Errores adicionales
| Status | Caso | error_code |
|---|---|---|
| 409 | Slug auto-generado choca y no se pudo desambiguar | `SLUG_COLLISION` |

---

## DELETE /api/v1/admin/tours/{id}

**Auth:** `role:admin` (no operator).

Soft delete. Si tiene reservas activas o futuras → 409 con instrucción de archivar.

### Errores
| Status | Caso | error_code |
|---|---|---|
| 409 | Tiene reservas activas/futuras | `TOUR_HAS_ACTIVE_BOOKINGS` |

---

## POST /api/v1/admin/tours/{id}/images

**Auth:** admin/operator.

Multipart form: `image` file + `is_cover` bool optional + `caption` string optional.

### Validación
| Campo | Reglas |
|---|---|
| `image` | required, file, mimes:jpg,jpeg,png,webp, max:5120 (KB) |
| `is_cover` | boolean |
| `caption` | nullable, string, max:200 |

### Response 201
```json
{ "data": { "id": 7, "tour_id": 1, "url": "...", "is_cover": false, "display_order": 4, "caption": null } }
```

### Errores
| Status | Caso |
|---|---|
| 422 | File > 5MB → "Image must not exceed 5MB" |
| 422 | Mime inválido |

---

## PATCH /api/v1/admin/tours/{id}/images/{imageId}

Body: `{ is_cover?: bool, display_order?: int, caption?: string }`. Si `is_cover=true`, desmarca al previo automáticamente.

---

## DELETE /api/v1/admin/tours/{id}/images/{imageId}

Hard delete.

---

## PATCH /api/v1/admin/tours/{id}/status

**Auth:** admin/operator.

### Request
```json
{ "status": "active" }
```

Transiciones permitidas:
- `draft → active` (requiere ≥1 imagen + ≥1 fecha futura abierta)
- `active → paused`
- `paused → active`
- `active|paused|draft → archived` (sólo admin)
- `archived → draft` (sólo admin)

### Errores
| Status | Caso | error_code |
|---|---|---|
| 422 | Transición inválida | `INVALID_STATUS_TRANSITION` |
| 422 | Activar sin imagen | `TOUR_NEEDS_IMAGE_TO_ACTIVATE` |
| 422 | Activar sin fecha futura | `TOUR_NEEDS_FUTURE_DATE_TO_ACTIVATE` |
| 403 | Operator intenta archivar | `INSUFFICIENT_ROLE` |

---

## Inertia pages

| Route | Page |
|---|---|
| `/admin/tours` | `Admin/Tour/Index.vue` (lista paginada con filtros) |
| `/admin/tours/create` | `Admin/Tour/Create.vue` (form) |
| `/admin/tours/{id}/edit` | `Admin/Tour/Edit.vue` (form + image manager) |

Las pages usan `useForm` con Wayfinder actions. La lista usa `usePage().props.tours` con deferred props.

---

## Eventos / Side-effects

- `Tour::created`, `Tour::updated`, `Tour::deleted` (Eloquent events) → no listeners aún en F003.
- Sin colas necesarias en F003.

---

## Cambios

- `2026-05-17` — Creación inicial.
- `2026-05-17` — Alineación con schema implementado: el contrato usa los nombres
  reales de columnas (`base_price`, `default_capacity`, `meeting_latitude/longitude`,
  `duration_label`, `excludes`). Se eliminan `min_capacity`/`max_capacity`,
  `booking_advance_hours`, `min_partial_payment_pct` (no existen en `tours` ni en
  `tenant_configurations`; quedan para F007/F008). El itinerario acepta
  `duration_label` (string corto, ej "30 min") en lugar de `duration_minutes`
  para coincidir con `tour_itineraries.duration_label`. Las imágenes usan
  `alt_text` en lugar de `caption`. Endpoint `images.update` no recibe
  `display_order` aún (no expuesto en UI del MVP — se reordena via cover toggle
  solamente).
