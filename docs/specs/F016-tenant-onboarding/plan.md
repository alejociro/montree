# F016 — Plan técnico

> Decisiones técnicas para implementar este feature.
> Backend, frontend, base de datos, tests.

---

## 1. Resumen

Flujo de alta self-serve que corre en el **host de plataforma** (sin tenant).
Una Action transaccional crea tenant `pending` + configuración default + usuario
fundador (admin, membresía active) y dispara la verificación de email. El link de
verificación activa el tenant e inicia el trial, y hace un **handoff firmado
cross-subdominio** (nonce one-shot en cache) que loguea al fundador en su propio
subdominio — necesario porque la sesión es host-scoped (§10). Sin migraciones.

## 2. Backend

### Modelos

- `Tenant` — sin cambios de schema. Se usa `status` (`pending`→`active`) y
  `trial_ends_at`.
- `TenantConfiguration` — se crea con defaults del modelo/migration existentes.
- `TenantUser` — membresía `active` para el fundador.
- `TenantStatus` enum — ya tiene `Pending` (confirmado por `TenantFactory::pending()`).

### Migrations

- **Ninguna.** Todas las columnas existen.

### Actions

- `App\Actions\Onboarding\RegisterAgencyAction` — dentro de una transacción:
  crea Tenant (pending), TenantConfiguration (defaults), User (fundador), attach
  admin vía `AttachUserToTenant` (reusa F001), dispara `AgencyRegistered`. Devuelve
  el Tenant.
- `App\Actions\Onboarding\ActivateAgencyAction` — marca email verificado, pasa
  tenant a `active` y setea `trial_ends_at` (idempotente). Devuelve signed URL de
  `claim` en el subdominio con nonce en cache.
- `App\Actions\Onboarding\ClaimAgencyAccessAction` — valida+consume nonce, valida
  membresía active, loguea al user en el guard web.

### Form Requests

- `App\Http\Requests\Onboarding\CheckSubdomainRequest` — valida `slug`.
- `App\Http\Requests\Onboarding\RegisterAgencyRequest` — valida todos los campos
  del alta; regla custom `not_reserved_subdomain` que consulta
  `SubdomainTenantFinder::isReservedHost("{slug}.montree.app")` y la lista de
  palabras reservadas (D4).

### Controllers

- `App\Http\Controllers\Api\V1\Onboarding\SubdomainAvailabilityController` (`__invoke`)
- `App\Http\Controllers\Api\V1\Onboarding\AgencyRegistrationController` (`store`)
- `App\Http\Controllers\Onboarding\VerifyAgencyController` (`__invoke`, web signed)
- `App\Http\Controllers\Onboarding\ClaimAgencyController` (`__invoke`, web signed)
- `App\Http\Controllers\Onboarding\ResendVerificationController` (`__invoke`)
- `App\Http\Controllers\Onboarding\OnboardingPageController` — Inertia pages
  (`/start`, check-email).

### Resources

- `App\Http\Resources\Onboarding\AgencyRegistrationResource` — shape de la 201
  (ver `contracts.md`).

### Policies

- N/A — los endpoints son públicos; la autorización es la firma del URL y el nonce.

### Services / DTOs

- `App\Services\Onboarding\OnboardingHandoff` — encapsula generar/validar/consumir
  el nonce en cache y construir los signed URLs (plataforma→subdominio). Razón:
  lógica compartida entre `ActivateAgencyAction` y `ClaimAgencyAccessAction` (regla
  del 3: 2+ Actions la usan).

### Jobs / Notifications / Events

- `App\Events\AgencyRegistered` — payload tenant+founder.
- `App\Notifications\Onboarding\VerifyAgencyEmail` — canal mail; contiene el signed
  URL a `/onboarding/verify/{tenant}/{user}`. Branded con el nombre de la agencia.
- (Opcional) `App\Notifications\SuperAdmin\AgencyRegisteredNotification` — database.

### Rutas

- `routes/api.php` (host plataforma, fuera de grupos con tenant): grupo
  `onboarding` con los 2 endpoints API + throttles.
- `routes/web.php`: `/start` y check-email (Inertia, público); `verify`, `claim`,
  `resend-verification` con middleware `signed` donde aplica.
- **Importante:** `ResolveTenant` debe permitir el host de plataforma (ya lo hace)
  y manejar `pending` para subdominios (ver §6 riesgos).

### Cambio en F002 — manejo de `pending`

`ResolveTenant` hoy solo bloquea `Suspended`. Agregar: si
`tenant->status === Pending` → renderizar `Errors/TenantPending` (Inertia, 503 o
200 según SEO). Documentar en `docs/multi-tenancy.md`. Coordinar con
`montree-spec-updater` para anotar el delta en F002.

## 3. Frontend

### Pages

