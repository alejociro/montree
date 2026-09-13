# Rediseño del panel Montree — paquete de prompts para Claude Code

Este paquete contiene los prompts listos para pegar en Claude Code (uno por módulo),
más los prototipos HTML que sirven de referencia visual.

## Contenido
- `00-sistema-de-diseno.md` — LÉELO PRIMERO. Tokens, componentes y patrones obligatorios.
- `01-salidas.md`
- `02-logistica.md` (incluye los modales de ruta / proveedor / hotel)
- `03-promociones.md`
- `04-newsletter.md`
- `05-resenas.md`
- `06-equipo.md`
- `07-roles-y-permisos.md`
- `08-configuracion.md`
- `09-tours-itinerario-y-paradas.md` (rediseño del constructor de paradas)
- `prototipos/montree-panel.html` — Salidas, Logística, Promociones, Newsletter, Reseñas, Equipo, Roles, Configuración.
- `prototipos/tours-admin-montree.html` — Tours: index, crear, editar, detalle, itinerario + paradas con mapa.
- `prototipos/tour-detalle-montree.html` — detalle público del tour con mapa.

## Cómo usarlo
1. Abre Claude Code en la raíz de tu proyecto.
2. Pega el contenido de `00-sistema-de-diseno.md` como primer mensaje de la sesión.
3. Luego pega el prompt del módulo que vas a rediseñar (uno por sesión o por rama).
4. Si Claude Code puede leer archivos locales, copia la carpeta `prototipos/` dentro del
   repo y menciónala: los prototipos son la referencia de layout y de comportamiento,
   no código para copiar y pegar (usa tus componentes reales).

## Orden recomendado
00 → 01 → 02 → 09 → 07 → 06 → 03 → 05 → 04 → 08

## Reglas que aplican a TODOS los prompts
- No inventar endpoints ni campos: si falta un dato, dejarlo como TODO y preguntar.
- No cambiar la paleta ni la tipografía (ver 00).
- Ningún módulo pierde funcionalidad existente: el rediseño reorganiza, no recorta datos.
- Accesibilidad: foco visible, `aria-selected` en pestañas, `aria-pressed` en toggles,
  menús cerrables con Escape, objetivos táctiles ≥ 40 px.
- Español de Colombia en toda la interfaz. Fechas legibles ("28 ago 2026 · 7:15 a. m."),
  nunca "28 De Agosto De 2026, 2:00 A. M.".
