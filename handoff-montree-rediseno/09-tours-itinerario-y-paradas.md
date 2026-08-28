# 09 · Tours: itinerario y paradas (constructor de ruta)

Referencia visual: `prototipos/tours-admin-montree.html` (Crear/Editar → Ruta y mapa).

## Problemas del estado actual
- Se pedían latitud y longitud al usuario.
- Las paradas vivían en una lista plana, desconectadas de los pasos del itinerario.
- No se podía reutilizar una misma ubicación en varios pasos.

## Reglas de negocio a respetar
- **Las paradas pertenecen a un paso del itinerario.** Cada paso (título, duración,
  descripción) contiene sus propias paradas, con un botón "Agregar parada aquí".
- Una parada también puede moverse a otro paso mediante un select "Paso del itinerario".
- **Varias paradas/pasos pueden compartir la misma ubicación.** Las ubicaciones son
  entidades reutilizables del tour ("lugares"): al elegir una ya guardada se comparte
  el mismo punto y el mapa muestra un solo pin con los números de las paradas que la usan
  ("Misma ubicación en los pasos 2 y 3").
- **Nunca se piden coordenadas.** El selector de ubicación tiene tres caminos:
  (1) buscar dirección con sugerencias, (2) reutilizar un lugar ya guardado del tour,
  (3) señalar/arrastrar el pin en el mapa (la dirección se deriva del punto).
- Ningún dato se pierde: tipo de parada (Recogida / Parada del recorrido / Regreso),
  nombre, hora, lugar de referencia visible, etiqueta en el mapa (solo habilitada para
  recogida y regreso), paso del itinerario, y el punto de encuentro del tour con sus
  indicaciones en texto libre.
- Reordenar y eliminar tanto pasos como paradas.
- En el mapa, los pines de recogida y regreso se separan a lados opuestos con su etiqueta
  para que no se solapen cuando están en la misma ciudad.

## Entregable
Rediseña el bloque "Itinerario y paradas" en Crear y Editar tour siguiendo el prototipo,
con mapa editable (Leaflet + OpenStreetMap) y mapa de solo lectura en Editar/Detalle.
