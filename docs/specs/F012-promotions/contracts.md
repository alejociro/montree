# F012 — Contratos de API

## GET /api/v1/admin/promotions
**Auth:** admin. Lista paginada con filtros opcionales (`status=active|inactive|expired`, `search=code`).

### Response
```json
{
  "data": [
    {
      "id": 1, "code": "VERANO2026", "type": "percentage", "value": "10.00",
      "max_discount": null, "min_amount": null,
      "starts_at": "2026-05-01T00:00:00Z", "ends_at": "2026-06-30T23:59:59Z",
      "max_uses": 100, "uses_count": 17, "max_uses_per_user": 1,
      "is_active": true, "is_expired": false, "is_exhausted": false,
      "applicable_tours": [1, 5], "created_at": "..."
    }
  ], "links": {...}, "meta": {...}
}
```

## POST /api/v1/admin/promotions
**Auth:** admin.
### Request
```json
{
  "code": "VERANO2026",
  "type": "percentage" | "fixed",
  "value": "10.00",
  "max_discount": "50000.00" | null,
  "min_amount": "100000.00" | null,
  "starts_at": "2026-05-01T00:00:00Z",
  "ends_at": "2026-06-30T23:59:59Z",
  "max_uses": 100,
  "max_uses_per_user": 1,
  "is_active": true,
  "applicable_tours": [1, 5] | null
}
```
### Validación
- `code`: required, max:40, uppercase normalized, unique per tenant
- `type`: required, in:percentage,fixed
- `value`: required, decimal > 0; if percentage ≤ 100
- `starts_at`/`ends_at`: required, ends_at > starts_at
- `max_uses`: nullable, integer min:1
- `applicable_tours.*`: exists:tours,id scoped tenant

### Errores
| Status | Caso | error_code |
|---|---|---|
| 409 | code duplicado en tenant | `PROMOTION_CODE_TAKEN` |
| 422 | validación | — |

## PUT /api/v1/admin/promotions/{id}
Patch. Code editable solo si `uses_count = 0`. Si ya usada → 422 `PROMOTION_CODE_LOCKED`.

## DELETE /api/v1/admin/promotions/{id}
Soft "deactivate" (set is_active=false). Si `uses_count > 0` → mantiene historia, sólo desactiva. Si `uses_count = 0` → hard delete.

## POST /api/v1/promotions/validate
**Auth:** required.
### Request
```json
{ "code": "VERANO2026", "tour_date_id": 42, "subtotal": "120000.00" }
```
### Response 200
```json
{ "data": { "promotion_id": 1, "code": "VERANO2026", "discount": "12000.00", "total_after": "108000.00" } }
```
### Errores
| Status | Caso | error_code |
|---|---|---|
| 404 | code inválido | `PROMOTION_NOT_FOUND` |
| 422 | expirado | `PROMOTION_EXPIRED` |
| 422 | agotado | `PROMOTION_EXHAUSTED` |
| 422 | inactivo | `PROMOTION_INACTIVE` |
| 422 | subtotal < min_amount | `PROMOTION_MIN_AMOUNT_NOT_MET` |
| 422 | tour no aplica | `PROMOTION_TOUR_NOT_APPLICABLE` |
| 422 | usuario excedió max_uses_per_user | `PROMOTION_USER_LIMIT_REACHED` |

## Inertia page
| Route | Page |
|---|---|
| `/admin/promotions` | `Admin/Promotion/Index.vue` |

(MVP sin Create/Edit page separadas — usar Dialog modal en Index.)

## Eventos
Sin eventos cross-feature en F012 (F006 usa el endpoint validate).
