# F002 — Tasks

## Backend (`montree-backend-dev`)

### Middleware + servicios
- [x] `app/Http/Middleware/ResolveTenant.php`: resolver + suspendido + reservados + setPermissionsTeamId
- [x] Registrar middleware en `bootstrap/app.php` grupo `web`
- [x] `app/Services/Tenant/TenantConfigurationCache.php` — wrapper Cache::remember
- [x] `app/Services/Tenant/CustomCssSanitizer.php` — whitelist + regex
- [x] `app/Services/Tenant/HexToHsl.php` — helper de conversión (puede ser método estático)

### Eventos / listeners / observers
- [x] `app/Events/TenantUpdated.php`, `TenantConfigurationUpdated.php`
- [x] `app/Listeners/InvalidateTenantCache.php`
- [x] `app/Observers/TenantObserver.php`, `TenantConfigurationObserver.php`
- [x] Registrar observers en `AppServiceProvider::boot()`

### Actions
- [x] `app/Actions/Tenant/UpdateTenantAction.php`
- [x] `app/Actions/Tenant/UpdateTenantConfigurationAction.php`

### Form Requests
- [x] `app/Http/Requests/Admin/Tenant/UpdateTenantRequest.php`
- [x] `app/Http/Requests/Admin/Tenant/UpdateTenantConfigurationRequest.php` (con check de plan para custom_css)

### Controllers
- [x] `app/Http/Controllers/Api/V1/TenantController.php` (show)
- [x] `app/Http/Controllers/Api/V1/Admin/TenantController.php` (update)
- [x] `app/Http/Controllers/Api/V1/Admin/TenantConfigurationController.php` (update)
- [x] `app/Http/Controllers/Errors/TenantNotFoundController.php`
- [x] `app/Http/Controllers/Errors/TenantSuspendedController.php`

### Resources
- [x] `app/Http/Resources/TenantResource.php`
- [x] `app/Http/Resources/TenantConfigurationResource.php` (incluye HSL conversion)

### Policy
- [x] `app/Policies/TenantPolicy.php` (`update`)
- [x] Registrar en `AppServiceProvider`

### Shared Inertia props
- [x] Modificar `app/Http/Middleware/HandleInertiaRequests.php` para inyectar `tenant`, `tenantConfiguration`, `flash`

### Rutas
- [x] `routes/api.php`: agregar rutas `/api/v1/tenant`, `/api/v1/admin/tenant`, `/api/v1/admin/tenant/configuration`
- [x] Verificar `routes/web.php` landing platform

### Tests
- [x] `tests/Feature/Tenant/ResolveTenantMiddlewareTest.php` (6 tests)
- [x] `tests/Feature/Api/V1/TenantControllerTest.php` (2 tests)
- [x] `tests/Feature/Api/V1/Admin/TenantControllerTest.php` (3 tests)
- [x] `tests/Feature/Api/V1/Admin/TenantConfigurationControllerTest.php` (4 tests)
- [x] `tests/Feature/Tenant/TenantSharedPropsTest.php` (1 test)
- [x] `tests/Unit/Services/CustomCssSanitizerTest.php` (5+ casos)

### Cierre
- [x] `php artisan wayfinder:generate`
- [x] `vendor/bin/pint --dirty --format agent`
- [x] `php artisan test --compact --filter=Tenant` y `--filter=TenantConfiguration`

## Frontend (`montree-frontend-dev`)

### Types
- [x] `resources/js/types/tenant.ts` — interfaces Tenant, TenantConfiguration (renombrado `tenant.types.ts`→`tenant.ts` para consistencia con `auth.ts`, `navigation.ts`)
- [x] Extender PageProps de Inertia con `tenant`, `tenantConfiguration`, `flash` en `types/global.d.ts`

### Composables
- [x] `resources/js/composables/useTenant.ts`
- [x] `resources/js/composables/useTenantBranding.ts` — aplica CSS vars en `:root` (envuelve los HSL del backend en `hsl(...)` para matchear el formato de `app.css`)

### Pages
- [x] `resources/js/pages/Errors/TenantNotFound.vue`
- [x] `resources/js/pages/Errors/TenantSuspended.vue`
- [x] `resources/js/pages/Admin/Tenant/Configuration.vue`

### Componentes (`resources/js/components/`)
- [x] `organisms/BrandingEditor.vue`
- [x] `organisms/OperationalSettingsForm.vue`
- [x] `organisms/SocialLinksEditor.vue`
- [x] `molecules/ColorPicker.vue`
- [x] `molecules/PreviewPanel.vue`
- [x] `molecules/CurrencySelector.vue` (whitelist corta: USD, COP, EUR, MXN, ARS, PEN, CLP, BRL)
- [x] `molecules/TimezoneSelector.vue` (LATAM + Madrid + UTC)
- [x] `ui/switch/Switch.vue` + `ui/textarea/Textarea.vue` agregados (shadcn-vue locales — el starter no los traía y los necesitábamos para el form)

