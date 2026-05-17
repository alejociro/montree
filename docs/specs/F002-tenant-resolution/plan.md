# F002 — Plan técnico

## 1. Resumen

Middleware HTTP que ejecuta `Spatie\Multitenancy\Http\Middleware\NeedsTenant` con nuestro `SubdomainTenantFinder`, después setea `setPermissionsTeamId($tenant->id)`, y maneja edge cases (tenant no resuelto, tenant suspendido) renderizando páginas Inertia. Endpoint API y endpoint admin para leer/escribir configuración. Composables Vue para consumir el tenant desde shared props.

## 2. Backend

### Middleware (`app/Http/Middleware/`)

- **`ResolveTenant`** — ejecuta el finder de spatie, decide si el tenant es requerido (404 si no se resuelve en subdominio no reservado), maneja suspendido (renderiza 503), y setea `setPermissionsTeamId($tenant->id)`. Reemplaza `NeedsTenant` del paquete para no acoplarnos.
- Registrar en `bootstrap/app.php` dentro del grupo `web`. Para landing pública (`montree.app` raíz, `www`, `admin`, `api`): el middleware lo permite sin tenant.

### Service (`app/Services/Tenant/`)

- **`TenantConfigurationCache`** — wrapper de `Cache::remember('tenant:{slug}', 300, ...)`. Métodos `forSlug(string $slug): ?Tenant` con relación `configuration` cargada. Invalidación vía listener.
- **`CustomCssSanitizer`** — recibe string CSS, parsea con regex tolerante, descarta propiedades fuera de whitelist y URLs peligrosas. Retorna string saneada + lista de removed (para reportar en response).

### Eventos / Listeners

- **`App\Events\TenantUpdated`**, **`App\Events\TenantConfigurationUpdated`** — disparados desde Observer.
- **`App\Listeners\InvalidateTenantCache`** — borra cache `tenant:{slug}`.
- Observers: `TenantObserver`, `TenantConfigurationObserver` registrados en `AppServiceProvider`.

### Actions (`app/Actions/Tenant/`)

- **`UpdateTenantAction::handle(Tenant, array $data): Tenant`**
- **`UpdateTenantConfigurationAction::handle(Tenant, array $data): TenantConfiguration`** — incluye check de plan para `custom_css`, sanitización, hex→HSL conversion para colores.

### Form Requests (`app/Http/Requests/Admin/Tenant/`)

- **`UpdateTenantRequest`** — name, contact_email, contact_phone.
- **`UpdateTenantConfigurationRequest`** — colors, currency, timezone, locale, social_links, etc. + lógica condicional para `custom_css` por plan.

### Controllers (`app/Http/Controllers/Api/V1/`)

- **`TenantController`** — `show(): TenantResource` (público, GET /api/v1/tenant).
- **`Admin\TenantController`** — `update(UpdateTenantRequest): TenantResource`.
- **`Admin\TenantConfigurationController`** — `update(UpdateTenantConfigurationRequest): TenantConfigurationResource`.

### Resources (`app/Http/Resources/`)

- **`TenantResource`** — id, slug, name, domain, status, plan, contact_email, contact_phone.
- **`TenantConfigurationResource`** — todos los fields del shape en contracts.md, incluyendo conversiones hex→HSL.

### Policies (`app/Policies/`)

- **`TenantPolicy`** — `update`: usuario tiene rol `admin` en este tenant (vía `hasRole('admin', $tenant)` de spatie con teams).

### Shared Inertia props

- Modificar `App\Http\Middleware\HandleInertiaRequests::share()` para incluir `tenant` y `tenantConfiguration` (resueltos del `currentTenant()`). Sin tenant → null en ambos.

### Páginas error Inertia

- **`Errors\TenantNotFoundController::__invoke()`** → `Inertia::render('Errors/TenantNotFound')->toResponse(...)->setStatusCode(404)`.
- **`Errors\TenantSuspendedController::__invoke()`** → idem con 503 + props `{ tenantName, contactEmail }`.
- Llamados directamente desde `ResolveTenant` middleware (no via routes públicas).

### Rutas

- `routes/api.php`: agrupar bajo `/api/v1/`. GET `/tenant`, PUT `/admin/tenant`, PUT `/admin/tenant/configuration`.
- `routes/web.php`: agregar `Inertia::render('Welcome')` solo para landing platform (sin tenant).

## 3. Frontend

### Composables (`resources/js/composables/`)

- **`useTenant()`** — devuelve `{ tenant, configuration, isResolved, primaryColor, currency, locale }` desde `usePage().props`.
- **`useTenantBranding()`** — efecto que en `onMounted` aplica `configuration.primary_color_hsl` y `secondary_color_hsl` al `:root` CSS variables. Llamado desde `AppLayout`.

### Types (`resources/js/types/`)

