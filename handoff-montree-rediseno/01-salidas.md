# 01 · Salidas

Rediseña la vista de listado de Salidas siguiendo 00-sistema-de-diseno.md.
Referencia visual: `prototipos/montree-panel.html` (vista Salidas).

## Problemas del estado actual
- Cinco selects en una caja gris ocupan el espacio principal y no jerarquizan nada.
- Fechas ilegibles: "7 De Septiembre De 2026, 2:00 A. M.".
- La tabla muestra demasiadas columnas; "Condiciones" acumula chips de ruta, proveedor
  y hotel sin poder actuar sobre ellos.
- Las acciones son dos iconos sueltos (lápiz y prohibido) sin etiqueta.
- No hay botón de exportar CSV (no existe la función) ni se programan salidas desde aquí.

## Qué debe entregar el rediseño
1. **Cabecera**: título "Salidas" + línea de contexto. **Sin** botón de exportar CSV y
   **sin** "Programar salida".
2. **KPIs (4)**: Salidas activas · Cupos por vender · Viajeros confirmados ·
   Sin guía asignado (variante alert cuando > 0).
3. **Barra de filtros** con el patrón obligatorio:
   - Buscador ancho: "Buscar salida por tour, código o guía" + contador "N de M".
   - Pestañas con conteo: Próximas · Hoy · Realizadas · Inhabilitadas · Todas.
   - Selects a la derecha: Tour y Orden (Más próxima / Más lejana).
4. **Tabla con exactamente estas columnas**:
   | Tour | Fecha | Precio | Guía | Ocupación | Acciones |
   - Tour: nombre en 14.5/600 + subtítulo mono con código y duración
     (`TD2-0828 · 3 días`, y "· inhabilitada" cuando aplique).
   - Fecha: bloque día/mes (46 px, borde, fondo #FBF7EE) + "mié · 7:15 a. m." y
     abajo la referencia relativa ("hoy", "en 3 días", "hace 2 días").
   - Precio: valor 15 px/700 y "por persona" en `--muted`.
   - Guía: avatar con iniciales + nombre + "asignado"; si no hay, etiqueta `t-due`
     "Sin guía".
   - Ocupación: barra + "14/19" y "5 libres".
   - Acciones: botón ⋯ → menú con **Editar salida** (icono lápiz) y
     **Inhabilitar** (icono prohibido, en danger) o **Habilitar** (icono check) según estado.
   - **Eliminar por completo la columna Condiciones/Logística.**
5. **Pie de tabla** con totales del filtro actual (salidas, viajeros, cupos libres).
6. Estado vacío cuando ningún registro coincide.

## Comportamiento
- Inhabilitar/Habilitar actualiza la fila y los KPIs sin recargar; confirma con toast.
- El menú se cierra con Escape, con clic fuera y al hacer scroll.
- El buscador filtra por tour, código y guía; no pierde el foco al escribir.
