# F015 — Tasks

## Backend
- [x] Middleware `EnsureSuperAdmin` + alias `super_admin.only` en bootstrap/app.php
- [x] Services (`Services/SuperAdmin/`): PlatformMetricsAggregator
- [x] Actions: UpdateTenantStatusAction, UpdateTenantPlanAction
- [x] Form Requests: UpdateTenantStatusRequest, UpdateTenantPlanRequest
- [x] Controllers: DashboardController, TenantController, TenantStatusController, TenantPlanController
- [x] Resources: SuperAdminTenantResource, PlatformMetricsResource
- [x] Notifications: TenantSuspendedNotification, TenantRestoredNotification, TenantPlanChangedNotification (queue + Blade templates)
- [x] Policy: SuperAdminTenantPolicy (Gate `manage-platform-tenant`)
- [x] Routes: Route::domain(config('montree.super_admin_host')) groups en api.php + web.php
- [x] Tests feature (4) + unit (1) + middleware (1)
- [x] Wayfinder generate
- [x] Pint + tests pasan (124/124)

## Frontend
- [x] Layout SuperAdminLayout + SuperAdminSidebar
- [x] Pages: SuperAdmin/Dashboard, SuperAdmin/Tenant/Index, SuperAdmin/Tenant/Detail
- [x] Organisms: TenantTable, PlatformStats, TenantDetailPanel
- [x] Molecules: PlanBadge, TenantStatusBadge, StatusChanger, PlanChanger, PlatformStatCard
- [x] app.ts: pages SuperAdmin/* → SuperAdminLayout
- [x] Types: types/super-admin.ts
- [x] types-check (no new errors), lint clean, build OK

## Review
- [x] Tests + lint + types verde
- [x] Acceso bloqueado para non-super-admins (403)
- [x] Notifications encoladas correctamente

---

## Notas
- `2026-05-17` (claude principal): F015 arrancado.
- `2026-05-17` (agent F015): F015 completado end-to-end. 124 tests passing (24 nuevos super-admin). Notas de implementación:
  - **Gate** `manage-platform-tenant` (no `manage`) para no chocar con el `update` del `TenantPolicy` existente.
  - **Config** `config/montree.php` con clave `super_admin_host` (env `MONTREE_SUPER_ADMIN_HOST`) para que `Route::domain()` sea configurable por entorno.
  - **PaymentMetrics**: comisión hardcoded a 3% en `PlatformMetricsAggregator` — mover a config si se requiere flexibilidad.
  - **TenantController::applySort**: rechaza ordenar por `bookings_count`/`revenue` (requeriría JOIN/subquery) — fallback a `created_at`. Documentar en spec si es importante.
  - **useHttp** no acepta body — Detail.vue usa `router.visit()` para PATCH (alternativa documentada en docs de Inertia v3).
  - Pre-existentes (no tocados): wayfinder `.form` errors en otros files (no relacionados con F015).
