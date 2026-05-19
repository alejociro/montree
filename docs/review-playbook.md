# MONTREE — UX review playbook

Receta reproducible para re-correr el review UX end-to-end con Playwright MCP. Pensada para ejecutarse por Claude (vía MCP) o manualmente.

> **Cuándo invocar:** antes de mergear `feature/administration-process` a `main`, antes de cada release, o cuando se hayan tocado ≥3 features y querramos verificar regresiones.

---

## 1. Pre-requisitos

1. **Server corriendo:** `composer dev` (o `php artisan serve` + `npm run dev`) escuchando en `:8000`.
2. **`/etc/hosts`** resuelve `montree.test`, `demo.montree.test`, `admin.montree.test` a `127.0.0.1`.
3. **DB en estado seed limpio.** Si la sesión anterior dejó datos sueltos:
   ```bash
   php artisan migrate:fresh --seed
   ```
   Después del seed siempre hay: 1 super admin (`super@montree.test`), 1 tenant (`demo`), 4 usuarios demo, 5 tours, 3 categorías, 2 fechas por tour.
4. **Playwright MCP cargado.** En la sesión Claude, las tools `mcp__playwright__browser_*` deben aparecer en `ToolSearch`. Si no, reiniciar Claude Code y volver a ejecutar.

---

## 2. Credenciales y URLs

Todas las cuentas usan `password = password`.

| Rol | Email | Subdominio |
|---|---|---|
| Público | — | `demo.montree.test:8000` |
| Customer | `customer@demo.montree.test` | `demo.montree.test:8000` |
| Guide | `guide@demo.montree.test` | `demo.montree.test:8000` |
| Operator | `operator@demo.montree.test` | `demo.montree.test:8000` |
| Admin tenant | `admin@demo.montree.test` | `demo.montree.test:8000` |
| Super admin | `super@montree.test` | `admin.montree.test:8000` |

Páginas a recorrer por rol — ver §4.

---

## 3. Tips operativos (descubiertos en review 2026-05-19)

Leer antes de empezar para no perder tiempo:

- **Login vía MCP:** el `<Form>` de Inertia v3 a veces no submite con `browser_click` por motivos de timing. Workaround confiable:
  ```js
  await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
  const xsrf = document.cookie.split(';').find(c => c.includes('XSRF-TOKEN'));
  await fetch('/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': decodeURIComponent(xsrf.split('=')[1]) },
    credentials: 'same-origin',
    body: JSON.stringify({ email: '<rol>@demo.montree.test', password: 'password' }),
  });
  ```
  Fortify responde con `{ two_factor: false, redirect: '/admin/dashboard' }` (o el path por rol).

- **Logout:** `POST /logout` con XSRF (no GET — devuelve 405). `GET /logout` muestra la página de error de debug, no es bug, es Fortify.

- **`browser_fill_form` no siempre dispara v-model en shadcn:** Inputs estándar OK; Textarea de Radix/shadcn requiere `browser_type` con `slowly: true` para que Vue procese el `input` event. Si vas a verificar persistencia, hacer la verificación via `fetch` directo al endpoint API (no es bug de la app — es artefacto del setter de Playwright).

- **`router.post('/api/v1/*')` está prohibido** (regla en constitución §4.2). Si encontrás un callsite así, es bug — usar `useApi()` (`resources/js/composables/useApi.ts`).

- **Hard refresh entre roles:** el Vite HMR puede dejar el estado React de un usuario cuando cambiás. Si ves comportamiento raro, `browser_navigate` a `about:blank` y volver.

- **Verificación de DB:** preferir `mcp__laravel-boost__database-query` para SELECT. Para mutaciones de cleanup usar `php artisan tinker --execute 'Model::query()->delete()'`.

- **Limpieza de cookies entre roles:** logout via `fetch` + `fetch('/sanctum/csrf-cookie')` antes del próximo login. Si saltás esto, vas a quedar logueado como el rol anterior.

---

## 4. Smoke checklist por rol

Cada item: **acción → resultado esperado → cómo verificar**.

### 4.1 Público (sin auth) — F001/F002/F004/F005

