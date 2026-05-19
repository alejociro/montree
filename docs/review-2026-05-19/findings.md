# Playwright UX review — 2026-05-19

Branch: `feature/administration-process` · Server: `php artisan serve :8000` · Hosts: `montree.test`, `demo.montree.test`, `admin.montree.test`.

Roles probados: público, customer (`customer@demo`), guide (`guide@demo`), admin (`admin@demo`), super_admin (`super@montree`). F007 pagos saltado.

---

## P0 — bloqueantes (UI rota, backend OK)

### P0-1 · TourDetail.vue rompe en render — falta import `tourShow`
- **Archivo:** `resources/js/pages/TourDetail.vue:410`
- **Síntoma:** Hydration mismatch + `TypeError: _ctx.tourShow is not a function` en todos los `/tours/{slug}`. Sección "Otras actividades que te podrían gustar" queda con tarjetas grises placeholder.
- **Causa:** se usa `tourShow(related.slug)` sin importarlo. Existe en Wayfinder como `show` en `@/routes/tours`.
- **Fix:** agregar `import { show as tourShow } from '@/routes/tours';` en el `<script setup>`.
- **Feature:** F005 · **Agente:** montree-frontend-dev

### P0-2 · Sistémico — `router.post('/api/v1/...')` no dispara request
- **Síntoma:** todas las acciones mutadoras desde el frontend silenciosamente no hacen nada (no fetch, no XHR, no error). La reserva, favoritos, marcar notif, moderación admin, etc. se ven como "click muerto".
- **Verificación:** click `Crear reserva` → 0 network requests, DB sin cambios. `fetch('/api/v1/bookings', ...)` manual con mismas cookies → 201 Created. Confirmado: el backend está OK, la integración Inertia↔API está rota.
- **Causa:** Inertia v3 `router.post/put/patch/delete` requiere respuestas Inertia (redirect 302 + headers `X-Inertia`). Los endpoints `/api/v1/*` devuelven `Resource` JSON, así que Inertia los descarta. La memoria de proyecto ya mencionaba "api stateful" como fix para session, pero el response shape sigue siendo JSON — eso no es válido para Inertia router.
- **Archivos afectados (sweep `grep -rn "router\\.\\(post\\|put\\|patch\\|delete\\)"`):**
  - `resources/js/pages/Booking/Create.vue:75` — crear reserva
  - `resources/js/pages/Account/Notifications.vue:34,42` — marcar leída + marcar todas
  - `resources/js/pages/Account/Bookings/Review.vue:42` — submit reseña customer
  - `resources/js/components/AppHeader.vue:75` — marcar notif desde header
  - `resources/js/components/molecules/FavoriteButton.vue:19` — toggle favorito
  - `resources/js/components/organisms/TourImageUploader.vue:79,112,137` — subir/editar/eliminar imágenes
  - `resources/js/pages/Admin/Promotion/Index.vue:88,124,140` — CRUD promociones
  - `resources/js/pages/Admin/Team/Index.vue:63,119,140,150` — invitar / cambiar rol / suspender / reactivar
  - `resources/js/pages/Admin/Newsletter/Index.vue:44` — enviar campaña
  - `resources/js/pages/Admin/Reviews/Index.vue:69,84,115` — aprobar/rechazar/responder reseña
  - `resources/js/pages/Admin/Tour/Edit.vue:126,161` — guardar / eliminar tour
  - `resources/js/pages/Newsletter/Unsubscribe.vue:13` — confirmar baja
- **Fix sugerido:**
  - Opción A (recomendada): reemplazar `router.post(...)` por `useHttp()` (hook v3) o un helper `apiClient.post(...)` con `fetch` + cookies + XSRF + handlers de éxito/error.
  - Opción B: cambiar los endpoints a web routes que devuelvan `redirect()->back()->with(...)` y consumir vía Inertia — implica reescribir 13 endpoints, no escalable.
- **Feature:** transversal (F006/F008/F009/F010/F011/F012/F013/F014 admin) · **Agente:** montree-frontend-dev (con apoyo de montree-spec-updater para anotar la regla en `constitution.md`)
- **Por qué los tests pasan:** los feature tests llaman `postJson('/api/v1/...')` directo al backend, nunca pasan por la UI.

---

## P1 — UX rota / branding / accesibilidad

### P1-1 · Customer sidebar muestra "Laravel Starter Kit"
- **Archivos:** `resources/js/components/AppLogo.vue:13` hardcodea el texto; lo usan `AppHeader.vue` y `AppSidebar.vue` (layout customer).
- **Fix:** sustituir `AppLogo` por `atoms/TenantBrandedLogo` (ya existe y lo usa `PublicLayout`) o leer `useTenant().displayName` dentro de `AppLogo`.
- **Feature:** F009 · **Agente:** montree-frontend-dev

