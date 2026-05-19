# MONTREE — Setup local

> Cómo levantar el proyecto y empezar a usarlo en tu máquina.

---

## 1. Pre-requisitos

- PHP 8.4 (chequear: `php -v`)
- Composer 2+
- Node.js 22+ y npm 10+
- MySQL 8+ (o MariaDB 11+)
- Git

## 2. Clonar e instalar

```bash
git clone <repo-url> montree
cd montree
composer install
npm install
cp .env.example .env
php artisan key:generate
```

## 3. Base de datos

Crear la BD en tu MySQL local:

```sql
CREATE DATABASE montree CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Editar `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=montree
DB_USERNAME=root
DB_PASSWORD=
```

Correr migrations + seed:

```bash
php artisan migrate:fresh --seed
```

Esto crea **27 tablas** y siembra:
- 1 super admin global
- 1 tenant `demo` con configuración base
- 4 usuarios (admin/operator/guide/customer) afiliados al tenant demo
- 3 categorías
- 5 tours con imágenes, itinerarios y fechas futuras

## 4. Hosts locales

MONTREE resuelve tenants por subdominio. Agregá a `/etc/hosts`:

```
127.0.0.1 demo.montree.test
127.0.0.1 admin.montree.test
127.0.0.1 montree.test
```

(En Mac/Linux: `sudo nano /etc/hosts`. En Windows: `C:\Windows\System32\drivers\etc\hosts`.)

> **Importante:** los hosts reservados (`www`, `admin`, `api`, `localhost`, `127.0.0.1`, `montree.test` raíz) NO resuelven a tenant — muestran landing genérica o pages de error.

## 5. Levantar el server

```bash
composer dev
```

Esto arranca en paralelo:
- `php artisan serve` (puerto 8000)
- `php artisan queue:listen`
- `php artisan pail` (logs en vivo)
- `npm run dev` (Vite)

Si preferís manual:
```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev

# Terminal 3 (opcional, para queues/notificaciones)
php artisan queue:listen
```

## 6. URLs principales

| URL | Qué muestra |
|---|---|
| `http://demo.montree.test:8000/` | Welcome del tenant demo (con branding verde + tagline) |
| `http://demo.montree.test:8000/login` | Login con logo y colores del tenant |
| `http://demo.montree.test:8000/register` | Registro (crea customer en el tenant demo automáticamente) |
| `http://demo.montree.test:8000/dashboard` | Dashboard genérico del starter (post-login) |
| `http://demo.montree.test:8000/admin/tenant/configuration` | Editor de branding del tenant (requiere admin) |
| `http://noexiste.montree.test:8000/` | 404 `TenantNotFound` |
| `http://localhost:8000/` | Landing plataforma (sin tenant) |

## 7. Usuarios de prueba

Todos con password: **`password`**

| Email | Rol | Tenant |
|---|---|---|
| `super@montree.test` | super_admin | — (global) |
| `admin@demo.montree.test` | admin | demo |
| `operator@demo.montree.test` | operator | demo |
| `guide@demo.montree.test` | guide | demo |
| `customer@demo.montree.test` | customer | demo |

## 8. Probar el flujo de configuración del tenant

1. Login con `admin@demo.montree.test`
2. Ir a `/admin/tenant/configuration`
3. Cambiar `primary_color` (probá un `#10b981`)
4. Guardar
5. La paleta de la app cambia en vivo (el `useTenantBranding` composable aplica los nuevos HSL al `:root`)
6. Reload de cualquier página confirma que se persistió

## 9. Probar el flujo de registro

1. Logout
2. `http://demo.montree.test:8000/register`
3. Crear cuenta nueva con email cualquiera (ej. `test@example.com`)
4. Quedás logueado como `customer` del tenant demo
5. Se envía email de verificación (vista en `php artisan pail` si no tenés mail server configurado; o en `storage/logs/laravel.log`)

## 10. Probar el flujo de login con membership suspended

Vía tinker:

```bash
php artisan tinker --execute "
\$user = App\Models\User::where('email', 'customer@demo.montree.test')->first();
\$tenant = App\Models\Tenant::where('slug', 'demo')->first();
\$tenant->users()->updateExistingPivot(\$user->id, ['status' => 'suspended']);
echo 'Suspended';
"
```

Luego intentar login con `customer@demo.montree.test`/`password` → vas a ver un Alert destacado con CTA mailto al admin.

Para deshacer:
```bash
php artisan tinker --execute "
\$user = App\Models\User::where('email', 'customer@demo.montree.test')->first();
\$tenant = App\Models\Tenant::where('slug', 'demo')->first();
\$tenant->users()->updateExistingPivot(\$user->id, ['status' => 'active']);
"
```

## 11. Comandos útiles del día a día

