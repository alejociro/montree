# F002 — Resolución de tenant y configuración

## Descripción

Mecanismo que identifica qué agencia está siendo accedida a partir del subdominio y carga su configuración visual y operativa. Cada agencia tiene su propia marca, colores, moneda y reglas sin compartir nada visualmente con otras.

## User stories

- Como visitante, quiero ver la marca y colores de la agencia cuando accedo a su subdominio.
- Como admin, quiero configurar los colores, moneda, idioma y reglas de mi agencia.
- Como admin, quiero ver un preview de los cambios antes de guardar.
- Como plataforma, necesito que cada subdominio cargue solo los datos de su tenant.

## Acceptance criteria

- **Given** una petición a `eco-adventures.montree.app`, **when** el sistema procesa la URL, **then** identifica el tenant `eco-adventures` y carga su configuración.
- **Given** un subdominio que no existe, **then** retorna `404` con página genérica de "agencia no encontrada".
- **Given** un tenant suspendido, **then** muestra página de "temporalmente no disponible" (`503`).
- **Given** un tenant en estado `pending` (agencia recién registrada en F016, con email del fundador aún sin verificar), **then** muestra la página `Errors/TenantPending` (`503`) en vez del catálogo o un `404`.
- **Given** un admin actualizando colores, **when** guarda, **then** las siguientes peticiones ya muestran los nuevos colores.
- **Given** un tenant en plan Basic intentando activar custom CSS, **then** recibe error indicando que requiere plan Enterprise.

## Edge cases

- Subdominio con caracteres inválidos: normalizar y rechazar si no matchea regex.
- Petición sin subdominio (`montree.app` directo): mostrar landing de plataforma, NO error de tenant.
- Configuración parcial (tenant nuevo): usar valores default de `tenant_configurations`.
- Tenant en estado `pending`: no debe exponer catálogo ni configuración; el middleware corta antes de setear el team_id y renderiza `Errors/TenantPending`.
- Cache de configuración: invalidar al actualizar, TTL 5 min para lecturas.

## Dependencias

- Ninguna (es base de todo lo demás).

## Endpoints involucrados

```
GET    /api/v1/tenant                          # devuelve config del tenant actual
PUT    /api/v1/admin/tenant/configuration      # edita configuración (admin)
```

## Componentes UI

- Pages: `TenantConfigPage` (admin), `TenantNotFoundPage`, `TenantSuspendedPage`, `Errors/TenantPending` (agregada por F016)
- Organisms: `BrandingEditor`, `OperationalSettingsForm`, `SocialLinksEditor`
- Molecules: `ColorPicker`, `PreviewPanel`, `CurrencySelector`, `TimezoneSelector`
- Atoms: `BaseInput`, `BaseSwitch`, `BaseSelect`, `ColorSwatch`

## Datos requeridos

Tablas: `tenants`, `tenant_configurations`

---

## Out of scope

- Custom domain (no subdominio): plan Enterprise futuro.
- Editor visual de templates de página.

## Decisiones tomadas

- **Custom CSS**: sanitizado con whitelist de propiedades CSS seguras (no inyección cruda). Disponible solo en plan Enterprise. Razón: XSS via `expression()`, `url(javascript:...)`, etc.
- **Cache**: cache default de Laravel, key `tenant:{slug}`, TTL 300s. Invalidado en `tenant.updated` y `tenant_configuration.updated` events. En dev queda en file driver; producción usará el driver del `.env`.
- **Página suspendido**: Inertia (no estático) con info de contacto del tenant + razón opcional, status code 503.
- **Hosts reservados**: `www`, `admin`, `api` (ya en `SubdomainTenantFinder`). Acceso a la raíz `montree.app/.test` muestra landing genérica sin tenant.
- **Shared props**: `tenant` y `tenantConfiguration` se inyectan en `HandleInertiaRequests::share()` para que toda página Vue tenga branding sin llamada extra. `GET /api/v1/tenant` queda para integraciones externas.

---

## Changelog

- `2026-05-17` — Creación inicial migrada del enunciado de proyecto.
- `2026-05-17` — Cerrado decisiones abiertas (custom CSS sanitization, cache strategy, suspended page, shared props).
- `2026-08-01` — Delta de F016 (onboarding): `ResolveTenant` ahora maneja el estado `TenantStatus::Pending` renderizando `Errors/TenantPending` (`503`). Razón: F016 crea tenants en estado `pending` antes de que el fundador verifique su email; sin esta rama el subdominio recién creado mostraría un catálogo vacío o un `404`.