### P1-2 · Customer sidebar idéntica para todos los roles
- **Síntoma:** guía logueado ve "Inicio / Mis Reservas / Favoritos / Mi Cuenta" del sidebar de customer. No hay sidebar específica para guide ni un toggle por rol.
- **Esperado:** Guide debería ver "Mi agenda" como primer ítem (y opcionalmente sus viajeros). Operator debería ver Reservas/Tours.
- **Feature:** F014 (guide) · **Agente:** montree-frontend-dev

### P1-3 · Footer "Enlaces Rápidos" son listitems sin href
- **Archivo:** template del footer del `PublicLayout.vue`. "Sobre Nosotros" y "Términos y condiciones" se renderizan como `<li>` texto, no como `<a>`. Confunden al usuario porque parecen links.
- **Fix:** o convertirlos en links a páginas reales (`/about`, `/terms`) o eliminarlos hasta que esas páginas existan.
- **Feature:** F004 · **Agente:** montree-frontend-dev

### P1-4 · Footer Facebook link apunta a `#`
- **Archivo:** mismo footer. El tenant no tiene url de Facebook en config (`facebook_url=null`) pero el componente igual renderiza el link con `href="#"`. Instagram sí tiene URL real.
- **Fix:** `v-if="tenant.facebook_url"` para no renderizar links muertos.
- **Feature:** F004 · **Agente:** montree-frontend-dev

### P1-5 · Booking status muestra enum raw
- **Síntoma:** `/account/bookings` muestra "pending_payment" en español. Mejor: "Pendiente de pago" / "Confirmada" / "Cancelada".
- **Fix:** mapa de traducción en `Account/Bookings.vue` (o componente `BookingStatusBadge`).
- **Feature:** F009 · **Agente:** montree-frontend-dev

### P1-6 · `FavoriteButton` sin `aria-label`
- **Archivo:** `resources/js/components/molecules/FavoriteButton.vue`. Solo tiene `:aria-pressed`. Screen reader lee "button, not pressed" sin contexto.
- **Fix:** agregar `:aria-label="isFavorite ? 'Quitar de favoritos' : 'Agregar a favoritos'"`.
- **Feature:** F009 · **Agente:** montree-frontend-dev

### P1-7 · Booking form sin placeholders en datos de viajero
- **Síntoma:** primer viajero tiene fields "Nombre completo", "Email", "Teléfono" sin placeholder. Compare con "Código promocional (opcional)" que sí lo tiene.
- **Fix:** agregar placeholders ej. "Tu nombre completo", "tu@correo.com", "+57 300...".
- **Feature:** F006 · **Agente:** montree-frontend-dev

---

## P2 — pulir / cosmético

### P2-1 · Search no matchea contra categorías
- `/tours?search=aventura` → "0 tours" aunque categoría Aventura tiene 1 tour. El backend solo busca en name/description.
- **Fix sugerido:** unificar — o ampliar query al `category.name` o mostrar sugerencia "¿Querés filtrar por la categoría Aventura?".
- **Feature:** F004 · **Agente:** montree-backend-dev

### P2-2 · Rating "0.00 de 5 (0)" cuando no hay reseñas
- En cards del catálogo y home. Mejor: ocultar el bloque o mostrar "Sin reseñas todavía".
- **Feature:** F004/F005 · **Agente:** montree-frontend-dev

### P2-3 · Fechas inconsistentes en `DateCard`
- "Mar 02 de 12:30 P.M." en detalle vs "martes, 2 de junio de 2026" en booking detail. Mezcla locales y abreviaturas confusas.
- **Fix:** estandarizar `Intl.DateTimeFormat('es-CO', {weekday:'long', day:'numeric', month:'long', hour:'numeric', minute:'2-digit'})` en todos los lugares.
- **Feature:** F005/F006 · **Agente:** montree-frontend-dev

### P2-4 · `/account` página vacía/mínima
- Solo form de perfil (nombre/email/teléfono). Sin avatar, sin stats, sin links rápidos a próxima reserva / favoritos.
- **Feature:** F009 · **Agente:** montree-frontend-dev (alcance opcional)

### P2-5 · Booking detail title usa UUID raw
- `<title>Reserva 148ae7ac-9a81-...</title>`. UX feo en el tab del navegador.
- **Fix:** usar `booking_number` truncado o "Reserva al Tour Demo #1".
- **Feature:** F006 · **Agente:** montree-frontend-dev

### P2-6 · Promociones vacías mostradas siempre en home
- Sección "Promociones especiales" con texto "No hay promociones activas en este momento." Aparece siempre. Mejor: `v-if="promotions.length"`.
- **Feature:** F004/F012 · **Agente:** montree-frontend-dev

