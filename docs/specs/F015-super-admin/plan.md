# F015 — Plan técnico

## 1. Resumen

Panel super admin que opera fuera del tenant context (host `admin.montree.test`). Endpoints + pages dedicados protegidos por middleware `EnsureSuperAdmin`. Servicio de métricas globales, actions para cambio de status/plan con notificaciones queue.

## 2. Backend

### Middleware
- `app/Http/Middleware/EnsureSuperAdmin.php` — verifica `auth() && setPermissionsTeamId(0) && user->hasRole('super_admin')`. Caso contrario → 403 (o 404 si quieres no revelar la existencia).

### Routes — host constraint
- En `routes/web.php` (o nuevo `routes/super-admin.php`) usar:
  ```
  Route::domain('admin.montree.test')->middleware(['auth', 'super_admin.only'])->prefix('super-admin')->group(...);
  ```
- Mismo patrón en `routes/api.php`.
- Configurar el alias `super_admin.only` en `bootstrap/app.php` middleware aliases.

### ResolveTenant interaction
- `ResolveTenant` ya trata `admin.montree.test/.app` como host reservado y NO bloquea. El middleware super_admin corre DESPUÉS y enforza.

### Services (`app/Services/SuperAdmin/`)
- `PlatformMetricsAggregator::collect(Carbon $from, Carbon $to): PlatformMetrics` — sums + counts globales (sin tenant_id scope, usando `withoutGlobalScope` documentado).
- `TenantSuspensionAction`, `TenantRestoreAction`, `TenantPlanChangeAction` (actions).
- Cada action emite la Notification correspondiente (queue).

### Actions (`app/Actions/SuperAdmin/`)
- `UpdateTenantStatusAction::handle(Tenant $tenant, TenantStatus $next, ?string $reason): Tenant`
- `UpdateTenantPlanAction::handle(Tenant $tenant, TenantPlan $next): Tenant`

### Form Requests
- `UpdateTenantStatusRequest`, `UpdateTenantPlanRequest`.

### Controllers (`app/Http/Controllers/Api/V1/SuperAdmin/`)
- `DashboardController` (show)
- `TenantController` (index, show)
- `TenantStatusController` (update)
- `TenantPlanController` (update)

### Resources
- `SuperAdminTenantResource`, `PlatformMetricsResource`.

### Notifications (`app/Notifications/SuperAdmin/`)
- `TenantSuspendedNotification implements ShouldQueue`
- `TenantRestoredNotification implements ShouldQueue`
- `TenantPlanChangedNotification implements ShouldQueue`

Constructores reciben primitivos (queue-safe). Templates Blade en `resources/views/emails/super-admin/`.

### Policy
- `SuperAdminTenantPolicy::manage` — gate.

### Cache
- Dashboard cacheado 60s (key `super-admin:dashboard:{from}:{to}`).

## 3. Frontend

### Pages
- `resources/js/pages/SuperAdmin/Dashboard.vue`
- `resources/js/pages/SuperAdmin/Tenant/Index.vue`
- `resources/js/pages/SuperAdmin/Tenant/Detail.vue`

### Layout
- `resources/js/layouts/SuperAdminLayout.vue` — wrapper independiente. NO usa `useTenantBranding` (no hay tenant). Paleta neutra (gris/zinc-900) para distinguir.
- `resources/js/components/SuperAdminSidebar.vue` — items "Dashboard", "Tenants", "Salir".
- `app.ts`: pages starting with `SuperAdmin/` → SuperAdminLayout.

### Organisms
- `TenantTable.vue` — lista con badges de status/plan, columnas users/tours/revenue.
- `PlatformStats.vue` — grid de StatCard reused.
- `TenantDetailPanel.vue` — tabs Overview/Users/Bookings.

### Molecules
- `TenantRow.vue` (si necesario, sino usar `<tr>` direct)
- `PlanBadge.vue`, `TenantStatusBadge.vue`
- `StatusChanger.vue` — dropdown que abre `ConfirmDialog` (suspended requiere reason)
- `PlanChanger.vue` — select inline con confirm.
- `PlatformStatCard.vue` — reuso de StatCard o variante.

### Reusar shadcn-vue
- `Table`, `Dialog`, `Badge`, `Button`, `Input`, `Textarea`.

### Types
- `resources/js/types/super-admin.ts` — interfaces.

### Wayfinder
- `@/actions/App/Http/Controllers/Api/V1/SuperAdmin/...`

## 4. Tests

### Feature
- `SuperAdmin/DashboardControllerTest`: super_admin sees globals; normal user → 403; admin user → 403 (verifica que `role:admin` en un tenant NO da acceso a super-admin).
- `SuperAdmin/TenantControllerTest`: list/show, filters search.
- `SuperAdmin/TenantStatusControllerTest`: suspend with reason → notification queued; without reason → 422; same status → 409.
- `SuperAdmin/TenantPlanControllerTest`: plan change → notification + limits applied; downgrade que excede → permitido (soft limit) con log warning.

### Unit
- `Services/SuperAdmin/PlatformMetricsAggregatorTest`: skip soft-deleted, scope global, currency aggregation.

## 5. Decisiones
- **Host constraint** vía `Route::domain()` en config — más explícito que un middleware. `admin.montree.test` se trata como subdomain reservado en `SubdomainTenantFinder`.
- **`super_admin.only` middleware alias** — más legible que `role:super_admin` (que requeriría team_id=0).
- **Soft limit en downgrade**: NO eliminamos data del tenant, solo aplicamos los nuevos límites a operaciones futuras (CreateTour etc. respetan el plan actual).
- **Queue notifications**: liberadas en queue. En dev queue=`sync` resuelto por driver.
- **Layout separado**: SuperAdmin no tiene branding tenant. Paleta neutra.

## 6. Riesgos / mitigaciones

| Riesgo | Mitig. |
|---|---|
| Host `admin.montree.test` no resuelve en local sin /etc/hosts | doc en local-setup.md ya lo cubre |
| Super admin accede por mistake desde tenant subdomain | middleware reject + 404 |
| Notificaciones queue caen porque drivers no configurados | en dev queue=sync por default; en prod log warning si fallan |

## 7. Out of scope
- Editar tenant_configurations del tenant desde super admin (los admins lo hacen ellos)
- Crear tenant nuevo desde super admin (futuro flow de onboarding)
- Logs de auditoría de acciones super admin (futuro F0XX)
- Impersonate as admin del tenant (futuro)
