# F004 — Contratos de API

## GET /api/v1/tours

**Auth:** none (público). **Rate limit:** 60/min.

Lista pública de tours `active` del tenant actual con filtros + paginación.

### Query params
- `search?: string` — title/description (full-text-like)
- `category?: string` (slug)
- `difficulty?: 'easy'|'moderate'|'hard'|'expert'`
- `price_min?: number`, `price_max?: number`
- `sort?: 'price_asc'|'price_desc'|'rating_desc'|'newest'|'next_date_asc'` (default `next_date_asc`)
- `per_page?: int` (default 12, max 48)
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
      "base_price": "120000.00",
      "currency": "COP",
      "duration_hours": 6,
      "difficulty": "moderate",
      "default_capacity": 12,
      "category": { "id": 1, "name": "Senderismo", "slug": "senderismo" },
      "cover_image_url": "https://...",
      "rating_average": "4.7",
      "rating_count": 9,
      "next_date_starts_at": "2026-05-20T07:00:00Z",
      "has_future_dates": true,
      "is_favorite": false
    }
  ],
  "links": {...},
  "meta": {...}
}
```

`is_favorite` solo si user autenticado (sino siempre `false`).

## GET /api/v1/tours/categories

Devuelve `[{id, slug, name, icon, tours_count}]` para los filtros del catálogo. Solo categorías activas con al menos 1 tour activo.

## Inertia page

| Route | Page |
|---|---|
| `/tours` (raíz del tenant — opcional usar `/` también) | `Catalog.vue` |

Props deferred: `tours` (lista), `categories`. Loading skeleton inicial.

## Eventos
Sin side-effects backend nuevos.
