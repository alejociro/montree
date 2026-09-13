# 06 · Equipo

Referencia visual: `prototipos/montree-panel.html` (vista Equipo).

## Problemas del estado actual
- "Invitar miembro" ocupa la mitad de la pantalla antes de la lista.
- Buscar/Estado/Rol como tres controles sueltos encima de la tabla.
- Dos botones por fila ("Roles" y "Suspender") repetidos en cada línea.

## Qué debe entregar el rediseño
1. KPIs: Miembros · Activos · Guías · Suspendidos (alert si > 0).
2. Card compacta "Invitar miembro": correo*, nombre, rol y botón Invitar en una sola fila.
3. Barra de filtros con el patrón obligatorio: buscador ancho arriba
   ("Buscar miembro por nombre, correo o rol"), pestañas Todos/Activos/Suspendidos
   con conteo, y select **Rol** a la derecha.
4. Tabla: | Miembro | Rol | Estado | Último acceso | Acciones |
   - Miembro: avatar con iniciales + nombre + correo.
   - Acciones ⋯: **Editar rol** (abre drawer) y **Suspender/Reactivar acceso**.
     La propia cuenta no puede suspenderse a sí misma.
5. Drawer "Editar rol": select de rol + resumen de permisos del rol elegido
   (agrupados por módulo, solo lectura) y botón Guardar rol.