| # | Paso | Esperado | Verificación |
|---|---|---|---|
| 1 | Navegar a `montree.test:8000` | Landing institucional con secciones features/cómo funciona/CTA | 0 errores en console |
| 2 | Navegar a `demo.montree.test:8000` | Home tenant branded ("Demo Eco Adventures") con 4 tours + sugerencias + footer condicional | tenant name en header + social links solo si `tenant.facebook_url` no es null |
| 3 | `?search=aventura` en hero | 1 resultado (tour de categoría Aventura) | Matchea por categoría (P2-1 fix) |
| 4 | Click filtro "Aventura" en sidebar | URL `?category=aventura`, 1 tour | `1 tour disponible` en el header |
| 5 | Click un tour | TourDetail renderiza, sin `tourShow` error, sección "Otras actividades" muestra cards reales | 0 errores console, no `_ctx.tourShow is not a function` |
| 6 | Click "Reservar" en una fecha sin auth | Redirige a `/login` | Conserva intent: tras login vuelve a `/booking/new?tour_date_id=X` |

### 4.2 Customer — F006/F008/F009/F010

Login con `customer@demo.montree.test`.

| # | Paso | Esperado | Verificación |
|---|---|---|---|
| 1 | Tras login, sidebar muestra brand del tenant | "Demo Eco Adventures", no "Laravel Starter Kit" | `AppSidebar.vue` usa `TenantBrandedLogo` |
| 2 | `/tours/tour-demo-2` → click corazón | `aria-pressed: false → true`, `aria-label: "Quitar de favoritos"`, fila nueva en `favorites` | `SELECT * FROM favorites WHERE user_id=X` |
| 3 | `/booking/new?tour_date_id=1` → llenar `#name-0`, `#email-0`, `#phone-0`, click "Crear reserva" | Redirige a `/bookings/{uuid}`, page title "Reserva al Tour Demo #1" | Booking row nuevo en `bookings`; title sin UUID raw (P2-5) |
| 4 | `/account/bookings` | Lista bookings con status humanizado ("Pendiente de pago", no `pending_payment`) | helper `formatBookingStatus` en `lib/format.ts` |
| 5 | `/account/favorites` | Card del tour favoriteado en paso 2 | — |
| 6 | `/account/notifications` | Empty state o lista; marcar leída debe hacer PATCH al API | DB column `read_at` en `notifications` cambia |
| 7 | `/account` | Hub con avatar + stats + form de perfil | — |

### 4.3 Guide — F014

Login con `guide@demo.montree.test`.

| # | Paso | Esperado | Verificación |
|---|---|---|---|
| 1 | Sidebar debe mostrar ítems relevantes a guide ("Mi agenda" como primer item) | No la sidebar genérica de customer | `AppSidebar.vue` filtra por `auth.user.roles` |
| 2 | `/guide/schedule` | Empty state o tour dates asignadas | Si seed no asigna `guide_id`, empty state es correcto |

### 4.4 Admin tenant — F003/F011/F012/F013/F014

Login con `admin@demo.montree.test`.

| # | Paso | Esperado | Verificación |
|---|---|---|---|
| 1 | `/admin/dashboard` | KPIs (ingresos, reservas, rating, ocupación) + tours top + próximas fechas | Sidebar branded tenant correctamente |
| 2 | `/admin/tours` | List con tabs (Todos/Borradores/Activos/Pausados/Archivados) + filtro categoría | "Mostrando 1–5 de 5 tours" |
| 3 | `/admin/tours/1/edit` | Form completo: general, precio, dificultad, includes, itinerario, gallery, status | — |
| 4 | `/admin/tours/create` → llenar campos → "Crear borrador" | Disparar POST a `/api/v1/admin/tours`. Si validation falla, errores inline | Si tour creado correctamente: redirect a `/admin/tours/{id}/edit` |
| 5 | `/admin/promotions` | Stats + list (vacío inicialmente) + botón "Nueva promoción" | — |
| 6 | `/admin/newsletter` | Subscribers count + form "Enviar campaña" | — |
| 7 | `/admin/team` | **Solo** admin/operator/guide (customer excluido — P2-9 fix) | `SELECT COUNT(*) FROM users WHERE ... AND role != customer` |
| 8 | `/admin/reviews` | Tabs Pendientes/Aprobadas/Rechazadas | — |
| 9 | `/admin/tenant/configuration` → cambiar tagline → "Guardar cambios" | Disparar PUT al API, toast success | `SELECT primary_color, tagline FROM tenant_configurations` antes/después |