```bash
# Tests
php artisan test --compact                          # toda la suite
php artisan test --compact --filter=Tenant          # solo tests con "Tenant" en el nombre
php artisan test --compact tests/Feature/Auth/      # carpeta específica

# Code style
vendor/bin/pint --dirty --format agent              # formatea solo archivos modificados
vendor/bin/pint --test --format agent               # verifica sin modificar

# Frontend
npm run dev                                          # Vite watch
npm run build                                        # build producción
npm run types:check                                  # vue-tsc
npm run lint                                         # eslint --fix
npm run format                                       # prettier --write

# Routes
php artisan route:list --except-vendor              # rutas de la app
php artisan route:list --path=api/v1                # rutas API v1

# DB
php artisan migrate:fresh --seed                    # reset + seed
php artisan tinker                                   # REPL

# Wayfinder (regenerar tipos de rutas para frontend)
php artisan wayfinder:generate

# Logs
php artisan pail                                     # logs en vivo
tail -f storage/logs/laravel.log                    # alternativa
```

## 12. Estructura del proyecto

```
app/
├── Actions/                # Casos de uso (verb-per-class)
│   ├── Fortify/           # Auth actions custom
│   └── Tenant/
├── Concerns/              # Traits (ej. BelongsToTenant)
├── Enums/                 # Status, plan, role enums
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/Api/V1/  # API endpoints
│   ├── Controllers/Errors/  # Inertia error pages
│   ├── Middleware/         # ResolveTenant, HandleInertiaRequests
│   ├── Requests/           # Form Requests
│   ├── Resources/          # API Resources
│   └── Responses/          # Fortify response overrides
├── Listeners/
├── Models/                # Eloquent models
├── Multitenancy/          # SubdomainTenantFinder
├── Notifications/Auth/
├── Observers/
├── Policies/
├── Providers/
└── Services/Tenant/       # Stateless services

resources/
├── css/app.css            # Tokens shadcn-vue (paleta verde MONTREE)
├── js/
│   ├── components/
│   │   ├── atoms/         # TenantBrandedLogo, BaseInput-like
│   │   ├── molecules/     # ColorPicker, PreviewPanel, etc.
│   │   ├── organisms/     # BrandingEditor, OperationalSettingsForm
│   │   └── ui/            # shadcn-vue components
│   ├── composables/       # useTenant, useTenantBranding
│   ├── layouts/           # AppLayout, AuthLayout, auth/*
│   ├── pages/             # Inertia pages
│   ├── types/             # TS interfaces (auth, tenant)
│   └── app.ts
└── views/emails/          # Blade templates de notifications

docs/
├── constitution.md        # Reglas técnicas inmutables
├── multi-tenancy.md       # Estrategia single DB + tenant_id
├── api-conventions.md
├── testing-policy.md
├── workflow.md            # Cómo trabajar una feature
├── local-setup.md         # Este archivo
└── specs/                 # 15 features (F001..F015)
    ├── F001-auth/{spec,contracts,plan,tasks}.md
    └── ...

.claude/
├── agents/                # Sub-agents especializados
│   ├── montree-db-architect.md
│   ├── montree-backend-dev.md
│   ├── montree-frontend-dev.md
│   ├── montree-reviewer.md
│   └── montree-spec-updater.md
└── commands/              # Slash commands
    ├── feature-start.md
    ├── feature-review.md
    └── feature-status.md
```

## 13. Flujo de trabajo con IA (Claude Code)

Ver [`workflow.md`](./workflow.md) para el detalle. Resumen:

1. `/feature-start F0XX` — escribe `contracts.md` + `plan.md` + `tasks.md`, crea branch.
2. Implementar (manual o con sub-agents `montree-backend-dev` / `montree-frontend-dev`).
3. `/feature-review F0XX` — invoca al reviewer.
4. Si GO → merge a `feature/administration-process` (rama integration).
5. Cuando todo el bloque admin esté terminado → merge a `main`.

## 14. Troubleshooting

**"Unable to locate file in Vite manifest"** → corré `npm run build` o `npm run dev`.

**Tests fallan tras `composer require`** → corré `php artisan config:clear && composer dump-autoload && php artisan migrate:fresh --seed`.

**Subdomain no resuelve en local** → verificar `/etc/hosts` y que `php artisan serve` está corriendo en el puerto que estás usando.

**`spatie/permission` "team_id cannot be null"** → siempre llamar `setPermissionsTeamId($tenant->id)` antes de `syncRoles()` o `assignRole()`. Para super_admin global usar `setPermissionsTeamId(0)` (sentinel — ver `docs/multi-tenancy.md` §9.3).

**Email del tenant no llega** → en dev sin SMTP, los emails se loguean en `storage/logs/laravel.log` (driver `log`). Configurar mailtrap/mailpit si querés ver HTML rendered.

**Wayfinder genera tipos incorrectos** → `php artisan wayfinder:generate` después de cambiar rutas backend; el frontend importa de `@/actions/...` y `@/routes/...` (gitignored, locales).