- **`tenant.types.ts`** — interfaces `Tenant`, `TenantConfiguration`, status/plan enums string literal.
- Extender `inertia.d.ts` (o donde sea) con `PageProps` que incluya `tenant` y `tenantConfiguration`.

### Pages (`resources/js/pages/`)

- **`Errors/TenantNotFound.vue`** — layout simple, ilustración placeholder, CTA "ir a montree.app".
- **`Errors/TenantSuspended.vue`** — recibe props, muestra nombre del tenant y contacto.
- **`Admin/Tenant/Configuration.vue`** — formulario completo de branding + operational settings.

### Componentes nuevos

- **`organisms/BrandingEditor.vue`** — sección de colors/logo/favicon/tagline.
- **`organisms/OperationalSettingsForm.vue`** — currency/timezone/locale/checks.
- **`organisms/SocialLinksEditor.vue`** — array de redes.
- **`molecules/ColorPicker.vue`** — input type=color + hex input sincronizado.
- **`molecules/PreviewPanel.vue`** — muestra preview de colores aplicados.
- **`molecules/CurrencySelector.vue`** — select de ISO 4217 (whitelist corta).
- **`molecules/TimezoneSelector.vue`** — select de timezones PHP comunes.

Reutilizar atoms del starter shadcn: `Input`, `Button`, `Label`, `Select`, `Switch`, `Textarea`, `Card`.

### Wayfinder

Tras backend listo: `php artisan wayfinder:generate`. Frontend importa:
```ts
import { update as updateTenant } from '@/actions/Api/V1/Admin/TenantController'
import { update as updateConfig } from '@/actions/Api/V1/Admin/TenantConfigurationController'
```

## 4. Tests

### Feature tests

- `tests/Feature/Tenant/ResolveTenantMiddlewareTest.php`
  - `test_resolves_tenant_from_subdomain`
  - `test_returns_404_inertia_when_subdomain_not_found`
  - `test_returns_503_inertia_when_tenant_suspended`
  - `test_allows_reserved_host_without_tenant` (`www`, `admin`, `api`)
  - `test_allows_root_domain_without_tenant`
  - `test_sets_permissions_team_id_after_resolution`

- `tests/Feature/Api/V1/TenantControllerTest.php`
  - `test_returns_current_tenant_with_configuration`
  - `test_returns_404_when_no_tenant_resolved`

- `tests/Feature/Api/V1/Admin/TenantControllerTest.php`
  - `test_admin_can_update_tenant_basic_info`
  - `test_non_admin_cannot_update_tenant` (403)
  - `test_validates_required_fields` (422)

- `tests/Feature/Api/V1/Admin/TenantConfigurationControllerTest.php`
  - `test_admin_can_update_branding`
  - `test_custom_css_rejected_when_plan_not_enterprise` (403, error_code)
  - `test_custom_css_accepted_on_enterprise_plan_and_sanitized`
  - `test_invalidates_cache_on_update`

- `tests/Feature/Tenant/TenantSharedPropsTest.php`
  - `test_inertia_pages_receive_tenant_and_configuration`

### Unit tests

- `tests/Unit/Services/CustomCssSanitizerTest.php` — varios casos de sanitización (acepta, rechaza, log de removidos).

## 5. Decisiones tomadas

- **Middleware propio en vez del `NeedsTenant` de spatie**: necesitamos lógica extra (suspendido, hosts reservados, teams). El finder sí es de spatie.
- **Cache TTL 300s**: balance entre frescura y carga DB. Invalidación inmediata al actualizar.
- **Sanitizer CSS in-house con regex + whitelist**: evitar agregar dep nueva (HTMLPurifier o similar) por ahora. Si crece la complejidad, considerar `spatie/css-cleaner` o pelusakit.
- **Conversión hex→HSL en backend**: shadcn usa HSL en CSS vars; hacer la conversión donde la lógica es más fácil de testear.
- **Shared props vs solo API endpoint**: ambos. Inertia para uso interno (sin round-trip), API para integraciones externas y debugging.

## 6. Riesgos y mitigaciones

| Riesgo | Probabilidad | Mitigación |
|---|---|---|
| Sanitizer CSS deja pasar algo malicioso | media | Tests de XSS conocidos + revisión humana del whitelist |
| Cache stale tras update | baja | Listener invalida explícitamente; tests cubren caso |
| Middleware order incorrecto rompe permisos | baja | Test específico `test_sets_permissions_team_id_after_resolution` |
| Conversión hex→HSL inconsistente entre frontend y backend | media | Test unit comparando ambos; doc clara |

## 7. Out of scope explícito

- Custom domain (no subdomain) — feature futuro Enterprise.
- Upload de logo/favicon (sube placeholder; storage real en feature aparte si se prioriza).
- Preview en vivo de cambios sin guardar (preview es estático con valores en form).
- Cache invalidation distribuida (single-server por ahora).
