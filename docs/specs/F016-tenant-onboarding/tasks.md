# F016 — Tasks

> Checklist atómico. Cada item se asigna a un rol y se marca al terminar.
> Generado a partir de `plan.md`. Modificaciones se reflejan en ambos.

---

## Pre-requisitos (resueltos)

- [x] D1 — Email único por agencia (`unique:users,email`, no reusar fundador).
- [x] D2 — `config/montree.php`: `onboarding.trial_days = 14`, `default_plan = Professional`.
- [x] D3 — Sin tarjeta en el alta (trial puro; billing en F007).
- [x] D4 — Ampliar slugs reservados con palabras de marca (ver `plan.md §5`).

## F002 delta (`montree-backend-dev` + `montree-spec-updater`)

- [x] `ResolveTenant`: manejar `TenantStatus::Pending` → `Errors/TenantPending`.
- [x] Test en `ResolveTenantMiddlewareTest`: subdominio `pending` → página pendiente.
- [x] Anotar el delta en `docs/specs/F002-tenant-resolution/` (Changelog) y
  `docs/multi-tenancy.md`. → HECHO 2026-08-01 por `montree-spec-updater`: spec.md +
  plan.md de F002 y `docs/multi-tenancy.md` (§2 pending page + §9.1 slugs reservados D4).

## Backend (`montree-backend-dev`)

- [x] `App\Services\Onboarding\OnboardingHandoff` (nonce cache + signed URLs) + unit test.
- [x] `App\Actions\Onboarding\RegisterAgencyAction` (transaccional) + test.
- [x] `App\Actions\Onboarding\ActivateAgencyAction` + test.
- [x] `App\Actions\Onboarding\ClaimAgencyAccessAction` + test.
- [x] `App\Http\Requests\Onboarding\CheckSubdomainRequest`.
- [x] `App\Http\Requests\Onboarding\RegisterAgencyRequest` (+ regla `not_reserved_subdomain`).
- [x] Controllers: `SubdomainAvailabilityController`, `AgencyRegistrationController`,
  `VerifyAgencyController`, `ClaimAgencyController`, `ResendVerificationController`,
  `OnboardingPageController`.
- [x] `App\Http\Resources\Onboarding\AgencyRegistrationResource`.
- [x] `App\Events\AgencyRegistered` + listener (`SendAgencyVerificationEmail`, auto-discovered).
- [x] `App\Notifications\Onboarding\VerifyAgencyEmail` (branded, ShouldQueue).
- [x] Rutas en `routes/api.php` (grupo onboarding, throttles) y `routes/web.php`
  (`/start`, check-email, `verify`/`claim`/`resend` con `signed`).
- [x] Tests feature: register (happy+failures+transacción), availability,
  verify+claim (activación, trial, one-shot nonce, expirado), tenant pending page.
- [x] `php artisan wayfinder:generate`
- [x] `vendor/bin/pint --dirty --format agent`
- [x] `php artisan test --compact --filter=Onboarding` → 21 passed. Suite completa 381 passed.

### Extras no listados explícitamente (necesarios para respetar la constitución)

- `App\Actions\Onboarding\CheckSubdomainAvailabilityAction` + `App\Data\SubdomainAvailability`
  (DTO) + `App\Enums\SubdomainAvailabilityReason`: la lógica del endpoint de
  disponibilidad no puede vivir en el controller. El endpoint devuelve 200 con
  `reason` (incluye `invalid_format`), así que el Form Request es laxo y la Action clasifica.
- `App\Actions\Onboarding\ResendAgencyVerificationAction` + `ResendVerificationRequest`.
- `App\Rules\NotReservedSubdomain` + `SubdomainTenantFinder::RESERVED_SLUGS` /
  `isReservedSlug()` (D4).
- `App\Exceptions\SubdomainTakenException` (409 `SUBDOMAIN_TAKEN` para el race).
- `App\Http\Controllers\Errors\TenantPendingController` + render de Inertia.
- Render de `InvalidSignatureException` en `bootstrap/app.php` (verify → página
  Inertia 403; claim → redirect a login).
- Vista de mail `resources/views/emails/verify-agency.blade.php` (branded).

## Frontend (`montree-frontend-dev`)

