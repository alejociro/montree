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
- **Given** un admin actualizando colores, **when** guarda, **then** las siguientes peticiones ya muestran los nuevos colores.
- **Given** un tenant en plan Basic intentando activar custom CSS, **then** recibe error indicando que requiere plan Enterprise.

## Edge cases

- Subdominio con caracteres inválidos: normalizar y rechazar si no matchea regex.
- Petición sin subdominio (`montree.app` directo): mostrar landing de plataforma, NO error de tenant.
- Configuración parcial (tenant nuevo): usar valores default de `tenant_configurations`.
- Cache de configuración: invalidar al actualizar, TTL 5 min para lecturas.

## Dependencias

- Ninguna (es base de todo lo demás).

## Endpoints involucrados

```
GET    /api/v1/tenant                          # devuelve config del tenant actual
PUT    /api/v1/admin/tenant/configuration      # edita configuración (admin)
```

## Componentes UI

- Pages: `TenantConfigPage` (admin), `TenantNotFoundPage`, `TenantSuspendedPage`
- Organisms: `BrandingEditor`, `OperationalSettingsForm`, `SocialLinksEditor`
- Molecules: `ColorPicker`, `PreviewPanel`, `CurrencySelector`, `TimezoneSelector`
- Atoms: `BaseInput`, `BaseSwitch`, `BaseSelect`, `ColorSwatch`

## Datos requeridos

Tablas: `tenants`, `tenant_configurations`

---

## Out of scope

- Custom domain (no subdominio): plan Enterprise futuro.
- Editor visual de templates de página.

## Decisiones abiertas

- [ ] ¿Custom CSS se sanitiza o se inyecta crudo? (riesgo XSS).
- [ ] Cache: ¿por subdominio en Redis o en memory cache de la app?

---

## Changelog

- `2026-05-17` — Creación inicial migrada del enunciado de proyecto.
