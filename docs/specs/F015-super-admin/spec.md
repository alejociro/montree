# F015 — Super admin

## Descripción

Panel de administración global de la plataforma. Gestionar tenants, cambiar planes, suspender agencias, métricas agregadas.

## User stories

- Como super admin, quiero ver todos los tenants con estado y plan.
- Como super admin, quiero suspender tenant que incumple términos.
- Como super admin, quiero cambiar plan de un tenant.
- Como super admin, quiero ver métricas globales de la plataforma.
- Como super admin, quiero buscar tenants por nombre o slug.

## Acceptance criteria

- **Given** super admin en panel, **then** ve listado de todos los tenants con stats.
- **Given** suspender tenant, **when** ejecuta, **then** tenant queda inaccesible y se notifica al admin.
- **Given** cambio de plan, **when** se actualiza, **then** nuevos límites aplican inmediatamente.
- **Given** dashboard global, **then** muestra: total tenants, usuarios, revenue plataforma (comisiones), bookings del mes.
- **Given** búsqueda "eco", **then** filtra tenants cuyo nombre o slug contiene "eco".
- **Given** usuario normal intenta acceder, **then** `403`.

## Edge cases

- Suspender tenant con reservas futuras: las reservas se mantienen, no se crean nuevas.
- Bajar plan que excede nuevos límites: advertir pero permitir (soft limit).
- Super admin que también es admin de un tenant: roles independientes.
- Tenant en trial con `trial_ends_at` pasado: auto-suspender via scheduler.

## Dependencias

- F002 (Tenants existen).

## Endpoints involucrados

```
GET    /api/v1/super-admin/tenants
GET    /api/v1/super-admin/tenants/{id}
PATCH  /api/v1/super-admin/tenants/{id}/status
PATCH  /api/v1/super-admin/tenants/{id}/plan
GET    /api/v1/super-admin/dashboard
```

## Componentes UI

- Pages: `SuperAdminDashboardPage`, `SuperAdminTenantsPage`, `SuperAdminTenantDetailPage`
- Organisms: `TenantTable`, `PlatformStats`, `TenantDetail`
- Molecules: `TenantRow`, `PlanBadge`, `StatusChanger`, `PlatformStatCard`
- Atoms: `Badge`, `BaseButton`, `SearchInput`, `ConfirmDialog`

## Datos requeridos

Tablas: `tenants`, `tenant_configurations`, `users`, `bookings`, `payments`

---

## Decisiones abiertas

- [ ] ¿Subdominio reservado `admin.montree.app` o ruta `/super-admin` en la plataforma?

## Changelog

- `2026-05-17` — Creación inicial.
