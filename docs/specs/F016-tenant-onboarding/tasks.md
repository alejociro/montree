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

- [ ] `ResolveTenant`: manejar `TenantStatus::Pending` → `Errors/TenantPending`.
- [ ] Test en `ResolveTenantMiddlewareTest`: subdominio `pending` → página pendiente.
- [ ] Anotar el delta en `docs/specs/F002-tenant-resolution/` (Changelog) y
  `docs/multi-tenancy.md`.

## Backend (`montree-backend-dev`)

- [ ] `App\Services\Onboarding\OnboardingHandoff` (nonce cache + signed URLs) + unit test.
- [ ] `App\Actions\Onboarding\RegisterAgencyAction` (transaccional) + test.
- [ ] `App\Actions\Onboarding\ActivateAgencyAction` + test.
- [ ] `App\Actions\Onboarding\ClaimAgencyAccessAction` + test.
- [ ] `App\Http\Requests\Onboarding\CheckSubdomainRequest`.
- [ ] `App\Http\Requests\Onboarding\RegisterAgencyRequest` (+ regla `not_reserved_subdomain`).
- [ ] Controllers: `SubdomainAvailabilityController`, `AgencyRegistrationController`,
  `VerifyAgencyController`, `ClaimAgencyController`, `ResendVerificationController`,
  `OnboardingPageController`.
- [ ] `App\Http\Resources\Onboarding\AgencyRegistrationResource`.
- [ ] `App\Events\AgencyRegistered` + listener que encola la notificación.
- [ ] `App\Notifications\Onboarding\VerifyAgencyEmail` (branded).
- [ ] Rutas en `routes/api.php` (grupo onboarding, throttles) y `routes/web.php`
  (`/start`, check-email, `verify`/`claim`/`resend` con `signed`).
- [ ] Tests feature: register (happy+failures+transacción), availability,
  verify+claim (activación, trial, one-shot nonce, expirado), tenant pending page.
- [ ] `php artisan wayfinder:generate`
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=Onboarding`

## Frontend (`montree-frontend-dev`)

- [ ] `pages/Onboarding/CreateAgency.vue` (ruta `/start`).
- [ ] `pages/Onboarding/CheckEmail.vue`.
- [ ] `pages/Onboarding/VerificationExpired.vue`.
- [ ] `pages/Errors/TenantPending.vue`.
- [ ] `organisms/AgencySignupForm.vue`, `molecules/SubdomainField.vue`.
- [ ] `composables/useSubdomainAvailability.ts` (debounce + fetch).
- [ ] `types/onboarding.types.ts`.
- [ ] POST del alta vía `useApi`/fetch (NO `router.post` a `/api/v1/*`); navegación
  con `router.visit`. URLs vía Wayfinder.
- [ ] Estados: loading, error por campo, disponibilidad (idle/checking/ok/taken).
- [ ] Validación frontend espejo de `RegisterAgencyRequest`.
- [ ] Entry point al alta desde el landing (`HomePageController` / `PublicLayout`).
- [ ] `npm run types:check`
- [ ] `npm run lint && npm run format`
- [ ] Probar en navegador: alta → email → verify → auto-login en subdominio.

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