### 4.5 Super admin — F015

Login con `super@montree.test` en `admin.montree.test:8000`.

| # | Paso | Esperado | Verificación |
|---|---|---|---|
| 1 | `/super-admin/dashboard` | KPIs agregados de toda la plataforma | "1 tenants activos, 5 usuarios" |
| 2 | `/super-admin/tenants` | Tabla con 1 tenant: Demo Eco Adventures, plan, status, contadores | Domain bien armado (sin `.montree.test.montree.app` — P2-8 fix) |
| 3 | `/super-admin/tenants/1` | Detail con: status, plan, KPIs, identidad visual editable, config operativa, social | — |
| 4 | Click "Suspender" → modal pide motivo → confirmar | PATCH `/api/v1/super-admin/tenants/1/status`, status DB → `suspended` | Restaurar inmediatamente con PATCH `status=active` |
| 5 | Cambiar plan (Professional → Basic) → "Aplicar" | PATCH `/api/v1/super-admin/tenants/1/plan`, plan DB cambia | Restaurar |

---

## 5. Cómo reportar hallazgos

Crear carpeta `docs/review-YYYY-MM-DD/` con:

- `findings.md` — issues organizados por prioridad (P0/P1/P2) con archivo + línea + fix sugerido + agente responsable. Ejemplo: `docs/review-2026-05-19/findings.md`.
- Opcional: `screenshots/` con `.png` relevantes (gitignored por `/tour-detail-*.png` pero podés ajustar).

**Convención de prioridades:**
- **P0** — bloqueante. UI rota / data corrupta / sin path de recuperación. Despachar fix antes de seguir.
- **P1** — UX visible (branding, copy, accesibilidad). Bloquea ofrecer a usuarios reales pero no funcionalidad.
- **P2** — cosmético / nice-to-have. Quedan para iteración siguiente.

**Convención de agentes:**
Cada issue indica qué sub-agente lo cierra:
- `montree-frontend-dev` — Vue/Inertia/Tailwind
- `montree-backend-dev` — Actions/Controllers/Requests/Resources
- `montree-db-architect` — migrations/models/factories
- `montree-spec-updater` — docs/specs/changelogs

---

## 6. Cleanup post-review

1. Si creaste datos de prueba (bookings, favoritos, tours nuevos), borrarlos o `php artisan migrate:fresh --seed`.
2. Verificar `.gitignore` no deja entrar artefactos Playwright (`.playwright-mcp/`, `tour-detail-*.png`, etc.).
3. Si se despacharon fixes, correr `php artisan test --compact` + `vendor/bin/pint --dirty --format agent` + `npm run build` antes de commitear.
4. Actualizar `project_montree_status.md` (memoria) si el inventario de deudas cambió.

---

## 7. Atajos: scripts útiles

```bash
# Pre-count de un modelo antes de un smoke test
php artisan tinker --execute 'echo App\Models\Booking::count();'

# Borrar todos los favoritos de un usuario
php artisan tinker --execute 'App\Models\Favorite::where("user_id", 1)->delete();'

# Ver últimos N bookings
php artisan tinker --execute 'App\Models\Booking::latest()->take(5)->get(["id","booking_number","status"])->each(fn($b) => print($b->booking_number . " " . $b->status->value . "\n"));'

# Estado del tenant
php artisan tinker --execute 'echo App\Models\Tenant::find(1)->status->value;'

# Restaurar tenant a active
php artisan tinker --execute 'App\Models\Tenant::find(1)->update(["status" => "active", "suspended_at" => null]);'
```

---

## Changelog

- `2026-05-19` — Creación inicial. Basada en review Playwright que detectó P0-2 sistémico (router.post a /api/v1/*) y otros 13 issues. Ver `docs/review-2026-05-19/findings.md`.
