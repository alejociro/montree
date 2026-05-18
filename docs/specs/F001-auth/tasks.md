# F001 — Tasks

## Backend (`montree-backend-dev`)

### Service
- [x] `app/Services/Tenant/AttachUserToTenant.php` — `handle(User $user, Tenant $tenant, UserRole $role, string $joinedVia): void`. syncWithoutDetaching pivot + setPermissionsTeamId + syncRoles. (`joined_via` arg aceptado pero NO persistido — columna no existe en pivot; ver nota abajo)

### Fortify actions
- [x] Modificar `app/Actions/Fortify/CreateNewUser.php`: validación con `Rule::unique` + mensaje genérico `Las credenciales no son válidas.`, después llamar `AttachUserToTenant` con rol customer + joined_via=registration. (NO se usó `firstOrCreate` porque el contrato manda 422 generic si email ya existe — ver nota)

### Custom LoginResponse
- [x] `app/Http/Responses/LoginResponse.php` implementa `Laravel\Fortify\Contracts\LoginResponse`. Chequea membership: si suspended → logout + redirect login con error. Si missing y NO es super_admin → attach customer + joined_via=login. Si OK → redirect intended() ?? '/dashboard'.
- [x] Registrar binding en `App\Providers\FortifyServiceProvider::register()`.
- [x] Extra: `App\Http\Responses\FailedPasswordResetLinkRequestResponse` para que forgot-password con email desconocido devuelva 200 (no email enumeration). Bind también en FortifyServiceProvider.

### Notifications
- [x] `app/Notifications/Auth/TenantAwareVerifyEmail.php` extiende `Illuminate\Auth\Notifications\VerifyEmail`. Snapshot de tenant (name + color + logo) en constructor para queue safety. Factory `fromTenant()`.
- [x] `app/Notifications/Auth/TenantAwareResetPassword.php` extiende `Illuminate\Auth\Notifications\ResetPassword`. Mismo patrón.

### Mail templates
- [x] `resources/views/emails/verify-email.blade.php` — Blade table-based HTML, inline CSS con branding.
- [x] `resources/views/emails/reset-password.blade.php` — análogo.

### User overrides
- [x] `User::sendEmailVerificationNotification(): void` → si hay tenant current, usa `TenantAwareVerifyEmail::fromTenant`; si no, fallback `DefaultVerifyEmail`.
- [x] `User::sendPasswordResetNotification($token): void` → análogo.
- [x] User ahora implementa `Illuminate\Contracts\Auth\MustVerifyEmail` (requisito para que el listener `SendEmailVerificationNotification` de Fortify dispare el email tras `Registered`).

### Rate limiting
- [x] Ya estaba registrado en `FortifyServiceProvider::configureRateLimiting()` (5/min por email+IP). Verificado.
- [x] `config/fortify.php` `'limiters.login' => 'login'` ya estaba OK.

### Inertia shared props enrichment
- [x] Modificado `HandleInertiaRequests::share()` para usar `AuthUserResource`. Shape: `id`, `name`, `email`, `email_verified_at` (ISO 8601 o null), `avatar_path`, `avatar_url`, `phone`, `tenantRole` (string|null), `isSuperAdmin` (bool). `tenantRole` es null si no hay membership o si el user es super_admin.

### Tests (feature)
- [x] `tests/Feature/Auth/RegistrationTest.php` (6 tests — reescrito sobre el existente del starter)
- [x] `tests/Feature/Auth/LoginTest.php` (5 tests, nuevos)
- [x] `tests/Feature/Auth/PasswordResetFlowTest.php` (3 tests, complementa el `PasswordResetTest.php` del starter)
- [x] `tests/Feature/Auth/TenantAwareEmailVerificationTest.php` (3 tests, complementa el `EmailVerificationTest.php` del starter)
- [x] `tests/Feature/Auth/InertiaAuthUserPropTest.php` (3 tests)

### Tests (unit)
- [x] `tests/Unit/Services/AttachUserToTenantTest.php` (4 tests)

### Cierre
- [x] `php artisan wayfinder:generate` — OK
- [x] `vendor/bin/pint --dirty --format agent` — pasó (1 fix automático en TenantAwareResetPassword)
- [x] `php artisan test --compact` — 100/100 tests, 316 assertions, ~3.8s

## Frontend (`montree-frontend-dev`)

### Pages existentes (adaptar)
- [x] `resources/js/pages/auth/Login.vue` — header con logo + nombre del tenant; usa `useTenant`. Si llega `errors.email` con código de suspended, mostrar Alert destacado.
- [x] `resources/js/pages/auth/Register.vue` — header con logo + nombre + tagline.
- [x] `resources/js/pages/auth/ForgotPassword.vue` — header con branding.
- [x] `resources/js/pages/auth/ResetPassword.vue` — header con branding (hereda automático del layout).
- [x] `resources/js/pages/auth/VerifyEmail.vue` — header con branding + CTA "Reenviar email".