- [x] `pages/Onboarding/CreateAgency.vue` (ruta `/start`).
- [x] `pages/Onboarding/CheckEmail.vue`.
- [x] `pages/Onboarding/VerificationExpired.vue`.
- [x] `pages/Errors/TenantPending.vue`.
- [x] `organisms/AgencySignupForm.vue`, `molecules/SubdomainField.vue`.
- [x] `composables/useSubdomainAvailability.ts` (debounce + fetch).
- [x] `types/onboarding.types.ts`.
- [x] POST del alta vía `useApi`/fetch (NO `router.post` a `/api/v1/*`); navegación
  con `router.visit`. URLs vía Wayfinder.
- [x] Estados: loading, error por campo, disponibilidad (idle/checking/ok/taken).
- [x] Validación frontend espejo de `RegisterAgencyRequest`.
- [x] Entry point al alta desde el landing (`Landing.vue`, host plataforma: header,
  hero, pricing y CTA final → `/start` vía Wayfinder).
- [x] `npm run types:check` (solo 6 errores preexistentes en AppHeader/Notifications).
- [x] `npm run lint && npm run format` (mis archivos: 0 errores; build Vite OK).
- [~] Probar en navegador: alta → email → verify → auto-login. Verificación estática
  completa (build + manifest + los 4 pages compilan). Render HTTP en vivo bloqueado
  por mismatch de PHP en el server de Valet (8.1 vs 8.4 requerido); no es del frontend.

## DB (`montree-db-architect`)

- [ ] **N/A** — sin cambios de schema (confirmado).

## Review (`montree-reviewer`)

- [ ] Tests pasan / Pint / types:check / ESLint
- [ ] Spec cubierta 100% (incluye delta F002 de `pending`)
- [ ] Constitución respetada (Form Request para input, Action por caso de uso,
  controllers < 10 líneas, sin N+1, Wayfinder, sin `router.post` a API)
- [ ] Handoff cross-host: nonce realmente one-shot; firma validada
- [ ] Aislamiento: el alta NO depende de un tenant actual; el claim valida membresía
- [ ] Reporte final go/no-go

---

## Bloqueos / Decisiones pendientes

- [ ] D1–D4 (arriba) deben cerrarse antes de arrancar backend.

## Notas durante implementación

- `2026-06-04` (spec): set creado. Sin migraciones. Depende de cerrar D1–D4 y del
  delta de `pending` en F002.
- `2026-08-01` (backend): capa backend completa. 21 tests de onboarding + 1 nuevo
  en `ResolveTenantMiddlewareTest`. Suite completa 381/381 verde. Pint limpio.
  Wayfinder regenerado. Decisiones/hallazgos:
  - **Config D2:** `montree.onboarding.default_plan` guarda la instancia enum
    `TenantPlan::Professional` (compatible con `config:cache` en L11+). `RegisterAgencyAction`
    crea el tenant `pending` con ese plan; `ActivateAgencyAction` re-lee `trial_days`
    y `default_plan` al activar (idempotente) y setea `trial_ends_at = now + trial_days`.
  - **Handoff cross-host:** `OnboardingHandoff` firma el URL de `claim` forzando el
    root al subdominio del tenant preservando scheme+puerto del request (patrón de
    `LoginResponse::hostUrl`), así no rompe en dev con `:8000`. Nonce = `Str::random(64)`
    en cache TTL 15 min, one-shot (borrado al consumir). Cubierto por unit test.
  - **Firma inválida:** el `signed` middleware lanza `InvalidSignatureException`;
    se rendea distinto por ruta en `bootstrap/app.php` (verify → Inertia
    `Onboarding/VerificationExpired` 403; claim → redirect a `login`). Se devuelve
    `null` para no afectar el resto de rutas firmadas.
  - **Availability 200 con reason:** contrato exige 200 incluso para formato
    inválido, por eso el Form Request NO valida el regex; lo hace la Action y
    devuelve `reason ∈ null|taken|reserved|invalid_format`.
  - **Test gotcha:** las páginas Inertia nuevas (`Onboarding/*`, `Errors/TenantPending`)
    no están en el manifest de Vite todavía. El test de página pendiente usa el
    header `X-Inertia` (la respuesta viene de middleware, no toca Vite); el de firma
    expirada usa `withoutVite()` + `assertInertia(..., false)` porque la respuesta se
    rendea vía exception handler (pasa por `HandleInertiaRequests`).
  - **PENDIENTE (otros agentes):** anotar el delta de `pending` en F002 y
    `docs/multi-tenancy.md` (→ `montree-spec-updater`); frontend de las páginas Vue
    (→ `montree-frontend-dev`).
