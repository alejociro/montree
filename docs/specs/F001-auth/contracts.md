# F001 — Contratos

> Auth se ejecuta a través de **Fortify** (rutas default del starter). Lo que
> documentamos acá son los shapes Inertia y modificaciones de comportamiento
> por tenant context.

---

## Rutas de Fortify usadas (sin cambios)

```
GET   /login                    -> Inertia 'auth/Login'
POST  /login                    -> AuthenticatedSessionController@store
POST  /logout
GET   /register                 -> Inertia 'auth/Register'
POST  /register                 -> RegisteredUserController@store
GET   /forgot-password          -> Inertia 'auth/ForgotPassword'
POST  /forgot-password
GET   /reset-password/{token}   -> Inertia 'auth/ResetPassword'
POST  /reset-password
GET   /verify-email             -> Inertia 'auth/VerifyEmail'
GET   /verify-email/{id}/{hash} -> EmailVerificationController
POST  /email/verification-notification
```

## Comportamiento por tenant

**Pre-requisito común:** todas las rutas anteriores corren tras `ResolveTenant` (F002). Si no hay tenant resuelto → 404 (`TenantNotFound`). Si suspendido → 503 (`TenantSuspended`).

### Registro
- Recibe: `name`, `email`, `password`, `password_confirmation`.
- Crea `User` global. Si el email YA existe globalmente, devuelve 422 con mensaje genérico "Las credenciales no son válidas" (no revela si existe).
- Asocia al `Tenant::current()` con `tenant_user.status = active`, `joined_at = now()`, `joined_via = registration`.
- Asigna rol `customer` con `setPermissionsTeamId($tenant->id)`.
- Dispara `Registered` event (Fortify default) → envía verification email con branding del tenant.
- Loguea automáticamente al usuario.
- Redirect a `/dashboard` (Inertia).

### Login
- Recibe: `email`, `password`, `remember`.
- Rate limit: 5/min por `(email, IP)`. Excedido → 429 con `Retry-After`.
- Credenciales válidas:
  - Si `tenant_user` ya existe con `status=active` → permitir.
  - Si `tenant_user` existe con `status=suspended` → 403 con `error_code: ACCOUNT_SUSPENDED_IN_TENANT`.
  - Si `tenant_user` no existe → crear con rol `customer`, `joined_via=login`.
- Sesión cookie set. Redirect a `intended()` o `/dashboard`.

### Forgot password
- Recibe: `email`.
- SIEMPRE responde 200 (no revela existencia).
- Si existe, envía email con token vía Fortify default. Email con branding del tenant.

### Reset password
- Recibe: `token`, `email`, `password`, `password_confirmation`.
- Token válido (60min): actualiza password, loguea, redirect a `/dashboard`.
- Token inválido/expirado: 422 con mensaje pidiendo solicitar otro.

### Verify email
- Click en link `/verify-email/{id}/{hash}` con firma válida → marca `email_verified_at` y redirect a `/dashboard?verified=1`.
- Link expirado o firma inválida → 403.

### Logout
- Invalida sesión. Redirect a `/` (Welcome del tenant).

---

## Inertia shared props extras (después del login)

Ya inyectados por `HandleInertiaRequests` (de F002), pero `auth.user` se enriquece:

```ts
auth: {
  user: {
    id: number,
    name: string,
    email: string,
    email_verified_at: string | null,
    avatar_path: string | null,
    avatar_url: string | null,
    phone: string | null,
    // role en el tenant actual (resuelto vía spatie/permission con team_id):
    tenantRole: 'admin' | 'operator' | 'guide' | 'customer' | null,
    isSuperAdmin: boolean
  } | null
}
```

`tenantRole` es `null` si el usuario NO tiene relación con el tenant actual (caso edge: super_admin navegando como visitante en un tenant sin estar afiliado).

---

## Errores comunes

| Status | Caso | error_code |
|---|---|---|
| 422 | Validación (registro/reset) | — |
| 403 | Cuenta suspendida en tenant | `ACCOUNT_SUSPENDED_IN_TENANT` |
| 403 | Email no verificado + intentar acción que requiere verified | `EMAIL_NOT_VERIFIED` |
| 429 | Rate limit login | `Retry-After` header |

---

## Verification email — Notification

`App\Notifications\TenantAwareVerifyEmail` (extiende `VerifyEmail` de Laravel):
- Subject: `"Verifica tu cuenta en {tenantName}"` (locale del tenant).
- Cuerpo: usa colores del tenant (logo + primary_color). Markdown template `mail.verify-email.tenant`.
- Link expira en 60 min (default).
- Override en `User::sendEmailVerificationNotification()` para enviar la nueva clase.

---

## Cambios al contrato

- `2026-05-17` — Creación inicial.
