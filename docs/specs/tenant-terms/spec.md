# Términos y condiciones de la agencia

## Descripción

Cada agencia escribe sus propios términos desde el panel, y el viajero los lee
en el subdominio de esa agencia. Hoy el enlace del checkout apunta a `/terms`,
una ruta que **no existe**, así que da 404; y las únicas páginas legales del
proyecto son de Montree, con texto fijo y servidas solo en el dominio de la
plataforma.

Es **un solo documento**, no varios: para una agencia de tours, cancelaciones y
pagos son secciones dentro de sus términos, no documentos aparte.

La agencia es la responsable del servicio, así que el texto es suyo. Montree
aporta un texto por defecto para que una agencia nueva pueda vender desde el
primer día, y ella lo reemplaza cuando quiera.

## User stories

- Como agencia, quiero escribir mis términos desde el panel, con títulos y
  listas, porque un texto legal corrido no lo lee nadie.
- Como agencia nueva, quiero tener un texto razonable desde el día uno sin
  redactarlo.
- Como viajero, quiero abrir los términos desde el checkout y leer los de **esa**
  agencia, no los de la plataforma.

## Acceptance criteria

- **Given** el checkout, **when** se pulsa «términos y condiciones», **then** se
  abre la página de términos de esa agencia. Ya no hay 404.
- **Given** una agencia que no escribió sus términos, **then** la página muestra
  el texto por defecto, y el panel indica que sigue sin personalizar.
- **Given** una agencia con términos propios, **then** la página muestra su texto
  renderizado con sus títulos, negritas y listas.
- **Given** un admin con `tenant.settings.update`, **when** guarda sus términos,
  **then** quedan visibles en la página pública.
- **Given** un texto con `<script>`, `<img onerror=…>` o un enlace
  `javascript:`, **when** se renderiza, **then** nada de eso llega al navegador.
- **Given** el subdominio de otra agencia, **then** se ven **sus** términos, no
  los de la primera.

## Edge cases

- Términos vacíos o solo espacios equivalen a «sin personalizar»: se usa el
  texto por defecto.
- El texto por defecto existe por idioma; sin archivo para el idioma activo se
  cae al idioma por defecto del proyecto.
- El Markdown se convierte y sanea **en el servidor**. El navegador nunca recibe
  el texto crudo para renderizar.

## Dependencias

Ninguna nueva: `league/commonmark` ya viene con Laravel y `Str::markdown()`
soporta `html_input => strip` y `allow_unsafe_links => false`, verificado.

## Endpoints involucrados

```
GET  /terminos                                  (público, subdominio del tenant)
PUT  /api/v1/admin/tenant/configuration         (can:tenant.settings.update)  suma `terms_body`
```

## Componentes UI

- Pages: `Policies/Terms` (nueva, pública), `Admin/Tenant/Configuration` (suma la sección)
- Organisms: `TermsEditor` (área de texto + vista previa + ayuda de Markdown)

## Datos requeridos

`tenant_configurations.terms_body`: `longText` nullable. `null` o vacío significa
texto por defecto.

El texto por defecto vive en `resources/policies/terms.{locale}.md`, no en
`lang/en.json`: es un documento largo, y meterlo como clave de traducción
ensuciaría el catálogo y su test.

## Out of scope

- Políticas separadas de privacidad, pago o cancelación. Si más adelante hacen
  falta, se agregan entonces.
- Versionado o historial de cambios del texto.
- Aceptación registrada por reserva (guardar qué versión aceptó cada viajero).
- Las páginas de plataforma (`politica-de-pago`, `politica-de-cancelacion`) no se
  tocan: describen cómo Montree procesa los cobros y son suyas.

## Decisiones tomadas

- **Un solo documento.** Razón: decisión del usuario; cancelaciones y pagos son
  secciones, no documentos.
- **Texto por defecto, no bloqueo.** Razón: una agencia nueva tiene que poder
  vender el primer día, y el checkout no puede enlazar a una página vacía.
- **Markdown saneado en el servidor.** Razón: un texto legal necesita estructura,
  y con `html_input => strip` no hay superficie de inyección.
- **Columna en `tenant_configurations`, sin tabla nueva.** Razón: es un
  documento; una tabla de políticas sería sobreingeniería hoy.

## Changelog

- `2026-09-13` — Creación.
