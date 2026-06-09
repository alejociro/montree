# F016 — Onboarding self-serve de agencia

> Spec funcional. Lo que el feature hace desde la óptica del usuario.
> Cambios a este archivo requieren actualizar [`tasks.md`](./tasks.md) y registrar en `## Changelog`.

---

## Descripción

Permite que cualquier operador turístico cree su propia agencia en MONTREE sin
intervención manual: elige un nombre, reclama un subdominio, crea su usuario
fundador y entra a un periodo de prueba. Es el flujo de adquisición de la
plataforma — la primera impresión y el principal motor de conversión, así que la
experiencia debe estar al nivel de los mejores SaaS (Shopify, Vercel, Linear):
una sola pantalla fluida, validación en tiempo real y cero fricción innecesaria.

El alta es **self-serve con trial inmediato**. La agencia se crea en estado
`pending` y se **activa cuando el fundador verifica su email**; en ese momento
arranca el trial y se lo lleva, ya logueado, a su panel de administración en su
propio subdominio.

## User stories

- Como operador turístico, quiero crear mi agencia desde el landing en una sola
  pantalla, para empezar a usar la plataforma sin esperar aprobaciones.
- Como fundador, quiero ver al instante si el subdominio que quiero está libre,
  para no perder tiempo con nombres ocupados.
- Como fundador, quiero recibir un email para confirmar mi cuenta, para que mi
  agencia quede activa y segura.
- Como fundador, quiero que al confirmar mi email me lleve directo y ya logueado
  a mi panel, para empezar a configurar mi agencia sin tener que volver a entrar.
- Como plataforma, necesito que nadie pueda ocupar subdominios reservados o con
  emails falsos, para mantener la calidad del namespace.

## Acceptance criteria

### Creación de la agencia
- **Given** un visitante en `montree.app/start`, **when** completa nombre de
  agencia, subdominio, su nombre, email y contraseña válidos, **then** se crea el
  tenant en estado `pending`, su `tenant_configuration` con defaults, el usuario
  fundador con rol `admin` y membresía `active`, y se le envía email de verificación.
- **Given** un alta exitosa, **then** se muestra una pantalla "Revisá tu email
  para activar {agencia}" con el email destino y opción de reenviar.

### Disponibilidad de subdominio
- **Given** el fundador escribe un subdominio, **when** deja de tipear (debounce),
  **then** se consulta disponibilidad y se muestra ✓ disponible o ✗ con la razón
  (ocupado / reservado / formato inválido).
- **Given** un subdominio que matchea un host reservado (`www`, `admin`, `api`,
  etc.) o el regex inválido, **then** se marca como no disponible.

### Verificación y activación
- **Given** un tenant `pending`, **when** el fundador abre el link de verificación
  válido, **then** su email queda verificado, el tenant pasa a `active`,
  `trial_ends_at` se setea a `now + TRIAL_DAYS`, y se lo redirige a su subdominio
  ya logueado en `/admin/dashboard`.
- **Given** un link de verificación ya usado o expirado, **then** se muestra una
  página clara con opción de reenviar.

### Acceso durante `pending`
- **Given** un tenant en estado `pending`, **when** alguien visita su subdominio,
  **then** ve una página "Esta agencia está pendiente de activación" (no el
  catálogo, no un 404).

## Edge cases

- **Email ya registrado en la plataforma:** el alta falla con mensaje genérico
  ("No pudimos crear la cuenta con esos datos"). Decisión D1: un email = una sola
  agencia fundada (`unique:users,email` en el request).
- **Subdominio tomado entre el check y el submit (race):** la validación de unicidad
  en el `StoreAgencyRequest` es la fuente de verdad; el check async es solo UX.
- **Token de handoff cross-subdominio reutilizado:** es de un solo uso (nonce en
  cache); el segundo intento manda a `/login`.
- **Verificación de email reenviada varias veces:** rate-limited; el último link
  válido es el que cuenta.
- **Abandono tras crear (nunca verifica):** el tenant queda `pending`; un comando
  programado puede purgar pendientes > N días (out of scope, ver abajo).

