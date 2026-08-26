# 07 · Roles y permisos

Referencia visual: `prototipos/montree-panel.html` (vista Roles y permisos).

## Problemas del estado actual
- Cuatro cards que solo dicen "39 permisos · 1 miembro" y un botón "Ver permisos"
  que no muestra de qué se trata hasta abrirlo.
- El bloque de roles propios es un recuadro punteado enorme y vacío.
- No se entiende la diferencia entre rol del sistema y rol propio más allá del texto.

## Qué debe entregar el rediseño
1. KPIs: Roles en uso · Permisos disponibles · Miembros con acceso · Roles propios.
2. Sección "Roles del sistema": cards con nombre + etiqueta "Solo lectura",
   metadatos ("X de Y permisos", "N miembros"), **barra de alcance** que visualiza
   qué proporción del total cubre el rol, descripción de una línea y acción
   "Ver permisos".
3. Sección "Roles propios de la agencia": mismas cards con etiqueta "Propio" y acciones
   "Ver permisos" + "Editar". Estado vacío compacto con una sola acción "Crear rol".
4. **Drawer de permisos**: agrupados por módulo (Tours, Salidas, Reservas, Logística,
   Contenido, Equipo, Configuración) con casilla marcada/desmarcada por permiso y
   contador por grupo. En roles del sistema es solo lectura, con nota explicativa y
   acción "Duplicar como rol propio". En roles propios, botón "Editar rol".
5. **Modal Crear/Editar rol**: nombre*, descripción, matriz de permisos marcable con
   contador global y atajos "Marcar todo" / "Quitar todo". Al guardar, aparece en la
   sección de roles propios.
6. Los permisos deben leerse en lenguaje de negocio ("Asignar guía", "Registrar pagos"),
   no como claves técnicas.
