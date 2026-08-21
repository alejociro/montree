# Aviso a QA — antes de subir este feature a su entorno

> Fecha: 2026-08-21 · Rama: `feature/tours-admin-passenger-manifest` · Feature:
> [`tours-admin-passengers`](./spec.md)

Las tres migraciones de este feature **reescriben datos existentes**. No son aditivas: si QA tiene
un entorno con salidas y reservas cargadas, lo que están mirando hoy va a cambiar solo. Conviene
avisar antes de correrlas y, si el entorno sirve de evidencia de alguna prueba en curso, sacar copia.

## Qué hace cada migración

| Migración | Qué reescribe |
|---|---|
| `2026_08_20_130000_add_health_and_emergency_fields_to_booking_travelers_table` | Agrega `eps`, `eps_other` y `emergency_contact_relationship` a `booking_travelers`, y **traslada** el contacto de emergencia que hoy vive en `bookings.contact_snapshot` al primer viajero de cada reserva que lo tenga. Es idempotente: correrla dos veces no duplica nada |
| `2026_08_20_130100_add_default_guide_id_to_tours_table` | Solo agrega la columna `tours.default_guide_id`. No toca datos |
| `2026_08_20_130200_require_guide_and_derive_ends_at_on_tour_dates_table` | **Recalcula `ends_at` de todas las salidas** a partir de `tours.duration_hours`, **asigna guía** a las que no tengan y pasa `tour_dates.guide_id` a `NOT NULL` |

## Lo que van a notar

- **Las horas de fin cambian.** `ends_at` deja de ser un dato que alguien escribió y pasa a derivarse
  de la duración del tour. Una salida que hoy dice que termina a las 6 puede pasar a decir otra cosa;
  el campo «Fin» ya no es editable en la pantalla, se muestra calculado.
- **Toda salida queda con guía.** El estado «Falta guía» ya no existe: se eliminó de la UI. Las salidas
  sin guía reciben uno en la migración.
- **La migración aborta si un tenant no tiene ningún usuario con rol `guide`**, y lista cuáles son. No
  deja la base a medias: hay que crear el guía y volver a correrla.
- **Reporta los solapes que encuentre** (un guía con dos salidas el mismo día). No los corrige: los
  informa, porque decidir cuál se mueve es de operación, no de la migración. De ahí en adelante la
  regla nueva impide crearlos.
- **`guide_id` pasó a `restrictOnDelete`** (antes `nullOnDelete`): borrar a un usuario que es guía de
  una salida ya no lo desasigna, lo impide. Cambia el comportamiento del panel de equipo.

## Qué probar después

- Panel de la agencia con **admin** y con **ventas**: ventas no debe ver EPS ni observaciones médicas
  en ninguna superficie (columna, filtro «Con observaciones», conteo del pie ni CSV). Es la Decisión 7.
- Zona del **guía** (`/guide/schedule`, `/guide/tour-dates/{id}/passengers`): ve la planilla completa,
  incluidos los datos de salud, y no puede editar nada ni entrar a `/admin/*`.
- Crear y editar salidas: elegir un guía ya ocupado esos días debe salir deshabilitado con el motivo.
- Cambiar la parada de recogida de un tour con reservas activas **envía correo** a los pasajeros. En un
  entorno con datos reales de contacto, ojo con eso.
