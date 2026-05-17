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
