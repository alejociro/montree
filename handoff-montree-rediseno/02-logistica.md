# 02 · Logística (rutas, proveedores, hoteles) + modales de creación

Referencia visual: `prototipos/montree-panel.html` (vista Logística y su modal).

## Problemas del estado actual
- Buscador diminuto arriba a la izquierda y el botón primario a su lado.
- Pestañas por encima del buscador: orden invertido respecto a cómo se usa.
- Cada ficha muestra solo nombre y una descripción genérica; no dice nada operativo.
- Tres iconos de acción por ficha (editar, duplicar, archivar) sin explicación.

## Qué debe entregar el rediseño
1. **Cabecera** con acción primaria contextual al tab activo: "Nueva ruta" /
   "Nuevo proveedor" / "Nuevo hotel".
2. **Barra de filtros** en este orden exacto:
   - Buscador ANCHO arriba: "Buscar ficha por nombre, municipio, contacto o tarifa"
     + contador de fichas.
   - Debajo, pestañas con conteo: Rutas · Proveedores · Hoteles.
   - A la derecha, select **Estado**: Todos / Habilitados / Inhabilitados.
3. **Fichas en rejilla** (`minmax(320px,1fr)`), cada una con:
   - Icono en caja de 38 px (pin para ruta, camión para proveedor, casa para hotel).
   - Nombre + etiqueta "Inhabilitado" si aplica, municipio debajo.
   - Descripción de una o dos líneas.
   - Lista de datos clave alineada a la derecha, según tipo:
     · Ruta: Distancia, Duración, Dificultad, Paradas.
     · Proveedor: Servicio, NIT, Tarifa, vencimiento de póliza/registro.
     · Hotel: Categoría, Capacidad, Tarifa, Check-in.
   - Pie: "Usada en N salidas" y **un solo botón: Editar**.
     **Quitar duplicar y archivar.**
4. Card punteada al final de la rejilla para crear una ficha nueva.

## Modal de creación / edición (aplica a los tres tipos)
- 880 px, navegación lateral por secciones que se marcan completas, pie con
  "X de Y campos obligatorios" + Cancelar / Guardar borrador / Guardar ficha.
- **Ubicación sin coordenadas**: campo de búsqueda de dirección con sugerencias;
  al elegir una se rellenan municipio y departamento y se guarda el punto.
  Si tu app ya tiene mapa (Leaflet + OpenStreetMap), añade pin arrastrable.
  Nunca pedir latitud/longitud.
- **Ningún campo se pierde.** Secciones y campos mínimos:
  ### Ruta
  Identificación (nombre*, descripción operativa, tipo de recorrido*, dificultad*),
  Ubicación (punto de inicio*, punto de finalización, municipio*, departamento, país),
  Perfil (distancia*, duración*, altitud máxima, desnivel positivo, capacidad del grupo*,
  temporada recomendada), Paradas (lista ordenable: nombre, hora, tipo
  Recogida/Parada/Regreso), Seguridad (riesgos y protocolo, equipo obligatorio,
  permisos o entradas, contacto de emergencia en zona, ficha habilitada).
  ### Proveedor
  Identificación (nombre*, tipo de servicio*: Transporte/Alimentación/Guianza/
  Actividades/Equipos/Otro, descripción), Datos legales (razón social*, NIT*,
  régimen tributario, correo de facturación, banco y cuenta, condiciones de pago*),
  Contacto (persona*, cargo, teléfono/WhatsApp*, correo, contacto alterno 24/7,
  horario, dirección*, municipio*, departamento, cobertura),
  Tarifas (lista: concepto, valor, unidad; moneda*, vigencia),
  Documentos (lista: tipo, número, vencimiento; notas internas; proveedor habilitado).
  ### Hotel
  Identificación (nombre*, tipo de alojamiento*, categoría, descripción, razón social, NIT*),
  Ubicación (dirección*, municipio*, departamento, país, cómo llegar),
  Habitaciones (lista: tipo, cantidad, tarifa por noche; capacidad total*, moneda*,
  vigencia, check-in*, check-out*),
  Servicios (incluye: desayuno/almuerzo/cena/wifi/agua caliente/parqueadero/piscina/
  fogata/lavandería/accesibilidad; alimentación, dietas, restricciones),
  Contacto y políticas (persona*, teléfono*, correo de reservas, contacto de emergencia,
  política de cancelación*, condiciones de pago*, notas internas, hotel habilitado).
- Al guardar: cerrar, refrescar la rejilla en el tab correspondiente y confirmar con toast.
