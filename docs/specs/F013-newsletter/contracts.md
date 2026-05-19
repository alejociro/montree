# F013 — Contratos

## POST /api/v1/newsletter/subscribe
**Auth:** none.
### Request
```json
{ "email": "user@example.com", "name": "John" | null }
```
### Validación
- `email`: required, email, max:255; unique en `newsletter_subscribers` scoped tenant
- `name`: nullable, string, max:120

### Response 201
```json
{ "data": { "email": "...", "subscribed_at": "..." } }
```

### Errores
| Status | Caso | error_code |
|---|---|---|
| 409 | email ya suscrito + activo | `NEWSLETTER_ALREADY_SUBSCRIBED` |
| 422 | email inválido | — |

Side-effect: envía email de bienvenida con branding del tenant (queue).

## GET /unsubscribe/{token}
Página Inertia pública `Newsletter/Unsubscribe.vue` que muestra confirmación + botón "Confirmar baja". POST a `/api/v1/newsletter/unsubscribe` con el token.

## POST /api/v1/newsletter/unsubscribe
**Auth:** none. Requiere token firmado.
### Request
```json
{ "token": "signed-token" }
```
### Response 200
```json
{ "data": { "status": "unsubscribed" } }
```

## GET /api/v1/admin/newsletter/subscribers
**Auth:** admin.
Listado paginado con count total.
### Response
```json
{ "data": [ { "id":1, "email":"...", "name":"...", "status":"active", "subscribed_at":"..." } ], "links":{}, "meta":{ "total_active": 234 } }
```

## POST /api/v1/admin/newsletter/send
**Auth:** admin.
### Request
```json
{ "subject": "...", "body_html": "...", "preview_text": "..." | null }
```
### Validación
- `subject`: required, max:200
- `body_html`: required, max:50000
- subscribers > 0 → si 0, 422 `NEWSLETTER_NO_RECIPIENTS`

### Response 202
```json
{ "data": { "queued_count": 234 } }
```

Side-effect: encola un Notification batch (un job por subscriber con throttle).

## Inertia pages
| Route | Page |
|---|---|
| `/admin/newsletter` | `Admin/Newsletter/Index.vue` (subscribers + composer) |
| `/unsubscribe/{token}` | `Newsletter/Unsubscribe.vue` |

## Eventos
- `NewsletterSubscribed` → listener envía bienvenida (queue).
- `NewsletterSent` → log para admin.
