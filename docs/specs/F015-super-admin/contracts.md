# F015 — Contratos de API

> Super admin opera en hostname reservado `admin.montree.test/.app`. Las rutas
> y pages viven separadas de las admin per-tenant.

## Middleware

Toda ruta de super admin requiere:
1. `auth` (sesión válida)
2. `super_admin.only` middleware nuevo (`EnsureSuperAdmin`): verifica que el user tiene rol `super_admin` con team_id=0 (sentinel). Si no → 403.
3. NO requiere tenant resuelto (super admin opera fuera de tenant context).

---

## GET /api/v1/super-admin/dashboard

**Auth:** super_admin only.

Métricas globales de la plataforma.

### Response 200
```json
{
  "data": {
    "totals": {
      "tenants": 24,
      "active_tenants": 21,
      "users": 412,
      "bookings_this_month": 187,
      "revenue_this_month": "45680000.00",
      "platform_commission_this_month": "1370400.00"
    },
    "growth": {
      "tenants_new_this_month": 3,
      "bookings_growth_pct": 18.4
    },
    "plan_distribution": {
      "basic": 12,
      "professional": 9,
      "enterprise": 3
    }
  }
}
```

---

## GET /api/v1/super-admin/tenants

**Auth:** super_admin.

Lista paginada de tenants con filtros.

### Query params
- `search?: string` (matches name/slug)
- `status?: 'active' | 'suspended' | 'pending'`
- `plan?: 'basic' | 'professional' | 'enterprise'`
- `sort?: 'created_at' | 'name' | 'bookings_count' | 'revenue'`
- `direction?: 'asc' | 'desc'`
- `per_page?: int`

### Response 200
```json
{
  "data": [
    {
      "id": 1,
      "slug": "demo",
      "name": "Demo Eco Adventures",
      "domain": "demo.montree.test",
      "status": "active",
      "plan": "professional",
      "trial_ends_at": null,
      "contact_email": "...",
      "users_count": 5,
      "tours_count": 5,
      "bookings_count_30d": 28,
      "revenue_30d": "8400000.00",
      "created_at": "2026-05-01T00:00:00Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

---

## GET /api/v1/super-admin/tenants/{id}

Detalle ampliado: agregar `configuration`, `users` (paginado o limitado), `recent_bookings`, etc.

---

## PATCH /api/v1/super-admin/tenants/{id}/status

### Request
```json
{ "status": "suspended", "reason": "Pago vencido" }
```

**Validación:**
- `status`: required, in:active,suspended,pending
- `reason`: nullable, string, max:500 (obligatorio cuando status=suspended)

### Response 200
Devuelve el tenant actualizado.

### Side-effects
- Si pasa a `suspended`: notificar al admin del tenant por email (queue) con razón.
- Si pasa a `active` desde `suspended`: notificar restitución.

### Errores
| Status | Caso |
|---|---|
| 422 | reason ausente al suspender |
| 409 | Estado actual = nuevo (no-op) |

---

## PATCH /api/v1/super-admin/tenants/{id}/plan

### Request
```json
{ "plan": "enterprise" }
```

**Validación:** `plan`: required, in:basic,professional,enterprise.

### Side-effects
- Aplica límites del nuevo plan inmediatamente.
- Si downgrade y excede límites: NO se eliminan datos. Se permite (soft limit). Se loguea warning.
- Notificar admin del tenant del cambio de plan (queue).

### Response 200
Tenant actualizado.

---

## Inertia pages

| Route | Page |
|---|---|
| `/super-admin/dashboard` | `SuperAdmin/Dashboard.vue` |
| `/super-admin/tenants` | `SuperAdmin/Tenant/Index.vue` |
| `/super-admin/tenants/{id}` | `SuperAdmin/Tenant/Detail.vue` |

Acceso solo via host `admin.montree.test/.app` (host reservado, NO tenant resuelto). En otros hosts → 404.

Layout: `SuperAdminLayout.vue` (separado de AdminLayout — tema gris-azul para distinguir).

---

## Notificaciones

- `App\Notifications\SuperAdmin\TenantSuspendedNotification` (al admin del tenant)
- `App\Notifications\SuperAdmin\TenantRestoredNotification`
- `App\Notifications\SuperAdmin\TenantPlanChangedNotification`

Todas con queue (ShouldQueue).

---

## Cambios
- `2026-05-17` — Creación inicial.
