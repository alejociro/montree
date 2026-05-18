# F001 — Plan técnico

## 1. Resumen

Extender Fortify (ya configurado en el starter) para que el registro y login asocien al usuario con el tenant actual vía pivote `tenant_user` + rol `customer` de spatie/permission. Custom `CreateNewUser` action, `LoginResponse` custom, `EnsureTenantMembership` middleware para el login, y `TenantAwareVerifyEmail` notification con branding. Frontend: enriquecer páginas auth del starter con branding del tenant.

## 2. Backend

### Fortify actions custom (`app/Actions/Fortify/`)

- **`CreateNewUser`** (ya existe): extender para `User::firstOrCreate(...)` (si email existe globalmente, devolver el ya creado) + asociar al tenant + asignar rol `customer`.
- **`UpdateUserProfileInformation`** (ya viene de starter): sin cambios.
- **Nueva `EnsureTenantMembership`** (no Fortify contract): listener del evento `Login` o middleware después del login que asegura la relación `tenant_user`.

### Login response (`app/Http/Responses/`)

- **`LoginResponse`** implementa `Laravel\Fortify\Contracts\LoginResponse`: chequea `tenant_user.status`. Si `suspended` → invalida sesión, redirect con error. Si OK → asocia si falta + redirect a intended.
- Registrar binding en `AppServiceProvider` o `FortifyServiceProvider`.

### Service (`app/Services/Tenant/`)

- **`AttachUserToTenant`** — service stateless que toma `User + Tenant + Role + joined_via`, hace `firstOrCreate` en `tenant_user`, sincroniza rol con `setPermissionsTeamId`. Reusable por register, login, y futuras invitaciones.

### Notification (`app/Notifications/Auth/`)

- **`TenantAwareVerifyEmail`** extiende `Illuminate\Auth\Notifications\VerifyEmail`. Override `toMail()` para usar template con branding.
- **`TenantAwareResetPassword`** análogo para reset.
- Override en `User::sendEmailVerificationNotification()` y `sendPasswordResetNotification()`.

### Mail templates (`resources/views/emails/`)

- `verify-email.blade.php` — markdown con `$tenantName`, `$primaryColor`, `$verificationUrl`, `$tenantLogo`.
- `reset-password.blade.php` — análogo.
- Layout común `auth-base.blade.php` con branding inyectado por slot.

### Rate limiting

- En `App\Providers\AppServiceProvider::boot()` registrar `RateLimiter::for('login', fn ($req) => Limit::perMinute(5)->by($req->input('email').'|'.$req->ip()))`.
- Aplicar al endpoint `POST /login` (Fortify lee `config('fortify.limiters.login')`).

### Suspended account flow

- Si después de login se detecta `tenant_user.status === suspended`:
  - `Auth::logout()`, invalidar sesión.
  - `back()->withErrors(['email' => 'Tu cuenta ha sido suspendida en esta agencia.'])`.
  - HTTP 302 con flash error (frontend lo muestra como toast/Alert).

### Override de User para verification/reset notifications

- `User::sendEmailVerificationNotification()` → `$this->notify(new TenantAwareVerifyEmail());`
- `User::sendPasswordResetNotification($token)` → análogo.

### Configuración de Fortify

- `config/fortify.php`: el array `features` ya tiene registration + resetPasswords + emailVerification + 2FA. Sin cambios.
- `config/fortify.php`: `home` redirige a `/dashboard`. Sin cambios.
- `config/fortify.php`: `limiters.login` debe ser `'login'` (matchear el RateLimiter registrado).

### Inertia shared `auth.user` enriquecido

- Modificar `HandleInertiaRequests::share()` para reemplazar `auth.user` con un shape que incluye `tenantRole` (resuelto vía `setPermissionsTeamId` + `getRoleNames()->first()`), `isSuperAdmin`, `avatar_url` derivado.

## 3. Frontend

### Pages (`resources/js/pages/auth/`)