- `resources/js/pages/Onboarding/CreateAgency.vue` — ruta `/start` (plataforma).
- `resources/js/pages/Onboarding/CheckEmail.vue` — post-alta.
- `resources/js/pages/Onboarding/VerificationExpired.vue`.
- `resources/js/pages/Errors/TenantPending.vue`.

### Composables

- `useSubdomainAvailability()` — debounce + fetch al endpoint de disponibilidad,
  expone `status: 'idle'|'checking'|'available'|'unavailable'` y `reason`.

### Organisms / Molecules / Atoms

- Nuevos: `organisms/AgencySignupForm.vue`, `molecules/SubdomainField.vue`.
- Reutilizar: atoms de formulario existentes, `PasswordField` si existe.

### Types

- `types/onboarding.types.ts` — `SubdomainAvailability`, `AgencyRegistrationPayload`,
  `AgencyRegistrationResponse`.

### Wayfinder

- Tras backend: `php artisan wayfinder:generate`.
- Imports: `@/actions/Api/V1/Onboarding/...`. **El form usa el composable `useApi`
  / fetch para el POST** (NO `router.post` a `/api/v1/*` — regla de la review
  2026-05-19 P0-2). Para navegación post-alta usar `router.visit`.

## 4. Tests

### Feature tests (backend)

- `tests/Feature/Onboarding/RegisterAgencyTest.php`
  - `test_creates_pending_tenant_with_founder_admin_and_sends_verification` (happy)
  - `test_rejects_taken_subdomain` (failure)
  - `test_rejects_reserved_subdomain` (failure)
  - `test_rejects_invalid_password` (failure)
  - `test_does_not_persist_anything_when_validation_fails` (edge / transacción)
- `tests/Feature/Onboarding/SubdomainAvailabilityTest.php`
  - available / taken / reserved / invalid_format
- `tests/Feature/Onboarding/VerifyAndClaimTest.php`
  - `test_verification_activates_tenant_and_starts_trial`
  - `test_verification_redirects_to_signed_claim_on_subdomain`
  - `test_claim_logs_in_founder_and_redirects_to_admin_dashboard`
  - `test_claim_nonce_is_single_use`
  - `test_expired_or_invalid_signature_shows_expired_page`
- `tests/Feature/Onboarding/TenantPendingPageTest.php`
  - subdominio `pending` muestra página de pendiente (no catálogo, no 404)

### Unit tests

- `tests/Unit/Onboarding/OnboardingHandoffTest.php` — generar/validar/consumir nonce.

## 5. Decisiones tomadas

- **Sin migraciones:** el schema ya soporta el flujo. Razón: `tenants` y
  `tenant_configurations` ya tienen status/plan/trial/defaults.
- **Handoff por signed URL + nonce en cache (no columna):** un solo uso, TTL corto.
  Razón: evita schema nuevo y mantiene el token efímero.
- **Verificación reusa `email_verified_at`** del user en vez de un token aparte.
  Razón: una sola fuente de verdad de "email confirmado".
- **D1 — email único por agencia:** `RegisterAgencyRequest` mantiene
  `unique:users,email`; no se reusa un user existente como fundador.
- **D2 — trial/plan:** `config/montree.php` → `onboarding.trial_days = 14`,
  `onboarding.default_plan = TenantPlan::Professional`. `ActivateAgencyAction` lee
  de config (no hardcodear).
- **D3 — sin tarjeta:** el alta no toca Cashier/billing; trial puro.
- **D4 — slugs de marca reservados:** agregar lista de slugs reservados
  (`app`, `blog`, `docs`, `status`, `ayuda`, `soporte`, `help`, `support`, `mail`,
  `static`, `cdn`, `assets`) consultada por la regla `not_reserved_subdomain`,
  junto a los hosts reservados existentes.

## 6. Riesgos y mitigaciones

| Riesgo | Probabilidad | Mitigación |
|---|---|---|
| `ResolveTenant` no maneja `pending` → subdominio nuevo muestra catálogo vacío o 404 | alta | Agregar rama `pending` ANTES de implementar el resto; test dedicado |
| Handoff cross-host falla en dev por puertos (`:8000`) | media | Construir URLs con el puerto del request (igual que `LoginResponse::redirectSuperAdmin`) |
| Email global único choca con fundadores que ya son users (D1) | media | Resolver D1 antes de codear `RegisterAgencyRequest` |
| Nonce en cache se pierde si el driver es `array`/efímero entre requests | baja | Usar el cache driver configurado (file/redis), no `array`; cubrir con test |
| Abuso de altas (spam de tenants) | media | `throttle:5,1` en el POST + verificación de email obligatoria |

## 7. Out of scope explícito

- Selección de plan de pago y captura de tarjeta en el alta (F007).
- Custom domain.
- Purga de tenants `pending` abandonados (comando futuro).
- Importación de datos / wizard multi-paso de branding.
