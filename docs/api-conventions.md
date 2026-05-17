# API — Convenciones

## 1. Versionado y prefijo

- Prefijo: `/api/v1/...`
- Versionar solo por breaking change (cambio de shape, eliminación de campo). Adición de campo opcional NO es breaking.
- Subdominio resuelve tenant; la ruta no lleva tenant_id.

## 2. Verbos y rutas

- RESTful por defecto.
- Plural snake_case en path: `/api/v1/tours`, `/api/v1/tour-dates`.
- IDs en path: `/api/v1/bookings/{booking_number}` (usar identificador público, no PK interna).
- Acciones no-CRUD: subrecurso con verbo: `POST /api/v1/bookings/{id}/cancel`.

## 3. Response shape (estándar)

### Éxito (single)
```json
{
  "data": { ... },
  "meta": { "timestamp": "2026-05-17T10:00:00Z" }
}
```

### Éxito (collection)
```json
{
  "data": [...],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "from": 1, "to": 15, "total": 42, "per_page": 15 }
}
```

### Error
```json
{
  "message": "Texto traducido y user-friendly",
  "errors": { "field": ["mensaje 1", "mensaje 2"] },
  "error_code": "BOOKING_OUT_OF_WINDOW"
}
```

- `message` siempre presente.
- `errors` solo en 422.
- `error_code` opcional, en MAYÚSCULAS_SNAKE, usado para errores de dominio que el frontend distingue.

## 4. Status codes

| Código | Cuándo |
|---|---|
| 200 | OK con body |
| 201 | Created (POST exitoso) |
| 204 | OK sin body (DELETE, algunas PATCH) |
| 400 | Request malformado |
| 401 | No autenticado |
| 403 | Autenticado pero sin permiso / regla de negocio violada |
| 404 | Recurso no existe (o no en este tenant) |
| 409 | Conflicto (duplicado, estado inválido) |
| 422 | Validación falló |
| 429 | Rate limit |
| 500 | Bug |

## 5. Autenticación

- **Sanctum SPA**: cookie `XSRF-TOKEN` + `laravel_session`. Sin tokens Bearer.
- Endpoint `GET /sanctum/csrf-cookie` antes de cualquier mutación.
- Frontend Inertia ya lo maneja por defecto.

## 6. Paginación

- Default 15 por página.
- Máximo 100 por página (validar query param `per_page`).
- Cursor pagination para endpoints de scroll infinito (notifications, reviews).

## 7. Filtros y ordenamiento

- Filtros vía query params planos: `?status=active&category=hiking`.
- Búsqueda: `?search=cocora` (full-text si la tabla lo soporta).
- Orden: `?sort=created_at&direction=desc`. Whitelistear columnas.

## 8. Idempotencia

- Endpoints de pago aceptan header `Idempotency-Key` (UUID). Si llega 2 veces el mismo, devuelve la misma respuesta.
- Webhooks de Stripe son idempotentes por `gateway_payment_id`.

## 9. Rate limiting

- Default Laravel: 60 req/min por user/IP.
- Login: 5 intentos/min.
- Webhooks: sin rate limit (validar firma).

## 10. Inertia + API

- Rutas Inertia (`Route::inertia()` o `return Inertia::render(...)`) NO devuelven JSON, devuelven HTML + props.
- Rutas API en `routes/api.php` con prefijo `/api/v1`.
- Wayfinder genera funciones tipadas para AMBAS.