Las pages ya existen del starter (`Login.vue`, `Register.vue`, etc.). Vamos a:
- **Reemplazar** el logo genérico del starter por el logo del tenant (composable `useTenant`).
- **Aplicar branding**: la paleta ya viene de `useTenantBranding`, así que botones, links y headers usan `--primary` automáticamente.
- **Mostrar nombre del tenant** en el header de las pages.
- **Tagline del tenant** como subtítulo en Login/Register.
- **Error suspended** (toast/Alert) cuando llega `errors.email` con cierto mensaje.

### Layout

- `AuthSimpleLayout.vue` ya existe (del starter). Verificar que tiene branding. Si no, extenderlo.

### Tipos

- Extender `auth.user` en `global.d.ts` con campos nuevos (`tenantRole`, `isSuperAdmin`, `avatar_url`, `email_verified_at`, `phone`).

### No nuevas composables ni atoms

- Reusar `useTenant`, `useTenantBranding`, `useForm` de Inertia.

## 4. Tests

### Feature tests

- `tests/Feature/Auth/RegisterTest.php`
  - `test_register_creates_user_and_attaches_to_tenant_as_customer`
  - `test_register_with_existing_email_returns_422_generic`
  - `test_register_with_invalid_data_returns_422`
  - `test_register_sends_verification_email`
  - `test_register_logs_user_in`

- `tests/Feature/Auth/LoginTest.php`
  - `test_login_with_valid_credentials_succeeds`
  - `test_login_creates_tenant_user_when_missing_with_customer_role`
  - `test_login_rejects_suspended_membership_with_403_message`
  - `test_login_invalid_credentials_returns_422`
  - `test_login_rate_limit_5_per_minute`

- `tests/Feature/Auth/PasswordResetTest.php`
  - `test_forgot_password_always_returns_200_for_security`
  - `test_reset_password_with_valid_token_updates_password`
  - `test_reset_password_with_expired_token_fails`

- `tests/Feature/Auth/EmailVerificationTest.php`
  - `test_unverified_user_can_login_and_access_unprotected_pages`
  - `test_verify_email_route_marks_user_verified`
  - `test_resend_verification_email_works`

- `tests/Feature/Auth/InertiaAuthUserPropTest.php`
  - `test_authenticated_user_inertia_props_include_tenant_role`
  - `test_super_admin_props_have_isSuperAdmin_true`
  - `test_user_without_tenant_relation_has_tenantRole_null`

### Unit tests

- `tests/Unit/Services/AttachUserToTenantTest.php` — varios casos.

## 5. Decisiones tomadas

- **CreateNewUser usa `firstOrCreate`**: si el email existe globalmente, devolvemos el user existente y solo creamos la relación tenant_user. Para el outside-world response sigue siendo "usuario creado" — no revelamos. Riesgo: si las contraseñas no coinciden, el login fallaría después. Mitigación: el registro NO actualiza password del existente.
- **Custom email notifications en vez de Fortify Markdown defaults**: necesitamos branding del tenant en el subject y el body. Override en User es el patrón Laravel.
- **No API JSON endpoints custom**: usamos Fortify default vía Inertia. Si hace falta API headless después, abrir RFC.
- **Suspended membership = logout + error**: en vez de bloquear pre-login (que requeriría 1 query extra), aceptamos el login y desconectamos en `LoginResponse`. Simplifica el flujo.

## 6. Riesgos y mitigaciones

| Riesgo | Probabilidad | Mitigación |
|---|---|---|
| Email enviado sin branding (porque `currentTenant` no propaga a queue) | media | Renderizar template síncrono o pasar tenant data al Notification constructor |
| Rate limiter mal configurado deja brecha | baja | Test específico `test_login_rate_limit_5_per_minute` |
| `firstOrCreate` race condition crea duplicados | baja | DB unique constraint en `users.email` + capture exception |
| Usuario super_admin sin pivote en tenant rompe shared props | media | `tenantRole: null` aceptable + test |

## 7. Out of scope explícito

- **OAuth social** (Google, GitHub) — futuro.
- **2FA forced** — Fortify ya soporta opt-in 2FA del starter, mantener tal cual.
- **Passkeys** — el starter trae soporte vía `laravel/passkeys`, no tocar en F001.
- **Magic link login** — futuro.
- **Account deletion flow** — separar en feature aparte si se pide.
- **Welcome email separado** — descartado (single verification+welcome).