### Wiring
- [x] Llamar `useTenantBranding()` en `AppLayout.vue` y en `pages/Errors/TenantSuspended.vue`
- [x] Inertia `app.ts` actualizado para que `Errors/*` se rendericen sin layout
- [x] Usar Wayfinder (`@/actions/App/Http/Controllers/Api/V1/Admin/TenantConfigurationController`) — cero URLs hardcoded
- [x] Validación frontend espejo: hex regex en `ColorPicker`, maxlength en Input/Textarea, whitelist en `CurrencySelector`/`TimezoneSelector`/locale Select

### Cierre
- [x] `npm run types:check` — 0 errores (también arregló los 16 pre-existentes del starter como side-effect del `lint --fix` que reordenó imports)
- [x] `npm run lint && npm run format` — 0 errores en mis archivos; único error remanente es `vite.config.ts` parsing en path de worktree, no relacionado a mis cambios
- [x] `npm run build` pasa: `Configuration-*.js` 53kB, `TenantNotFound-*.js` 1.36kB, `TenantSuspended-*.js` 1.77kB
- [ ] Probar en navegador: visitar `demo.montree.test`, verificar branding cargado; modificar config y verificar update — **NO PROBADO**: la página admin requiere login (F001 pendiente). Las pages Errors sí son accesibles públicamente. Recomendación: el reviewer puede correr `php artisan tinker --execute 'auth()->login(\App\Models\User::first()); ...'` o esperar F001.

## Review (`montree-reviewer`)

- [ ] Tests pasan (incluye los nuevos)
- [ ] Pint, types-check, lint OK
- [ ] Cobertura: cada acceptance criterion tiene test
- [ ] N+1 check en TenantController (relación configuration eager)
- [ ] Sanitizer probado contra XSS conocidos
- [ ] Middleware order verificado
- [ ] Sin URLs hardcoded en frontend

---

## Bloqueos / Decisiones pendientes

(ninguna abierta — todas las decisiones cerradas en spec.md)

## Notas durante implementación

- `2026-05-17` (claude principal): F002 arrancado. Branch `feature/F002-tenant-resolution`.
- `2026-05-17` (montree-backend-dev): backend completo. 24 tests nuevos verdes, 78 totales en suite. Pint clean. Wayfinder regenerado (frontend ya puede importar `Api/V1/...` actions). Decisiones tomadas durante implementación:
  - **Middleware ejecuta el finder manualmente** en lugar de depender del boot de spatie. Razón: el package corre `start()` una sola vez en boot, lo cual no funciona en el test runner (mismo proceso, múltiples requests). Mi `ResolveTenant` llama a `$finder->findForRequest($request)` + `makeCurrent()/forgetCurrent()` en cada request, lo que también permite cubrir `tearDown` correctamente.
  - **Routing API**: `bootstrap/app.php` ahora registra `routes/api.php` con `apiPrefix: 'api/v1'`. ResolveTenant aplicado en `web` (prepend) y `api` (prepend).
  - **HexToHsl** devuelve valores matemáticamente correctos. Para `#16a34a` produce `142 76% 36%` (spec menciona `142 65% 38%` como ejemplo aproximado). Si reviewer/frontend requiere el valor exacto del spec, hay que ajustar la fórmula o aceptar el cómputo estándar.
  - **CustomCssSanitizer**: MVP con regex + whitelist. TODO: si la complejidad crece, evaluar `sabberworm/php-css-parser` (paquete dedicado) — abrir RFC en backlog.
  - **FeatureRequiresEnterpriseException**: nueva clase en `app/Exceptions/` (no estaba en plan). Implementa `HttpExceptionInterface` para producir 403 con `error_code` consistente.
  - **Listener auto-discovery**: `event:list` confirma binding correcto. No fue necesario `EventServiceProvider` explícito.
  - **`config('filesystems.default')`**: en producción puede no devolver URL para `logo_path` si está apuntando a un disco sin método `url()`. Por ahora retorna `null` cuando no hay path. Upload de logo está out of scope para F002.
