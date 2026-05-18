# F005 — Contratos de API

## GET /api/v1/tours/{slug}

**Auth:** none. **Rate limit:** 60/min.

Tour `active` con todo lo necesario para PDP.

### Response 200
```json
{
  "data": {
    "id": 1,
    "slug": "senderismo-cocora",
    "name": "Senderismo Cocora",
    "short_description": "...",
    "description": "...",
    "base_price": "120000.00",
    "currency": "COP",
    "duration_hours": 6,
    "difficulty": "moderate",
    "default_capacity": 12,
    "category": { "id": 1, "name": "Senderismo", "slug": "senderismo" },
    "rating_average": "4.7",
    "rating_count": 9,
    "rating_distribution": { "5": 6, "4": 2, "3": 1, "2": 0, "1": 0 },
    "images": [
      { "id": 1, "url": "...", "is_cover": true, "alt_text": null, "display_order": 0 }
    ],
    "itinerary": [
      { "step_number": 1, "title": "...", "description": "...", "duration_label": "30 min" }
    ],
    "requirements": ["..."],
    "includes": ["..."],
    "meeting_point": "Plaza Cocora",
    "meeting_latitude": 4.6371,
    "meeting_longitude": -75.5096,
    "future_dates": [
      {
        "id": 42, "starts_at": "2026-05-20T07:00:00Z", "ends_at": "2026-05-20T13:00:00Z",
        "price_override": null, "effective_price": "120000.00",
        "capacity_total": 12, "capacity_booked": 5, "available_seats": 7,
        "is_full": false, "status": "open"
      }
    ],
    "is_favorite": false
  }
}
```

### Errores
| Status | Caso |
|---|---|
| 404 | Slug no existe o tour `archived`/`draft` |

## GET /api/v1/tours/{slug}/reviews

Solo reviews `approved`. Paginated 10.

### Response 200
```json
{ "data": [ { "id": 1, "rating": 5, "title": "...", "body": "...", "author_name": "Juan", "created_at": "...", "admin_response": "Gracias!" | null, "admin_responded_at": "..." | null } ], "links": {...}, "meta": {...} }
```

## POST /api/v1/favorites

**Auth:** required (cualquier rol).
Toggle favorite del user actual sobre un tour.
### Request
```json
{ "tour_id": 1 }
```
### Response 200
```json
{ "data": { "tour_id": 1, "is_favorite": true } }
```

## Inertia page

| Route | Page |
|---|---|
| `/tours/{slug}` | `TourDetail.vue` |
| `/favorites/toggle` (POST) | redirige back con `is_favorite` flash |

## Eventos
Sin side-effects nuevos.
