# 00 · Sistema de diseño (pegar al inicio de la sesión)

Vas a rediseñar módulos del panel de administración de Montree (SaaS para agencias de
viaje). Antes de tocar código, asume estas reglas como fuente de verdad de estilo.

## Paleta (no cambiarla)
```
--ink:      #14301F   /* verde muy oscuro: sidebar, botones de estado sólido, texto fuerte */
--green:    #12A150   /* primario: acciones, barras de ocupación, foco */
--green-d:  #0E7C3E   /* hover del primario, enlaces */
--green-100:#E3F0E7   /* fondo de etiquetas y estados activos suaves */
--green-50: #EFF6F0   /* hover de filas/botones fantasma */
--cream:    #F6F0E6   /* fondo de la aplicación */
--card:     #FFFDF9   /* fondo de cards, tablas, inputs */
--line:     #E4DDCD   /* bordes */
--line-2:   #EFE9DC   /* separadores internos */
--text:     #1B1D18
--muted:    #767B70
--danger:   #B4562A   /* destructivo / alertas */
--danger-50:#F7E6DC
--warn:     #B98A17
--radius:   14px  (cards) · 10px (inputs) · 999px (botones y etiquetas)
```
Nada de rojo saturado, ni gradientes, ni sombras duras. Sombra permitida solo en
menús y modales: `0 24px 50px -26px rgba(20,48,31,.55)`.

## Tipografía
- UI: **Plus Jakarta Sans** (400/500/600/700). Base 14 px, línea 1.5.
- Títulos decorativos y números de héroe: **Instrument Serif** (solo donde ya se use).
- Etiquetas/códigos: **IBM Plex Mono** 10.5 px, `letter-spacing:.09em`, mayúsculas.
- Títulos de vista 30 px / 600; títulos de sección 19 px; subtítulos en `--muted`.

## Componentes y patrones obligatorios
1. **Cabecera de vista**: h1 + una línea de contexto (máx. 64 caracteres de ancho) y
   acciones primarias a la derecha. Sin acciones falsas: si el backend no tiene la
   función (p. ej. exportar CSV), el botón no existe.
2. **KPIs**: fila de 4 cards (`--card` + borde `--line`), etiqueta en mono, número
   26 px/600, pie en `--muted`. Variante `alert` con fondo `--danger-50` cuando el KPI
   representa trabajo pendiente.
3. **Barra de filtros (patrón fijo)**: una sola card que contiene, en este orden:
   (a) buscador ancho de 44 px de alto con icono a la izquierda y contador de resultados
   a la derecha; (b) debajo, separador y una fila con **pestañas con conteo a la
   izquierda** y **selects a la derecha** (pill, 999 px). Nunca una rejilla de 5 selects.
4. **Tablas**: envueltas en card con `overflow:auto`, thead pegajoso, encabezados en
   mayúsculas 11 px `--muted`, filas con hover `#FBF7EE`, separador `--line-2`.
   Fila inhabilitada: `opacity:.62`. Última columna "Acciones" alineada a la derecha.
5. **Acciones de fila**: SIEMPRE un botón de tres puntos que abre un menú desplegable
   con iconos (16 px, trazo 1.7, `currentColor`). Nunca varios iconos sueltos.
   Ítem destructivo en `--danger`.
6. **Etiquetas de estado**: pill 11.5 px/600. `t-ok` (verde 100), `t-warn` (ámbar),
   `t-off` (gris), `t-due`/`t-danger` (danger-50), `t-live` (ink).
7. **Ocupación / progreso**: barra de 6 px con radio completo + línea superior
   "sold/cap" y "N libres". Barra en `--ink` cuando está lleno, `--line` cuando es 0.
8. **Modales de creación/edición largos**: 880 px, alto máx. 88vh, columna izquierda con
   navegación por secciones (marca las completas), formulario con scroll a la derecha,
   pie con contador "X de Y campos obligatorios" + Cancelar / Guardar borrador / Guardar.
9. **Panel lateral (drawer)** 470 px para detalle y asignaciones puntuales.
10. **Toast** pill oscuro abajo al centro para confirmar acciones.
11. **Estado vacío**: borde punteado, título 16 px, explicación y una sola acción.

## Antipatrones prohibidos
- Emojis en la interfaz.
- Columnas que acumulan chips heterogéneos ("Condiciones" mezclando ruta, proveedor y hotel).
- Botones cuya función el usuario no puede nombrar (duplicar, archivar) sin pedirlos.
- Buscadores angostos (< 280 px) en vistas de listado.
- Pedir latitud/longitud al usuario. Nunca.
