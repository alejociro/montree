# F018 — Contratos de API

> Shapes exactos de request y response. Es CONTRATO: backend y frontend
> se basan en este archivo. Modificar requiere acuerdo de ambos lados.
>
> F018 no agrega endpoints nuevos: cierra el catálogo de permisos y cambia
> la regla de autorización de rutas `admin/*` y `guide/*` ya existentes.
> El contrato de este feature es doble: (1) qué permiso exige cada ruta,
> y (2) el shape nuevo que el backend expone al frontend para que la UI
> sepa qué puede mostrar.

---

## 1. Mapa ruta → permiso (`routes/api.php:93-135`, grupo `admin/*`, y `guide/*`)

Reemplaza el gate de grupo (`tenant_admin.only` / `tenant_guide.only`) por
`can:<permiso>` por ruta. Fuente: matriz cerrada en `rbacbase.md` (kit
p2p-kits) y huecos confirmados en `CurrentAdminAccessMatrixTest.php`.

| Método | Ruta | Permiso |
|---|---|---|
| GET | `/admin/dashboard` | `dashboard.view` |
| GET | `/admin/reports` | `reports.view` |
| GET/POST | `/admin/reports/export` | `reports.export` |
| GET | `/admin/tours` | `tours.view` |
| POST | `/admin/tours` | `tours.create` |
| PUT/PATCH | `/admin/tours/{tour}` | `tours.update` |
| PATCH | `/admin/tours/{tour}/publish` | `tours.publish` |
| DELETE | `/admin/tours/{tour}` | `tours.delete` |
| POST/DELETE | `/admin/tours/{tour}/images/*` | `tours.images.manage` |
| GET | `/admin/tour-dates` | `departures.view` |
| POST | `/admin/tour-dates` | `departures.create` |
| PUT/PATCH | `/admin/tour-dates/{tourDate}` | `departures.update` |
| PATCH | `/admin/tour-dates/{tourDate}/cancel` | `departures.cancel` |
| DELETE | `/admin/tour-dates/{tourDate}` | `departures.delete` |
| PATCH | `/admin/tour-dates/{tourDate}/guide` | `departures.assign_guide` |
| GET | `/admin/logistics/{routes,providers,hotels}` | `logistics.view` |
| POST/PUT/DELETE | `/admin/logistics/{routes,providers,hotels}/*` | `logistics.manage` |
| GET | `/admin/bookings` | `bookings.view` |
| PATCH | `/admin/bookings/{booking}` | `bookings.update` |
| POST | `/admin/bookings/{booking}/refund` | `payments.refund` |
| GET | `/admin/promotions` | `promotions.view` |
| POST | `/admin/promotions` | `promotions.create` |
| PUT/PATCH | `/admin/promotions/{promotion}` | `promotions.update` |
| DELETE | `/admin/promotions/{promotion}` | `promotions.delete` |
| GET | `/admin/newsletter/subscribers` | `newsletter.view` |
| POST | `/admin/newsletter/campaigns` | `newsletter.send` |
| GET | `/admin/reviews` | `reviews.view` |
| PATCH | `/admin/reviews/{review}/moderate` | `reviews.moderate` |
| POST | `/admin/reviews/{review}/respond` | `reviews.respond` |
| GET | `/admin/team` | `team.view` |
| POST | `/admin/team/invite` | `team.invite` |
| PATCH | `/admin/team/{user}/role` | `team.role.update` |
| PATCH | `/admin/team/{user}/{suspend,reactivate}` | `team.suspend` |
| GET | `/admin/tenant/settings` | `tenant.view` |
| PUT/PATCH | `/admin/tenant/settings` | `tenant.update` / `tenant.settings.update` |
| GET | `/guide/schedule` | `guide.schedule.view` |
| GET | `/guide/tour-dates/{tourDate}/travelers` | `guide.travelers.view` |

`super_admin` no se lista: `Gate::before` lo autoriza siempre, antes de
llegar al chequeo `can:`.

## 2. `HandleInertiaRequests` — prop compartida `auth.permissions`

**Hoy** (`resources/js/types/auth.ts`):
```ts
interface AuthUser {
  // ...
  tenantRole: string
}
```

**Después de F018**:
```ts
interface AuthUser {
  // ...
  permissions: string[] // ej. ["tours.view", "tours.create", "bookings.view", ...]
}
```

- `tenantRole` se elimina del shape compartido — ningún componente de este
  feature debe seguir leyéndolo (los que hoy lo leen son parte del scope de
  Fase 2, no de este feature; ver "Out of scope" en `spec.md`).
- `permissions` es la unión de los permisos de **todos** los roles activos
  del usuario en el tenant actual (`$user->getAllPermissions()->pluck('name')`,
  ya resuelto por Spatie con `teams` activo).
- Para `super_admin`: `permissions` se llena con **los 37 nombres completos**
  (no un flag `is_super_admin` aparte) para que el frontend pueda usar el
  mismo helper `can()` sin caso especial. El backend sigue autorizando
  server-side vía `Gate::before`, este array es solo para UI.

## 3. `AuthUserResource`

**Response 200** (fragmento relevante, dentro de `props.auth.user` de cualquier
página Inertia de `admin/*` o `guide/*`):

```json
{
  "id": 42,
  "name": "Ana Ríos",
  "email": "ana@eco-adventures.test",
  "permissions": ["dashboard.view", "tours.view", "tours.create", "..."]
}
```

## 4. Errores — sin cambio de shape, cambia el motivo

Sigue el shape estándar de `api-conventions.md` §3. Lo único nuevo es que el
**motivo** de un 403 ahora es "falta el permiso X", no "el rol no es Y":

| Status | Caso | error_code | Mensaje |
|---|---|---|---|
| 403 | Usuario autenticado sin el permiso de la ruta | `INSUFFICIENT_PERMISSION` | "No tienes permiso para realizar esta acción." |
| 403 | `TeamController::updateRole/suspend/reactivate` sobre usuario de otro tenant (cierre de B3) | `CROSS_TENANT_ACCESS` | "El usuario no pertenece a esta agencia." |

`INSUFFICIENT_PERMISSION` reemplaza cualquier mensaje ad-hoc que hoy devuelven
las Policies con `hasRole()` embebido. `CROSS_TENANT_ACCESS` es nuevo (no
existía chequeo, ver `plan.md` §2 Actions / B3).

## 5. Eventos / Side-effects

- Ninguno nuevo. F018 no dispara eventos de dominio — es autorización pura.
- El seeder de corte (`RolesAndPermissionsSeeder`, ver `plan.md` §2) es un
  side-effect de despliegue, no de request: asigna `sales` + `operator` a
  todo usuario que hoy tiene `operator`, documentado en `spec.md` §"Migración
  de operator".

---

## Cambios al contrato

- `2026-08-16` — Creación inicial. Deriva de la matriz cerrada (`rbacbase.md`)
  y de los huecos confirmados por `CurrentAdminAccessMatrixTest.php`.
