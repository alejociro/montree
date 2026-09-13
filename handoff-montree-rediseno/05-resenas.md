# 05 · Reseñas

Referencia visual: `prototipos/montree-panel.html` (vista Reseñas).

## Problemas del estado actual
- Tres pestañas gigantes tipo segmento rojo y un recuadro punteado vacío.
- No se ve el promedio ni cuánto trabajo de moderación hay pendiente.

## Qué debe entregar el rediseño
1. KPIs: Por moderar (alert si > 0) · Publicadas · Promedio publicado · Rechazadas.
2. Pestañas con conteo (Pendientes / Aprobadas / Rechazadas) dentro de la card de filtros.
3. Cada reseña: estrellas en `--warn`, autor, texto (máx. 76 caracteres de ancho de línea),
   metadatos en mono (tour y fecha) y acciones a la derecha:
   Aprobar (primario) · Rechazar (destructivo) · Volver a revisión (para ya moderadas).
4. Al moderar: la reseña cambia de bandeja, se actualizan KPIs y se confirma con toast.
5. Estado vacío por bandeja.
