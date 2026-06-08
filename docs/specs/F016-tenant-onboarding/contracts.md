# F016 — Contratos de API

> Shapes exactos de request y response. Es CONTRATO: backend y frontend
> se basan en este archivo. Modificar requiere acuerdo de ambos lados.
>
> Todos los endpoints de onboarding viven en el **host de plataforma** (reservado,
> sin tenant): `montree.app` / `montree.test`. El `claim` corre en el subdominio.

---

## GET /api/v1/onboarding/subdomain-availability

**Auth:** none
**Permission:** N/A
**Rate limit:** `throttle:30,1`

### Request

Query string:

| Campo | Tipo | Reglas |
|---|---|---|
| `slug` | string | required, lowercase, `^[a-z0-9][a-z0-9-]{1,62}$` |

### Response 200

```json
{
  "slug": "eco-adventures",
  "available": true,
  "reason": null
}
```

`reason` ∈ `null | "taken" | "reserved" | "invalid_format"` cuando `available=false`.

---

## POST /api/v1/onboarding/agencies

**Auth:** none
**Permission:** N/A
**Rate limit:** `throttle:5,1`

### Request

```json
{
  "agency_name": "Eco Adventures",
  "subdomain": "eco-adventures",
  "founder_name": "Ana Gómez",
  "email": "ana@eco.com",
  "password": "••••••••",
  "password_confirmation": "••••••••"
}
```

**Validación:**
| Campo | Tipo | Reglas |
|---|---|---|
| `agency_name` | string | required, max:255 |
| `subdomain` | string | required, lowercase, regex slug, `unique:tenants,slug`, no reservado |
| `founder_name` | string | required, max:255 |
| `email` | string | required, email, max:255, `unique:users,email` (ver D1) |
| `password` | string | required, confirmed, reglas de `PasswordValidationRules` |

### Response 201

```json
{
  "data": {
    "tenant": {
      "slug": "eco-adventures",
      "domain": "eco-adventures.montree.app",
      "status": "pending"
    },
    "next": "verify_email",
    "email": "ana@eco.com"
  }
}
```

### Errores

| Status | Caso | error_code | Mensaje |
|---|---|---|---|
| 422 | Validación falló | — | mensajes por campo |
| 422 | Subdominio reservado | — | "Ese subdominio no está disponible." |
| 409 | Subdominio tomado (race) | `SUBDOMAIN_TAKEN` | "Ese subdominio ya fue reclamado." |

---

## GET /onboarding/verify/{tenant}/{user}  (web, signed)

**Auth:** none (la firma ES la autorización)
**Host:** plataforma
**Firma:** `signed` middleware (Laravel signed URL, expira en 60 min)

Efecto (idempotente sobre email ya verificado):
1. Marca `users.email_verified_at` si falta.
2. Activa el tenant: `status = active`, `trial_ends_at = now + TRIAL_DAYS` (solo si
   estaba `pending`).
3. Genera un **nonce de un solo uso** (cache, TTL 15 min) y construye un signed
   URL absoluto al subdominio: `GET https://{slug}.montree.app/onboarding/claim`.
4. `redirect()->away()` a ese URL.

| Status | Caso | Resultado |
|---|---|---|
| 302 | Firma válida | redirect a `claim` en el subdominio |
| 403 | Firma inválida/expirada | Inertia `Onboarding/VerificationExpired` |

---

## GET /onboarding/claim  (web, signed, en el subdominio)

**Auth:** none → produce sesión
**Host:** subdominio del tenant
**Firma:** `signed` + nonce válido en cache

Efecto:
1. Valida firma y consume el nonce (one-shot; si ya fue usado → `/login`).
2. Verifica que el `user` sea miembro `active` del tenant resuelto.
3. `Auth::guard('web')->login($user)` → crea sesión host-scoped en el subdominio.
4. `redirect('/admin/dashboard')`.

| Status | Caso | Resultado |
|---|---|---|
| 302 | OK | login + redirect a `/admin/dashboard` |
| 302 | Nonce usado/expirado | redirect a `/login` con mensaje |
| 403 | Firma inválida | redirect a `/login` |

---

## POST /onboarding/resend-verification  (web)

**Auth:** none
**Rate limit:** `throttle:3,10` (3 cada 10 min por email+IP)

### Request

```json
{ "email": "ana@eco.com" }
```

Respuesta siempre 302 con flash neutro ("Si la cuenta existe, te reenviamos el
email") — no revela si el email existe.

---

## Eventos / Side-effects

- Al crear la agencia se dispara `AgencyRegistered` que: encola la notificación
  de verificación al fundador y (opcional) notifica al super_admin.
- La activación reusa la lógica de `TenantUpdated` para invalidar la cache de
  configuración del tenant (ver F002 `InvalidateTenantCache`).

---

## Cambios al contrato

- `2026-06-04` — Creación inicial.
