# F011 — Contratos de API

## GET /api/v1/admin/dashboard

**Auth:** required. **Permission:** `role:admin` or `role:operator`.

Resumen de métricas del tenant para el periodo seleccionado.

### Query params
- `period?: 'last_7_days' | 'last_30_days' | 'last_90_days' | 'this_month' | 'last_month' | 'this_year'` (default `last_30_days`)
- `tz?: string` (default `tenant.configuration.timezone`)

### Response 200
```json
{
  "data": {
    "period": {
      "key": "last_30_days",
      "start": "2026-04-17T00:00:00-05:00",
      "end": "2026-05-17T23:59:59-05:00",
      "previous_start": "2026-03-18T00:00:00-05:00",
      "previous_end": "2026-04-16T23:59:59-05:00"
    },
    "revenue": {
      "gross": "12450000.00",
      "net": "11825000.00",
      "currency": "COP",
      "growth_pct": 14.3,
      "previous_gross": "10895000.00"
    },
    "bookings": {
      "total": 38,
      "confirmed": 30,
      "pending_payment": 5,
      "cancelled": 3,
      "growth_pct": 26.7,
      "previous_total": 30
    },
    "rating": {
      "average": "4.7",
      "count": 18
    },
    "occupancy": {
      "upcoming_dates_count": 12,
      "total_capacity": 144,
      "booked_seats": 87,
      "occupancy_pct": 60.4
    },
    "top_tours": [
      {
        "id": 1,
        "slug": "senderismo-cocora",
        "name": "Senderismo Cocora",
        "bookings_count": 12,
        "revenue": "1440000.00",
        "rating_average": "4.8",
        "cover_image_url": "https://..."
      }
    ],
    "upcoming_dates": [
      {
        "id": 42,
        "tour_id": 1,
        "tour_name": "Senderismo Cocora",
        "starts_at": "2026-05-18T07:00:00-05:00",
        "capacity_total": 12,
        "capacity_booked": 8,
        "occupancy_pct": 66.7,
        "guide_name": "Demo Guide"
      }
    ],
    "pending_reviews_count": 3,
    "permissions": {
      "can_export_reports": true
    }
  }
}
```

`growth_pct` puede ser `null` si periodo anterior tenía 0 (no division by zero — devuelve null y frontend muestra "N/A").

Operator: `permissions.can_export_reports = false`, no se renderiza el botón.

---

## GET /api/v1/admin/reports/revenue

**Auth:** `role:admin`.

Exporta revenue agrupado.

### Query params
- `from: date` (required, ISO date)
- `to: date` (required, ISO date)
- `group_by?: 'day' | 'week' | 'month'` (default `day`)
- `format?: 'json' | 'csv'` (default `json`)

### Response 200 (json)
```json
{
  "data": {
    "from": "2026-04-01",
    "to": "2026-04-30",
    "group_by": "day",
    "rows": [
      { "bucket": "2026-04-01", "gross": "350000.00", "net": "332500.00", "bookings_count": 2 },
      ...
    ],
    "totals": { "gross": "12450000.00", "net": "11825000.00", "bookings_count": 38 }
  }
}
```

### Response 200 (csv)
`Content-Type: text/csv; charset=utf-8`
`Content-Disposition: attachment; filename="revenue-2026-04-01-to-2026-04-30.csv"`

Body:
```
bucket,gross,net,bookings_count
2026-04-01,350000.00,332500.00,2
...
```

### Errores
| Status | Caso |
|---|---|
| 403 | operator (sólo admin) |
| 422 | rango > 366 días |
| 422 | from > to |

---

## GET /api/v1/admin/bookings (resumen para dashboard)

**Auth:** admin/operator. Lista reciente paginada para "Bookings que necesitan atención".

### Query params
- `attention_only?: bool` (default false) — filtra pending_payment, expired soon, etc.
- `per_page?: int` (default 10)

### Response 200
```json
{
  "data": [
    {
      "id": 100,
      "booking_number": "BK-2026-...",
      "status": "pending_payment",
      "customer_name": "...",
      "customer_email": "...",
      "tour_name": "...",
      "tour_date_starts_at": "...",
      "travelers_count": 2,
      "total_amount": "240000.00",
      "expires_at": "2026-05-17T11:00:00Z",
      "created_at": "2026-05-17T10:30:00Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

---

## Inertia pages

| Route | Page |
|---|---|
| `/admin/dashboard` | `Admin/Dashboard.vue` (overlapping con la rota `/dashboard` del starter — discutido abajo) |

**Decisión:** `/admin/dashboard` es la ruta admin. La existente `/dashboard` del starter sigue para visión genérica del usuario (post-login redirect default), pero customers logueados terminan ahí también. Si el user es admin/operator → muestra link "Ir al panel admin → /admin/dashboard" en `/dashboard`.

---

## Cambios
- `2026-05-17` — Creación inicial.
