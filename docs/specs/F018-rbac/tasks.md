# F018 — Tasks

> Checklist atómico. Cada item se asigna a un rol y se marca al terminar.
> Generado a partir de `plan.md`. Modificaciones se reflejan en ambos.
> Skill obligatoria para todo item de este archivo: `laravel-best-practices`
> (cargarla al inicio de cada sesión de `montree-backend-dev` sobre F018).

---

## Backend (`montree-backend-dev`)

- [ ] `App\Enums\UserRole` — agregar case `Sales = 'sales'`, `label()` → `'Vendedor'`; pasar los 5 labels existentes a español si no lo están
- [ ] `database/seeders/RolesAndPermissionsSeeder.php` — método `seedPermissions()`: 37 permisos `modulo.accion` (catálogo completo en `spec.md` §"Catálogo de permisos")
- [ ] `database/seeders/RolesAndPermissionsSeeder.php` — método `seedRolePermissions()`: matriz rol→permiso (`rbacbase.md` §5), separado de `seedPermissions()`
- [ ] `database/seeders/RolesAndPermissionsSeeder.php` — rutina de corte: todo usuario con rol `operator` recibe también `sales` (idempotente, no duplica si ya lo tiene)
- [ ] `app/Providers/AppServiceProvider.php` — `Gate::before` para `super_admin`, retorna `null` (no `false`) para el resto
- [ ] `DashboardPolicy` — `hasRole()` → `can('dashboard.view' | 'reports.view' | 'reports.export')`
- [ ] `TourPolicy` — `hasRole()` → `can('tours.*')`, sin tocar reglas de `guide` ya correctas
- [ ] `TenantPolicy` — `hasRole()` → `can('tenant.*')`
- [ ] `PromotionPolicy` — `hasRole()` → `can('promotions.*')`
- [ ] `ReviewPolicy` — `hasRole()` → `can('reviews.*')`
- [ ] Cerrar huecos sin Policy propia: `BookingController`, `TourDateController`, `RevenueReportController`, `TourImageController::destroy`, `NewsletterController::index`, `ReviewController::index`, logística (routes/providers/hotels) — Policy nueva si el modelo la amerita, `authorize()` directo si no hay modelo dedicado
- [ ] Form Requests → `can()`: `Admin/Team/UpdateMemberRoleRequest`, `InviteMemberRequest`, `Review/ModerateReviewRequest`, `RespondReviewRequest`, `Newsletter/SendCampaignRequest`
- [ ] `App\Actions\Team\UpdateMemberRoleAction` (extraer de `TeamController` si no existe) — verifica pertenencia al tenant antes de tocar roles, lanza `CrossTenantAccessException` si no (cierre de B3)
- [ ] Mismo chequeo de pertenencia en `suspend`/`reactivate` de `TeamController` (reusar `User::isActiveMemberOf`, no triplicar)
- [ ] `routes/api.php` (líneas 93-135) y rutas `guide/*` — `->middleware('can:<permiso>')` por ruta según el mapa de `contracts.md` §1, retirar `tenant_admin.only`/`tenant_guide.only` de esas rutas (la membresía la sigue validando `EnsureTenantAdmin`/`EnsureTenantGuide` si aplica, no se borra la clase)
- [ ] `AuthUserResource` — `tenantRole: string` → `permissions: array` (shape en `contracts.md` §3)
- [ ] `HandleInertiaRequests` — comparte `auth.permissions`, deja de compartir `tenantRole`
- [ ] Actualizar `tests/Feature/Rbac/CurrentAdminAccessMatrixTest.php` (Fase 0) celda por celda donde este feature cambia el resultado esperado
- [ ] `tests/Feature/Rbac/PermissionCatalogSeederTest.php` — happy (37 permisos agrupados) + edge (seeder idempotente)
- [ ] `tests/Feature/Rbac/RoleMigrationSeederTest.php` — happy (operator recibe sales) + failure (usuario sin operator no se toca)
- [ ] `tests/Feature/Team/UpdateMemberRoleActionTest.php` — happy (mismo tenant) + failure (`CROSS_TENANT_ACCESS`, cross-tenant) + tenant isolation
- [ ] 1 test de failure (403 sin permiso) por cada endpoint del mapa de `contracts.md` §1 que no lo tenga ya
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=Rbac`
- [ ] `php artisan test --compact` (suite completa, no solo el filtro)

## Frontend (`montree-frontend-dev`)

- [ ] `resources/js/types/auth.ts` — `AuthUser.tenantRole: string` → `AuthUser.permissions: string[]`
- [ ] `npm run types:check` (va a fallar en los 4 sidebars que leen `tenantRole` — esperado, se resuelve en Fase 2; documentar el fallo esperado en el reporte, no silenciarlo)

Nada más de frontend en este feature — composable `can()` y reescritura de sidebars quedan en Fase 2 (`spec.md` §"Out of scope").

## DB (`montree-db-architect`, solo si hay cambios de schema)

- No aplica. Sin migraciones nuevas (ver `plan.md` §2 Migrations).

## Review (`montree-reviewer`)

- [ ] Tests pasan (suite completa, no solo `Rbac`)
- [ ] Pint pasa
- [ ] Cero ocurrencias de `hasRole(` en `app/Policies`, `app/Http/Requests`, `app/Http/Middleware` salvo el chequeo de `super_admin` (criterio de salida de `planfase.md` Fase 1)
- [ ] `permissions` = 37 filas, `role_has_permissions` poblada, `roles` = 6, verificado por query real, no por lectura de código
- [ ] Operador → `/admin/team` responde 403, no 200 (verificado con test, no manual)
- [ ] Spec (`spec.md`) cubierta 100%, incluidos los 4 "Given/When/Then" de "Cierre de huecos de autorización"
- [ ] Constitución respetada (§3.2 capas, §2 principios de diseño)
- [ ] Sin código muerto: `EnsureTenantAdmin`/`EnsureTenantGuide` no quedan huérfanas sin uso si se retiraron de todas las rutas admin — confirmar que siguen usadas donde corresponde o se documenta por qué no
- [ ] N+1 check en `AuthUserResource`/`HandleInertiaRequests` (permisos ya cacheados por Spatie, confirmar que no se re-consultan por request)
- [ ] Reporte final con go/no-go

---

## Bloqueos / Decisiones pendientes

- [ ] Ninguno bloqueante para arrancar. 0.4 (inventario `operator` en demo/prod) es requisito de **despliegue**, no de implementación — ver `plan.md` §6 Riesgos.

## Notas durante implementación

- `2026-08-16` (Claude principal): `contracts.md`/`plan.md`/`tasks.md` escritos al cerrar Fase 0 completa, corrigiendo que 0.2 se había marcado hecha con solo `spec.md`. Rama `feature/F018-rbac` recreada desde `develop` (estaba mal creada desde `main`, que quedó 9 commits atrás). Ningún seeder se tocó todavía — `RolesAndPermissionsSeeder.php` sigue sin modificar; poblarlo es la primera tarea de implementación de esta lista, no algo ya hecho.