### Layout
- [x] `AuthSimpleLayout.vue`, `AuthCardLayout.vue` y `AuthSplitLayout.vue` ahora usan `TenantBrandedLogo` + invocan `useTenantBranding()` + muestran tagline si existe.

### Types
- [x] Extendido `User` en `resources/js/types/auth.ts` con `tenantRole`, `isSuperAdmin`, `avatar_url`, `avatar_path`, `phone`. Removido `created_at`/`updated_at` (no los expone `AuthUserResource`).

### Wiring
- [x] URLs vía Wayfinder (`@/routes/login`, `@/routes/register`, `@/routes/password`, `@/routes/verification`, `@/routes`). Sin hardcoded URLs.
- [x] Validación visual espejo — `InputError` + `Form` slots ya muestran errores de Fortify; Alert dedicado para suspensión.

### Cierre
- [x] `npm run types:check`
- [x] `npm run lint && npm run format`
- [x] `npm run build` debe pasar
- [ ] Probar en navegador: visitar `demo.montree.test/login`, probar register + login + flow de password reset. (pendiente — requiere browser, no verificado en este agente)

## Review (`montree-reviewer`)

- [ ] Tests pasan (suite completa)
- [ ] Pint, types-check, lint, build OK
- [ ] Cobertura: cada acceptance criterion tiene test
- [ ] Sin URLs hardcoded
- [ ] Notifications con branding renderizan correcto
- [ ] Rate limiter verificado con test 429

---

## Bloqueos / Decisiones pendientes

(ninguna abierta — todas las decisiones cerradas en spec.md)

## Notas durante implementación

- `2026-05-17` (claude principal): F001 arrancado. Branch `feature/F001-auth`.
- `2026-05-17` (montree-backend-dev): Backend implementado. Notas de implementación:
  - **`joined_via` no persistido**: la tabla `tenant_user` no tiene esa columna (sólo `status`, `invited_at`, `joined_at`, `suspended_at`). El argumento se acepta en `AttachUserToTenant::handle()` por forward-compat, pero se ignora. Crear migration queda para otro feature (no se modifica schema en backend tasks).
  - **`firstOrCreate` descartado en registro**: el plan original sugería `User::firstOrCreate` para reusar usuario existente al registrarse, pero el contrato + acceptance criterion dicen que email duplicado devuelve 422 con mensaje genérico ("Las credenciales no son válidas"), sin revelar existencia. Resolví favor del contrato. La auto-asociación con un usuario que ya existe en otro tenant ocurre en LOGIN, no en register (también está en contrato).
  - **`User implements MustVerifyEmail`**: necesario para que el listener default `SendEmailVerificationNotification` (que Fortify registra para el evento `Registered`) dispare el email tras registrarse. Sin esto, el listener hace early return.
  - **Super admin sin pivote**: en `LoginResponse`, si el user tiene rol super_admin pero no tiene `tenant_user` row, NO le creamos una membership de customer (sería raro). Sólo se atacha si no es super_admin.
  - **`FailedPasswordResetLinkRequestResponse` extra**: fortify default devuelve 422 cuando el email del forgot-password no existe; el contrato dice 200 siempre (no email enumeration). Implementé el contrato custom.
  - **Suspended membership flow**: detectamos en `LoginResponse::toResponse()` (después que Fortify validó credenciales), hacemos logout + invalidamos sesión + redirect a `login` con error en `email`. Acepta el costo de hacer la query extra después del login para mantener el flujo simple.
  - **Snapshot del tenant en Notification constructor**: necesario para queue safety (si el notification se queuea, el subscriber no tendría acceso a `Tenant::current()`). Pasamos `name`, `primary_color`, `logo_url` ya resueltos.
  - **Tests del starter actualizados**: `RegistrationTest` re-escrito (los antiguos no establecían tenant context y fallaban con los nuevos requisitos). Los otros tests del starter (Authentication, PasswordReset, EmailVerification del starter) siguen pasando sin modificación porque hacen actingAs directo y no requieren tenant.
  - **Total**: 22 tests nuevos (5 archivos feature + 1 unit). Suite completa: 100/100 (antes 78/78).
  - **Pendiente para frontend**: actualizar tipos `auth.user` en `global.d.ts` con shape nuevo (tenantRole, isSuperAdmin, avatar_url, email_verified_at, phone). NO toqué TS/Vue.