## Dependencias

- F002 (tenant resolution) — el subdomain finder, hosts reservados y manejo de
  estados de tenant. **Requiere** que `ResolveTenant` maneje el estado `pending`.
- F001 (auth) — usuarios, Fortify, verificación de email, sesión aislada por
  subdominio (ver `docs/multi-tenancy.md §10`).

## Endpoints involucrados

```
GET    /api/v1/onboarding/subdomain-availability   # check async (platform host)
POST   /api/v1/onboarding/agencies                 # crea agencia + fundador
GET    /onboarding/verify/{tenant}/{user}          # signed, platform host — verifica + activa
GET    /onboarding/claim                            # signed, subdominio — auto-login fundador
POST   /onboarding/resend-verification             # reenvía email (rate-limited)
```

(Detalle en [`contracts.md`](./contracts.md))

## Componentes UI

- Pages: `Onboarding/CreateAgency.vue`, `Onboarding/CheckEmail.vue`,
  `Onboarding/VerificationExpired.vue`, `Errors/TenantPending.vue`
- Organisms: `AgencySignupForm`
- Molecules: `SubdomainField` (input + sufijo `.montree.app` + estado de
  disponibilidad), `PasswordField`
- Atoms: reutilizar `BaseInput`, `BaseButton`, `ColorSwatch` (si se ofrece color
  inicial)

## Datos requeridos

Tablas: `tenants`, `tenant_configurations`, `tenant_user`, `users`
**No requiere migraciones** — todas las columnas necesarias ya existen
(`tenants.status/plan/trial_ends_at`, defaults en `tenant_configurations`).

---

## Out of scope (explícitamente NO se hace)

- Custom domain (dominio propio sin subdominio) — plan Enterprise futuro.
- Selección/cambio de plan de pago durante el alta — entra en trial del plan
  default; el upgrade lo maneja F007/billing.
- Importación de tours/datos al crear.
- Purga automática de tenants `pending` abandonados (comando programado futuro).
- Editor de branding completo en el alta — se reduce a defaults; el branding fino
  vive en F002 `TenantConfigPage`.

## Decisiones tomadas

- **Modelo de alta:** self-serve público con trial inmediato. Razón: máxima
  conversión, estándar de los mejores SaaS.
- **Activación:** el tenant nace `pending` y se activa al verificar el email del
  fundador. Razón: evita squatting de subdominios y altas con emails falsos.
- **Login post-alta:** token firmado de un solo uso (nonce en cache) que hace
  auto-login en el subdominio tras la verificación. Razón: sesión aislada por
  subdominio (§10) impide compartir cookie entre hosts; el handoff firmado da una
  UX de una sola pantalla sin re-login.

- **D1 — Email global único:** un email puede fundar UNA sola agencia. El alta
  mantiene `unique:users,email`; un user existente no puede fundar otra agencia con
  el mismo email. Razón: simplicidad y modelo claro de propiedad.
- **D2 — Trial y plan default:** 14 días en plan `Professional`. Configurable en
  `config/montree.php` (`onboarding.trial_days`, `onboarding.default_plan`).
- **D3 — Sin tarjeta en el alta:** trial sin fricción; el billing se solicita al
  expirar el trial (F007). Razón: maximizar conversión de alta.
- **D4 — Palabras de marca reservadas:** ampliar la lista de hosts/slugs reservados
  con términos de plataforma (`app`, `blog`, `docs`, `status`, `ayuda`, `soporte`,
  `help`, `support`, `mail`, `static`, `cdn`, `assets`). Centralizado junto a
  `SubdomainTenantFinder::RESERVED_HOSTS` / una lista de slugs reservados.

---

## Changelog

- `2026-06-04` — Creación inicial. Decisiones de alta/activación/handoff cerradas.
- `2026-06-04` — D1–D4 resueltas (email único por agencia, trial 14d Professional,
  sin tarjeta, lista de slugs de marca reservados). Spec lista para implementar.
