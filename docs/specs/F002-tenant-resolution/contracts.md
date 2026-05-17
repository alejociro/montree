# F002 — Contratos de API

> Shapes exactos. Backend y frontend se basan en este archivo.

---

## GET /api/v1/tenant

**Auth:** none. **Permission:** N/A. **Rate limit:** 60/min.

Devuelve el tenant resuelto por el subdominio actual + su configuración.

### Request

Sin body. El tenant se resuelve del Host header vía `SubdomainTenantFinder`.

### Response 200

```json
{
  "data": {
    "tenant": {
      "id": 1,
      "slug": "demo",
      "name": "Demo Eco Adventures",
      "domain": "demo.montree.test",
      "status": "active",
      "plan": "professional",
      "contact_email": "hello@demo.montree.test",
      "contact_phone": "+57 300 000 0000"
    },
    "configuration": {
      "primary_color": "#16a34a",
      "primary_color_hsl": "142 65% 38%",
      "secondary_color": "#0f766e",
      "secondary_color_hsl": "168 75% 26%",
      "logo_url": null,
      "favicon_url": null,
      "currency": "COP",
      "timezone": "America/Bogota",
      "locale": "es",
      "tagline": "Aventuras inolvidables en Colombia",
      "description": "...",
      "social_links": { "instagram": "https://instagram.com/demo" },
      "contact_info": { "email": "hello@demo.montree.test" },
      "reviews_require_moderation": true,
      "require_traveler_details": true,
      "custom_css": null
    }
  }
}
```

### Errores

| Status | Caso | error_code | Mensaje |
|---|---|---|---|
| 404 | Sin tenant resuelto (subdominio reservado o raíz) | `TENANT_NOT_RESOLVED` | "No tenant for this host." |

---

## PUT /api/v1/admin/tenant

**Auth:** required. **Permission:** `role:admin` en el tenant actual.

Edita campos del tenant. NO incluye configuración (eso es endpoint aparte).

### Request

```json
{
  "name": "Demo Eco Adventures",
  "contact_email": "hello@demo.montree.test",
  "contact_phone": "+57 300 000 0000"
}
```

**Validación:**

| Campo | Tipo | Reglas |
|---|---|---|
| `name` | string | required, max:120 |
| `contact_email` | string | required, email, max:255 |
| `contact_phone` | string | nullable, max:30 |

### Response 200

Mismo shape que GET /api/v1/tenant (`data.tenant`).

### Errores

| Status | Caso | error_code |
|---|---|---|
| 401 | No auth | — |
| 403 | No es admin del tenant actual | `INSUFFICIENT_ROLE` |
| 422 | Validación | — |

---

## PUT /api/v1/admin/tenant/configuration

**Auth:** required. **Permission:** `role:admin` en el tenant actual.

Edita la configuración del tenant actual.

### Request

Todos los campos son opcionales — se aplica patch.

```json
{
  "primary_color": "#15803d",
  "secondary_color": "#0d9488",
  "currency": "COP",
  "timezone": "America/Bogota",
  "locale": "es",
  "tagline": "...",
  "description": "...",
  "social_links": { "instagram": "https://...", "facebook": "https://..." },
  "contact_info": { "email": "...", "phone": "..." },
  "reviews_require_moderation": true,
  "require_traveler_details": true,
  "custom_css": "/* solo si plan=enterprise */"
}
```

**Validación:**

| Campo | Tipo | Reglas |
|---|---|---|
| `primary_color` | string | nullable, regex hex `/^#[0-9A-Fa-f]{6}$/` |
| `secondary_color` | string | nullable, regex hex |
| `currency` | string | nullable, size:3, ISO 4217 whitelist |
| `timezone` | string | nullable, in:lista de timezones PHP |
| `locale` | string | nullable, in:`es`,`en` |
| `tagline` | string | nullable, max:160 |
| `description` | string | nullable, max:2000 |
| `social_links` | array | nullable, keys: `instagram\|facebook\|twitter\|youtube\|tiktok`, values URL |
| `contact_info` | array | nullable, free shape |
| `reviews_require_moderation` | bool | nullable |
| `require_traveler_details` | bool | nullable |
| `custom_css` | string | nullable, max:10000. Solo plan `enterprise`. Sanitizado backend. |

### Response 200

```json
{ "data": { "configuration": { ...full shape... } } }
```

### Errores

| Status | Caso | error_code |
|---|---|---|
| 401 | No auth | — |
| 403 | No es admin del tenant | `INSUFFICIENT_ROLE` |
| 403 | `custom_css` en plan no-enterprise | `FEATURE_REQUIRES_ENTERPRISE` |
| 422 | Validación (incluye CSS sanitizer rechazando props no whitelisted) | — |

---

## Shared Inertia props (todas las pages)

`HandleInertiaRequests::share()` agrega:

```ts
{
  tenant: Tenant | null,
  tenantConfiguration: TenantConfiguration | null,
  flash: { success?: string, error?: string },
  auth: { user: User | null }  // ya existe del starter
}
```

Si el host no resuelve tenant → ambos son `null` y la app renderiza modo plataforma.

---

## Páginas Inertia de error

| Ruta | Página Vue | HTTP status | Cuándo |
|---|---|---|---|
| ANY (tenant no resuelto en subdominio no reservado) | `Errors/TenantNotFound.vue` | 404 | Subdominio escrito mal o tenant no existe |
| ANY (tenant resuelto pero `status=suspended`) | `Errors/TenantSuspended.vue` | 503 | Suspendido por admin global |

Estas páginas se renderizan vía `Inertia::render(..., props)->setStatusCode(...)`.

Props para `TenantSuspended`:
```json
{ "tenantName": "Demo Eco Adventures", "contactEmail": "support@montree.app" }
```

---

## Eventos / Side-effects

- Al actualizar `tenant_configurations` → disparar `TenantConfigurationUpdated` event → listener `InvalidateTenantCache::handle` invalida cache `tenant:{slug}`.
- Al actualizar `tenants` → mismo patrón con `TenantUpdated`.

---

## Sanitizer de `custom_css`

Whitelist de propiedades CSS aceptadas:
```
color, background-color, background-image, background, border, border-color, border-radius,
font-family, font-size, font-weight, line-height, letter-spacing, text-align,
margin, padding, width, height, max-width, max-height,
display, flex, grid, gap, opacity, transition, transform,
--primary, --secondary, --accent, --background, --foreground, --card
```

Reglas:
- Rechazar `@import`, `@font-face` con URLs externas, `expression()`, `javascript:`, `url(data:...)` salvo `data:image/svg+xml`.
- Sin selectores con `*`, `:root`, `body` (limitar a `.tenant-*` y custom properties).
- Validación en backend con regex + parser ligero (sin instalar nuevas deps si se puede; si hace falta, abrir RFC).

---

## Cambios al contrato

- `2026-05-17` — Creación inicial.