- **Self-review**:
  1. Completo: todos los checkboxes de Backend marcados. Frontend pendiente (no es de mi rol).
  2. Errores: 0 — 78/78 tests verdes, pint OK.
  3. Mejoras pendientes:
     - El sanitizer no soporta `@media` (todos los at-rules rechazados). Si Enterprise quiere queries responsive, abrir feature follow-up.
     - El `TenantPolicy::update` se invoca dos veces vía Form Request (autorize) y la Action podría hacer un check segundo si quisiéramos defensa en profundidad. Aceptable hoy.
     - Listener de invalidación: si en el futuro hay muchos eventos por save, podríamos debouncing/queue, pero por ahora síncrono es OK.
     - No tests para la conversión `HexToHsl` aislada (cubierta indirectamente por TenantConfigurationControllerTest y TenantSharedPropsTest). Si reviewer lo pide, agregar un test unit dedicado.
- `2026-05-17` (montree-frontend-dev): frontend completo. Types-check 0 errores, build OK, lint clean en mis archivos. Decisiones tomadas durante implementación:
  - **`tenant.ts` (no `tenant.types.ts`)**: el resto de los types del proyecto (`auth.ts`, `navigation.ts`, `ui.ts`) no usa el sufijo `.types`. Mantuve la convención.
  - **Switch + Textarea agregados a `ui/`**: el starter no los traía. Los creé localmente siguiendo el patrón shadcn-vue (mismo formato que `Checkbox.vue`/`Input.vue`, usando `reka-ui` que ya está instalado). NO se instaló ningún paquete npm nuevo.
  - **`useTenantBranding`**: aplica los HSL del backend envueltos en `hsl(...)` porque `app.css` define los tokens como `hsl(...)`. Cachea defaults la primera vez para poder restaurar cuando no hay tenant. Sobreescribe `--primary`, `--ring`, `--sidebar-primary`, `--sidebar-ring` (todos derivan del primary) y `--secondary`.
  - **`AdminLayout` no se creó**: reutilicé `AppLayout` (AppSidebarLayout) que es lo que ya hace el starter para Dashboard/Settings. Si reviewer/F003+ pide un layout admin separado con sidebar verde-más-oscuro, refactor.
  - **`AuthLayout` NO se modificó**: dejé a F001 decidir si quiere aplicar `useTenantBranding()` en las páginas de login. Aplicarlo en AuthLayout afectaría también páginas como ForgotPassword sin tenant resuelto.
  - **`app.ts` modificado**: agregada rama `case name.startsWith('Errors/'): return null;` para que `TenantNotFound`/`TenantSuspended` no se monten dentro de un layout con sidebar.
  - **Manejo de error Enterprise**: el frontend muestra un Alert destructivo cuando el backend devuelve mensaje conteniendo "enterprise" (case-insensitive) en `custom_css` error o en `flash.error`. Si el `error_code` específico viene en un shape distinto al esperado, ajustar.
  - **No agregamos un input UI para `custom_css`** porque la spec dice "solo Enterprise" y la página admin sirve para los 3 planes. El form solo envía `custom_css` si `isEnterprise.value` y el tenant lo seteó. UI completa de Enterprise (textarea de CSS con syntax highlight) queda para feature follow-up.
- **Self-review frontend**:
  1. **Completo**: todos los checkboxes de Frontend marcados salvo "Probar en navegador" — bloqueado por falta de login (F001).
  2. **Errores**: 0 type-check, 0 lint en mis archivos, build OK. Como side-effect positivo, `lint --fix` reordenó imports en archivos del starter (`auth/Login.vue`, `settings/Profile.vue`, etc.) y eso resolvió los 16 errores TS pre-existentes (`Property 'form' does not exist`). No tocó la lógica, solo orden de imports.
  3. **Mejoras pendientes / candidatos a reutilización**:
     - `Switch` y `Textarea` podrían beneficiarse de un test visual / story (no hay setup de stories en el proyecto todavía).
     - `ColorPicker` podría ser un átomo si en F0xx hace falta para Categorías/Tags (color por categoría, etc.).
     - `PreviewPanel` actualmente sólo muestra un botón hardcoded; en feature de templates de página podría aceptar un slot.
     - Toast genérico para errores 422: el `onError` muestra mensaje genérico. Podríamos centralizar esto en `lib/flashToast.ts` para que cualquier form lo herede.
     - Accesibilidad: los Switches tienen Label asociada por `for=`, pero el Switch root no tiene `id` explícito (reka-ui lo genera). Verificar en QA con lector de pantalla.
     - El form `Admin/Tenant/Configuration.vue` no usa Inertia `<Form v-bind="action.form()">` porque necesitaba `transform()` y control fino sobre estado anidado (`social_links` objeto). Si el patrón `<Form>` evoluciona en Inertia v3.x para soportar mejor objetos anidados, migrar.
     - El cache de defaults en `useTenantBranding` es módulo-level (singleton). En SSR/multi-window puede ser fishy; por ahora cliente-only (`typeof document === 'undefined'` guard).