### P2-7 · GET /logout devuelve 405 visible
- `GET http://demo.montree.test:8000/logout` muestra MethodNotAllowedHttpException (debug page). Fortify solo registra POST.
- **Fix:** no es un bug real (es dev mode), pero conviene una página user-menu con botón "Cerrar sesión" que haga POST. **Verificar que existe ese botón en el menú DC del sidebar customer** — actualmente parece que no hay.
- **Feature:** F001/F009 · **Agente:** montree-frontend-dev

### P2-8 · Super admin tenant detail: dominio mal armado
- Tenant detail muestra `demo.montree.test.montree.app` — se concatena `.montree.app` a un domain que ya es completo (`demo.montree.test`).
- **Fix:** mostrar `tenant.domain` tal cual o usar slug + sufijo solo si `domain` es null.
- **Feature:** F015 · **Agente:** montree-frontend-dev

### P2-9 · Team list incluye al customer
- `/admin/team` muestra `Demo Customer` con dropdown de rol. Los clientes no son staff y no deberían aparecer en la administración de equipo.
- **Fix:** filtrar en `AdminTeamController::index` por roles `admin|operator|guide` (excluir `customer`).
- **Feature:** F014 · **Agente:** montree-backend-dev

### P2-10 · Memoria de estado desactualizada
- `project_montree_status.md` dice "F010 frontend pendiente — falta UI: customer ReviewForm + admin moderation page". Ambos archivos ya existen: `pages/Account/Bookings/Review.vue` y `pages/Admin/Reviews/Index.vue`. La review-submit UI existe pero está rota por P0-2.
- **Acción:** actualizar memoria proyecto post-fixes.
- **Agente:** (manual al finalizar)

---

## Resumen por feature

| Feature | Estado tras review | Issues |
|---|---|---|
| F001 Auth | ⚠ Login funciona vía POST JSON con Fortify, pero submit desde Inertia `<Form>` no siempre redirige (depende del Accept header). P2-7. |
| F002 Tenants | ✅ resolución demo OK, branding tenant en public layout OK |
| F003 Tour CRUD admin | ✅ list/edit/create renderizan; ❌ guardar/eliminar bloqueado por P0-2 |
| F004 Catálogo | ✅ list + filtros OK; P1-3, P1-4, P2-1, P2-2, P2-6 |
| F005 Tour Detail PDP | ❌ P0-1 rompe related tours; P2-2, P2-3 |
| F006 Booking flow | ❌ submit rota por P0-2 (DB no recibe); P1-7, P2-3, P2-5 |
| F007 Pagos | (saltado) |
| F008 Notificaciones | ❌ marcar leída rota por P0-2; lista renderiza OK |
| F009 Mi cuenta | ❌ favoritos toggle rota por P0-2; P1-1 sidebar; P1-5 status; P1-6 aria; P2-4 |
| F010 Reviews | ❌ submit rota por P0-2 (UI customer + admin moderation); memoria desactualizada (P2-10) |
| F011 Dashboard admin | ✅ renderiza con KPIs correctos en seed |
| F012 Promociones | ❌ CRUD rota por P0-2; list vacío renderiza OK |
| F013 Newsletter | ❌ enviar campaña rota por P0-2; lista vacía renderiza OK |
| F014 Equipo / Guías | ❌ invitar/rol/suspender rota por P0-2; P2-9 customer en lista; P1-2 guide sidebar |
| F015 Super admin | ✅ dashboard + list + detail renderizan; P2-8 dominio |

---

## Plan de despacho

1. **montree-frontend-dev — branch A (urgent P0):**
   - Fix P0-1 (un import en `TourDetail.vue`).
   - Crear `composables/useApi.ts` que envuelva `fetch` con CSRF + cookies + manejo de errores (replica firma de `router.post` para minimizar diff).
   - Reemplazar TODOS los `router.post/put/patch/delete` a `/api/v1/*` con `useApi.*`. (13 archivos listados en P0-2.)
   - Mantener `router.visit()` post-success para navegar a la página resultante.

2. **montree-frontend-dev — branch B (P1 UX):** después de P0.
   - P1-1 branding customer sidebar.
   - P1-2 sidebar dinámica por rol.
   - P1-3, P1-4, P1-5, P1-6, P1-7.

3. **montree-backend-dev:** P2-1 (search categorías), P2-9 (filtrar team).

4. **montree-spec-updater:** anotar regla en `docs/constitution.md`: "Frontend NUNCA usa `router.post/put/patch/delete` contra rutas `/api/v1/*` — usar `useApi` composable. `router.*` se reserva para web routes Inertia."

5. **Memoria del proyecto:** actualizar `project_montree_status.md` con esta review.

6. **Tests E2E:** este review revela que faltan tests browser. Considerar Playwright o Dusk como deuda P2.
