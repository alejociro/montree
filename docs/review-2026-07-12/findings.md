# Review end-to-end — 2026-07-12

Auditoría funcional con Playwright MCP sobre seed limpio (`migrate:fresh --seed`), servida por Herd (`montree.test` / `demo.montree.test`, assets `npm run build`). Cobertura: público (landing, catálogo + filtros, detalle de tour, guest checkout), customer, guide, admin tenant, super admin.

## Qué funciona (verificado)

- **Filtros del catálogo — todos correctos contra la BD**: categoría (`?category=aventura` → 2 tours correctos), dificultad (`extreme` → los 2 extreme), rango de precio (`300–600` → tours de $444 y $513), orden `price_asc`/`price_desc` (secuencias exactas), búsqueda por nombre de categoría (`aventura` → los 2 de esa categoría), filtros combinados (aventura + extreme + min 1000 → solo Tour #3), empty state con "Limpiar filtros" cuando no hay resultados. Contadores por categoría en el sidebar correctos.
- **Detalle de tour**: galería, includes, requisitos, itinerario, disponibilidad con cupos y precio, punto de encuentro con link a Google Maps, reseñas (empty state), sugerencias con cards reales. 0 errores de consola. El botón "Reservar ahora" se habilita al elegir fecha y se convierte en link a `/booking/new?tour_date_id=X`.
- **Guest checkout dos fases (F006, verificado end-to-end)**: reserva de 2 adultos + 1 menor creada como invitado; cuenta auto-creada; sección "Viajeros" en el detalle con slots por adulto/menor y badge "Requerido antes del tour" (respeta `require_traveler_details`); datos guardados vía `PUT /bookings/{uuid}/travelers` y verificados en BD (`is_minor` correcto).
- **Customer**: favoritos (toggle `aria-label` + página Mis favoritos), Mis reservas, notificaciones (empty state), perfil.
- **Guide**: login redirige a `/guide/schedule`, "Mi agenda" con empty state correcto.
- **Admin tenant**: dashboard KPIs, tours con tabs y filtro de categoría, editor de tour completo, promociones, newsletter, equipo (customer correctamente excluido), reseñas, configuración del tenant.
- **Super admin**: dashboard de plataforma, tabla de tenants (dominio bien armado), detalle con suspender/plan/agregar usuario.

## Hallazgos

### P0

| # | Hallazgo | Ubicación | Estado |
|---|---|---|---|
| P0-1 | **No existe gestión de fechas/salidas (`tour_dates`)** ni en la UI admin ni en la API (solo asignar guía a una fecha existente; las fechas solo salen del seeder). Como `ChangeTourStatusAction` exige ≥1 fecha futura open para activar, **un admin no puede activar un tour nuevo desde la UI**. | Editor `/admin/tours/{id}/edit` (secciones: general/precio/detalle/punto/itinerario/galería/estado — sin fechas); `routes/api.php` admin | **Abierto — se resuelve con F017 (módulo Producto/Salidas)**, no parchear por separado |

### P1

| # | Hallazgo | Ubicación | Estado |
|---|---|---|---|
| P1-1 | Usuarios del seeder sin `password_set_at` → todos (super, admin, operator, guide, customer) veían la tarjeta "Define tu contraseña" pensada para cuentas guest | `database/seeders/DemoTenantSeeder.php` (updateOrCreate sin la columna) | **Corregido** en este review |

### P2

| # | Hallazgo | Ubicación | Estado |
|---|---|---|---|
| P2-1 | Botón de reset de filtros decía "Personalizar filtros" en vez de "Limpiar filtros" | `resources/js/components/organisms/FilterSidebar.vue:168` | **Corregido** |
| P2-2 | Badge de dificultad en detalle de tour mostraba el valor crudo `extreme` (el mapa usaba `expert`, clave inexistente del enum) | `resources/js/pages/TourDetail.vue:65`, `resources/js/types/tour-detail.ts:38` | **Corregido** |
| P2-3 | Prop `requireTravelers` muerto en `BookingPagesController::create` (el checkout ya no lo usa tras F006 dos fases) | `app/Http/Controllers/BookingPagesController.php` | **Corregido** (eliminado) |
| P2-4 | Card de favoritos muestra "0.00 ★" cuando el tour no tiene reseñas — mejor ocultar el rating o mostrar "Sin reseñas" | `resources/js/pages/Account/Favorites.vue` (aprox.) | Abierto — cosmético, `montree-frontend-dev` |
| P2-5 | Playbook desactualizado: dominio super admin, redirect a login pre-guest-checkout, campos del checkout viejo | `docs/review-playbook.md` | **Corregido** |

## Notas

- El flujo "Reservar sin auth → redirect `/login`" del playbook original ya no aplica: guest checkout va directo al formulario (comportamiento correcto desde F006 guest checkout).
- Las rutas `/super-admin/*` viven en el dominio central `montree.test`; `admin.montree.test` devuelve 404 para esas rutas (comportamiento actual, no bug — el playbook estaba desactualizado).
- Sin errores de consola en ninguna página recorrida (solo favicon 404 en `montree.test`, preexistente y cosmético).
