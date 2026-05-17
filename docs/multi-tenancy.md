# Multi-tenancy — Estrategia y reglas

> MONTREE usa **single database + columna `tenant_id`** con el paquete
> [`spatie/laravel-multitenancy`](https://spatie.be/docs/laravel-multitenancy)
> como mecanismo de resolución y aislamiento.

---

## 1. Por qué single DB

- Volumen esperado por tenant: 5–50 tours activos, decenas/miles de reservas/mes. No justifica overhead de schema-per-tenant.
- Costo operativo bajo: una sola conexión, una sola migración, backups simples.
- Migraciones atómicas para toda la plataforma.

**Compromiso aceptado:** un bug en el global scope puede leakear data entre tenants. Mitigamos con:
- Global scope automático aplicado vía trait.
- Tests obligatorios de aislamiento por modelo tenant-scoped.
- Auditoría periódica de queries (Laravel Telescope en local/staging).

---

## 2. Resolución de tenant

- **Por subdominio.** `eco-adventures.montree.app` → tenant cuyo `slug = 'eco-adventures'`.
- Configurar `TenantFinder` custom basado en host: extrae el primer label y busca `tenants.slug`.
- Fallback: si el host es `montree.app` o `www.montree.app` → **NO hay tenant** (landing pública de la plataforma).
- Si el subdominio no existe → 404 con página genérica.
- Si el tenant está `status = 'suspended'` → 503 con página de "temporalmente no disponible".

### 2.1 Local dev

- Usar `*.montree.test` con Valet/Sail. Configurar dnsmasq o `/etc/hosts` para resolver `*.montree.test` → 127.0.0.1.
- Tenant de prueba: `demo.montree.test`.

---

## 3. Modelos tenant-scoped

Toda tabla que pertenezca a un tenant lleva:
- Columna `tenant_id BIGINT UNSIGNED NOT NULL` con FK a `tenants`.
- Índice en `tenant_id`.
- Índice compuesto con la siguiente columna más usada en filtros (p.ej. `(tenant_id, status)`).

El modelo:
- Usa el trait `UsesLandlordConnection` o el equivalente del paquete + global scope que filtra por `currentTenant()`.
- En boot del modelo, al crear: `tenant_id` se asigna automáticamente del tenant actual (no se pasa manualmente).

### 3.1 Tablas tenant-scoped (todas las del scope inicial)

```
tours, tour_images, tour_itineraries, tour_dates,
bookings, booking_travelers, payments,
reviews, favorites, notifications,
promotions, newsletter_subscribers, categories,
tenant_configurations
```

### 3.2 Tablas NO tenant-scoped (landlord)

```
tenants, users, tenant_user (pivote)
```

`users` es global: un mismo usuario puede pertenecer a varios tenants vía `tenant_user`.

---

## 4. Roles y permisos

- Paquete: `spatie/laravel-permission`.
- **Roles** definidos en seeder, no editables por UI:
  - `super_admin` (global, sin tenant)
  - `admin` (por tenant)
  - `operator` (por tenant)
  - `guide` (por tenant)
  - `customer` (por tenant)
- **Permissions** sí editables por super_admin (futuro). Por ahora se definen en seeder y se asignan al rol.
- Roles se asignan **por team** (team = tenant). Activar feature `teams` de spatie/permission.
- En cada request, después de resolver tenant, se setea el team_id del paquete con el tenant actual.

---

## 5. Reglas de query

- **NUNCA** filtrar por `tenant_id` manualmente en código de aplicación. Si lo ves, el modelo está mal configurado.
- **NUNCA** desactivar el global scope con `withoutGlobalScope` salvo en jobs/comandos super_admin explícitos, justificado por comentario `// WHY: ...`.
- Los seeders y factories que crean data tenant-scoped DEBEN setear `Tenants::current()` antes de crear.

---

## 6. Tests de aislamiento

Por cada modelo tenant-scoped, debe existir un test que pruebe:

```php
public function test_tenant_a_no_ve_recursos_de_tenant_b(): void
{
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $tenantA->makeCurrent();
    Tour::factory()->create(['name' => 'Tour A']);

    $tenantB->makeCurrent();
    Tour::factory()->create(['name' => 'Tour B']);

    $tenantA->makeCurrent();
    $this->assertEquals(['Tour A'], Tour::pluck('name')->all());
}
```

Sin este test, el modelo no se considera completo.

---

## 7. Super admin

- Accede a `admin.montree.app` (subdominio reservado).
- Salta el tenant resolver: trabaja con `Tenant::all()`.
- Usa rutas `/api/v1/super-admin/...` protegidas por middleware `role:super_admin`.
- Cuando edita un tenant específico, debe usar `Tenants::current($tenant)->execute(fn() => ...)`.

---

## 8. Tareas que el `montree-db-architect` debe ejecutar al setup inicial

1. Instalar `spatie/laravel-multitenancy` y `spatie/laravel-permission` (requiere aprobación de PR).
2. Publicar configs.
3. Crear migration de `tenants`, `tenant_configurations`, `tenant_user`, roles/permissions de spatie.
4. Configurar `TenantFinder` por subdominio.
5. Crear trait `BelongsToTenant` que los modelos tenant-scoped van a usar.
6. Crear seeder de roles y un tenant `demo`.
7. Documentar en este archivo cualquier desviación que descubra.

---

## 9. Implementación efectiva (2026-05-17)

Estas son las decisiones de implementación que el `montree-db-architect` aplicó al
montar el schema completo. Conviértelas en convención al construir features:

### 9.1 Resolver de tenant

- Vive en `App\Multitenancy\SubdomainTenantFinder` (extiende
  `Spatie\Multitenancy\TenantFinder\TenantFinder`).
- Configurado en `config/multitenancy.php` vía `'tenant_finder' => SubdomainTenantFinder::class`.
- Hosts reservados que devuelven `null` (sin tenant): `montree.app`, `www.montree.app`,
  `montree.test`, `www.montree.test`, `admin.montree.app`, `admin.montree.test`,
  `localhost`, `127.0.0.1`.
- El subdomain se valida contra `^[a-z0-9][a-z0-9-]{1,62}$`. Si no matchea: `null`.
- Las conexiones `landlord_database_connection_name` y `tenant_database_connection_name`
  quedan en `null` para usar la misma conexión por default (single-DB).

### 9.2 Trait `BelongsToTenant`

- `App\Concerns\BelongsToTenant`.
- Registra global scope `'tenant'` que filtra por `Tenant::current()`. Si no hay
  tenant actual, NO aplica filtro (esto permite jobs de super_admin sin scope).
- Hookeás `creating`: si no hay `tenant_id` setteado y no hay tenant actual,
  lanza `RuntimeException`. Esto es deliberado — falla rápido si alguien intenta
  escribir sin contexto.
- Provee relación `tenant()` automática.

### 9.3 RBAC con spatie/permission

- `config/permission.php` tiene `teams => true` y `team_foreign_key => 'tenant_id'`.
- La migration publicada agrega `tenant_id` (NOT NULL) a `roles`, `model_has_roles`
  y `model_has_permissions` como parte de la PK compuesta.
- **Sentinel para super_admin**: como super_admin es global (sin tenant), al
  asignarle el rol se usa `setPermissionsTeamId(0)`. El valor `0` actúa como
  "no tenant" — no choca con ningún `tenants.id` autoincrement (que arranca en 1).
- Para roles por tenant: llamar `setPermissionsTeamId($tenant->id)` antes de
  `assignRole`/`syncRoles`. La middleware de tenant resolution debe hacerlo
  después de identificar el tenant.

### 9.4 Tabla `notifications`

- Usa el shape default de Laravel (`uuid` PK, `morphs notifiable`, `json data`)
  más una columna nullable `tenant_id`. Es nullable para soportar notificaciones
  globales del super_admin. Sigue siendo tenant-aware vía el campo cuando aplica.
- **No usa el trait `BelongsToTenant`** porque Laravel ya tiene su propia
  Notification model y el filtrado se hace por `notifiable` (el usuario), no por
  global scope. Si más adelante hace falta scope automático, agregar.

### 9.5 `Booking::booking_number`

- UUID generado automáticamente en `booted::creating` si no se pasa explícitamente.
- `getRouteKeyName()` devuelve `booking_number` — todas las rutas públicas se
  bindean por UUID, nunca por PK interno.

### 9.6 `tour_dates.guide_id`

- FK a `users` (no a `tenant_user`). El guide es un User; la relación con el
  tenant se valida en la Policy/Action (debe ser miembro `active` del tenant
  actual con rol `guide`).
- `nullOnDelete()` para que si el guide es removido la fecha quede sin asignar.

### 9.7 Soft deletes

- Aplicado en `tours`, `bookings`, `reviews` (la spec lo justifica: historial
  para reportes/auditoría, integridad referencial post-archivo).
- Los demás eliminación dura (categories, favorites, promotions, etc.).

### 9.8 Seeders

- `RolesAndPermissionsSeeder` — crea los 5 roles globales (con `tenant_id = null`
  en la tabla `roles` — son catálogo, no asignaciones).
- `DemoTenantSeeder` — crea tenant `demo.montree.test`, su `tenant_configuration`,
  un super_admin global y un admin/operator/guide/customer del tenant. Más
  3 categorías y 5 tours activos con imagen, itinerario y 2 fechas futuras cada uno.
- `DatabaseSeeder` llama a ambos.

### 9.9 Tests de aislamiento

- Cubiertos en `tests/Feature/Tenant/TenantIsolationTest.php` con un test por
  modelo tenant-scoped (14 modelos cubiertos). Incluye un test que verifica que
  crear un modelo sin tenant actual lanza excepción.

---

## Changelog

- `2026-05-17` — Versión inicial.
- `2026-05-17` — Sección 9 añadida con decisiones de implementación efectiva
  (subdomain finder con hosts reservados, sentinel team_id=0 para super_admin,
  detalles de cada tabla y soft delete).
