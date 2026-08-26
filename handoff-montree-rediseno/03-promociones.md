# 03 · Promociones

Referencia visual: `prototipos/montree-panel.html` (vista Promociones).

## Problemas del estado actual
- Tres contadores en cero sin contexto y un formulario inline que empuja la lista.
- No se ve vigencia, uso ni estado real de cada código.

## Qué debe entregar el rediseño
1. Cabecera + botón primario "Nueva promoción" (abre **modal**, no formulario inline).
2. KPIs: Códigos creados · Vigentes · Usos totales · Vencen este mes.
3. Tabla: | Código | Descuento | Vigencia | Uso | Estado | Acciones |
   - Código en IBM Plex Mono + tipo debajo (Porcentaje / Monto fijo).
   - Vigencia: "26 ago 2026 → 25 sep 2026" y debajo "Vence en 31 días" o "Vencida".
   - Uso: barra de progreso usos/máximo, o "N usos · sin límite".
   - Estado: Activa / Vencida / Inhabilitada.
   - Acciones ⋯: Editar promoción · Copiar código · Inhabilitar/Habilitar.
4. Modal: código*, tipo*, valor*, máximo de usos, desde*, hasta*, y nota aclarando
   que los códigos vencidos dejan de aplicarse solos.
5. Estado vacío con una sola acción cuando no hay promociones.
