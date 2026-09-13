import { translate } from '@/composables/useTranslations';
import {
    accommodationTypeOptions,
    cancellationPolicyOptions,
    currencyOptions,
    difficultyOptions,
    documentTypeOptions,
    mealPlanOptions,
    paymentTermsOptions,
    rateUnitOptions,
    routeKindOptions,
    seasonOptions,
    serviceTypeOptions,
    starRatingOptions,
    stopKindOptions,
    taxRegimeOptions,
    amenityOptions,
} from '@/lib/logistics';
import type { SelectOption } from '@/lib/logistics';
import type {
    LogisticsFieldValue,
    LogisticsFormState,
    LogisticsRecord,
    LogisticsResourceKind,
} from '@/types/logistics';

/**
 * El formulario de ficha, descrito como datos.
 *
 * WHY: son tres fichas de cinco secciones y más de veinte campos cada una. Con
 * plantillas escritas a mano serían mil líneas de Vue repetidas tres veces, y
 * el pie que cuenta «X de Y obligatorios» tendría que saberse los campos de
 * memoria. Aquí la sección es un objeto: el diálogo la pinta, la cuenta y la
 * marca completa sin conocer ni un nombre de campo.
 */

export type LogisticsFieldType =
    | 'text'
    | 'textarea'
    | 'number'
    | 'email'
    | 'date'
    | 'select'
    | 'options'
    | 'tags'
    | 'address'
    | 'repeat';

export type RepeatColumn = {
    key: string;
    label: string;
    type: 'text' | 'number' | 'select' | 'date';
    options?: SelectOption[];
    placeholder?: string;
    /** Fracción de la fila; se vierte tal cual en `grid-template-columns`. */
    width: string;
};

export type LogisticsFieldDef = {
    key: string;
    label: string;
    type: LogisticsFieldType;
    required?: boolean;
    placeholder?: string;
    hint?: string;
    rows?: number;
    width?: 'full' | 'half' | 'third';
    options?: SelectOption[];
    multiple?: boolean;
    columns?: RepeatColumn[];
    addLabel?: string;
    /** Qué campos rellena la dirección elegida en el buscador. */
    fills?: {
        latitude?: string;
        longitude?: string;
        city?: string;
        state?: string;
    };
    /** Sufijo dentro del campo: km, h, m, personas. */
    unit?: string;
};

export type LogisticsSectionDef = {
    id: string;
    nav: string;
    title: string;
    lead: string;
    fields: LogisticsFieldDef[];
};

function routeSections(): LogisticsSectionDef[] {
    return [
        {
            id: 'identification',
            nav: translate('Identificación'),
            title: translate('Identificación'),
            lead: translate('Cómo se llama y qué tipo de recorrido es.'),
            fields: [
                {
                    key: 'name',
                    label: translate('Nombre de la ruta'),
                    type: 'text',
                    required: true,
                    width: 'full',
                    placeholder: translate('Ruta Cascadas'),
                    hint: translate('Así la verás al armar una salida.'),
                },
                {
                    key: 'description',
                    label: translate('Descripción operativa'),
                    type: 'textarea',
                    width: 'full',
                    rows: 3,
                    placeholder: translate(
                        'Qué recorre, cómo es el terreno y qué debe saber el guía',
                    ),
                },
                {
                    key: 'kind',
                    label: translate('Tipo de recorrido'),
                    type: 'select',
                    required: true,
                    width: 'half',
                    options: routeKindOptions(),
                },
                {
                    key: 'difficulty',
                    label: translate('Dificultad'),
                    type: 'select',
                    required: true,
                    width: 'half',
                    options: difficultyOptions(),
                },
            ],
        },
        {
            id: 'location',
            nav: translate('Ubicación'),
            title: translate('Ubicación'),
            lead: translate('Punto de inicio y de cierre del recorrido.'),
            fields: [
                {
                    key: 'start_point',
                    label: translate('Punto de inicio'),
                    type: 'address',
                    required: true,
                    width: 'full',
                    fills: {
                        latitude: 'start_latitude',
                        longitude: 'start_longitude',
                        city: 'city',
                        state: 'state',
                    },
                },
                {
                    key: 'end_point',
                    label: translate('Punto de finalización'),
                    type: 'address',
                    width: 'full',
                    fills: {
                        latitude: 'end_latitude',
                        longitude: 'end_longitude',
                    },
                },
                {
                    key: 'city',
                    label: translate('Municipio'),
                    type: 'text',
                    required: true,
                    width: 'third',
                    placeholder: 'Salento',
                },
                {
                    key: 'state',
                    label: translate('Departamento'),
                    type: 'text',
                    width: 'third',
                    placeholder: 'Quindío',
                },
                {
                    key: 'country',
                    label: translate('País'),
                    type: 'text',
                    width: 'third',
                    placeholder: 'Colombia',
                },
            ],
        },
        {
            id: 'profile',
            nav: translate('Perfil'),
            title: translate('Perfil del recorrido'),
            lead: translate(
                'Datos que definen el esfuerzo y el tamaño del grupo.',
            ),
            fields: [
                {
                    key: 'distance_km',
                    label: translate('Distancia'),
                    type: 'number',
                    required: true,
                    width: 'third',
                    unit: 'km',
                },
                {
                    key: 'duration_hours',
                    label: translate('Duración estimada'),
                    type: 'number',
                    required: true,
                    width: 'third',
                    unit: 'h',
                },
                {
                    key: 'max_altitude_m',
                    label: translate('Altitud máxima'),
                    type: 'number',
                    width: 'third',
                    unit: 'm',
                },
                {
                    key: 'elevation_gain_m',
                    label: translate('Desnivel positivo'),
                    type: 'number',
                    width: 'half',
                    unit: 'm',
                },
                {
                    key: 'group_capacity',
                    label: translate('Capacidad máxima del grupo'),
                    type: 'number',
                    required: true,
                    width: 'half',
                    unit: translate('personas'),
                },
                {
                    key: 'seasons',
                    label: translate('Temporada recomendada'),
                    type: 'options',
                    multiple: true,
                    width: 'full',
                    options: seasonOptions(),
                },
            ],
        },
        {
            id: 'stops',
            nav: translate('Paradas'),
            title: translate('Paradas'),
            lead: translate(
                'El orden define el recorrido; cada parada guarda su hora prevista.',
            ),
            fields: [
                {
                    key: 'stops',
                    label: translate('Paradas de la ruta'),
                    type: 'repeat',
                    width: 'full',
                    addLabel: translate('Agregar parada'),
                    columns: [
                        {
                            key: 'name',
                            label: translate('Nombre de la parada'),
                            type: 'text',
                            placeholder: translate('Plaza de Bolívar'),
                            width: 'minmax(0,1.6fr)',
                        },
                        {
                            key: 'time_label',
                            label: translate('Hora'),
                            type: 'text',
                            placeholder: '8:00 a. m.',
                            width: 'minmax(0,0.7fr)',
                        },
                        {
                            key: 'kind',
                            label: translate('Tipo'),
                            type: 'select',
                            options: stopKindOptions(),
                            width: 'minmax(0,0.9fr)',
                        },
                    ],
                },
            ],
        },
        {
            id: 'safety',
            nav: translate('Seguridad'),
            title: translate('Seguridad y notas'),
            lead: translate(
                'Lo que el guía necesita si algo se sale del plan.',
            ),
            fields: [
                {
                    key: 'safety_notes',
                    label: translate('Riesgos y protocolo'),
                    type: 'textarea',
                    width: 'full',
                    rows: 3,
                    placeholder: translate(
                        'Tramos expuestos, señal de celular, punto de evacuación',
                    ),
                },
                {
                    key: 'required_gear',
                    label: translate('Equipo obligatorio'),
                    type: 'tags',
                    width: 'full',
                    placeholder: translate('Calzado de trekking'),
                },
                {
                    key: 'permits',
                    label: translate('Permisos o entradas'),
                    type: 'text',
                    width: 'half',
                    placeholder: translate('Entrada al parque'),
                },
                {
                    key: 'emergency_contact',
                    label: translate('Contacto de emergencia en zona'),
                    type: 'text',
                    width: 'half',
                    placeholder: translate('Bomberos Salento · +57 …'),
                },
            ],
        },
    ];
}

function providerSections(): LogisticsSectionDef[] {
    return [
        {
            id: 'identification',
            nav: translate('Identificación'),
            title: translate('Identificación'),
            lead: translate('Quién es y qué presta exactamente.'),
            fields: [
                {
                    key: 'name',
                    label: translate('Nombre del proveedor'),
                    type: 'text',
                    required: true,
                    width: 'full',
                    placeholder: translate('Transportes Andinos'),
                },
                {
                    key: 'service_type',
                    label: translate('Tipo de servicio'),
                    type: 'options',
                    required: true,
                    width: 'full',
                    options: serviceTypeOptions(),
                },
                {
                    key: 'description',
                    label: translate('Descripción del servicio'),
                    type: 'textarea',
                    width: 'full',
                    rows: 3,
                    placeholder: translate(
                        'Capacidad, restricciones y detalles del servicio',
                    ),
                },
            ],
        },
        {
            id: 'legal',
            nav: translate('Datos legales'),
            title: translate('Datos legales y facturación'),
            lead: translate('Lo que necesita contabilidad para pagarle.'),
            fields: [
                {
                    key: 'legal_name',
                    label: translate('Razón social'),
                    type: 'text',
                    required: true,
                    width: 'half',
                    placeholder: 'Transportes Andinos S.A.S.',
                },
                {
                    key: 'tax_id',
                    label: translate('NIT o documento'),
                    type: 'text',
                    required: true,
                    width: 'half',
                    placeholder: '901.223.114-3',
                },
                {
                    key: 'tax_regime',
                    label: translate('Régimen tributario'),
                    type: 'select',
                    width: 'half',
                    options: taxRegimeOptions(),
                },
                {
                    key: 'billing_email',
                    label: translate('Correo de facturación'),
                    type: 'email',
                    width: 'half',
                    placeholder: 'facturacion@proveedor.com',
                },
                {
                    key: 'bank_account',
                    label: translate('Banco y cuenta'),
                    type: 'text',
                    width: 'half',
                    placeholder: 'Bancolombia · Ahorros 123-456789-00',
                },
                {
                    key: 'payment_terms',
                    label: translate('Condiciones de pago'),
                    type: 'select',
                    required: true,
                    width: 'half',
                    options: paymentTermsOptions(),
                },
            ],
        },
        {
            id: 'contact',
            nav: translate('Contacto'),
            title: translate('Contacto operativo'),
            lead: translate('A quién se llama el día del viaje.'),
            fields: [
                {
                    key: 'contact_name',
                    label: translate('Persona de contacto'),
                    type: 'text',
                    required: true,
                    width: 'half',
                },
                {
                    key: 'contact_role',
                    label: translate('Cargo'),
                    type: 'text',
                    width: 'half',
                    placeholder: translate('Coordinador de flota'),
                },
                {
                    key: 'contact_phone',
                    label: translate('Teléfono / WhatsApp'),
                    type: 'text',
                    required: true,
                    width: 'half',
                    placeholder: '+57 310 555 8821',
                },
                {
                    key: 'contact_email',
                    label: translate('Correo'),
                    type: 'email',
                    width: 'half',
                    placeholder: 'operaciones@proveedor.com',
                },
                {
                    key: 'alternate_contact',
                    label: translate('Contacto alterno 24/7'),
                    type: 'text',
                    width: 'half',
                    placeholder: translate('Nombre y teléfono'),
                },
                {
                    key: 'service_hours',
                    label: translate('Horario de atención'),
                    type: 'text',
                    width: 'half',
                    placeholder: 'Lun–sáb 6:00 a. m. – 8:00 p. m.',
                },
                {
                    key: 'address',
                    label: translate('Dirección'),
                    type: 'address',
                    required: true,
                    width: 'full',
                    fills: {
                        latitude: 'latitude',
                        longitude: 'longitude',
                        city: 'city',
                        state: 'state',
                    },
                },
                {
                    key: 'city',
                    label: translate('Municipio'),
                    type: 'text',
                    required: true,
                    width: 'third',
                    placeholder: 'Armenia',
                },
                {
                    key: 'state',
                    label: translate('Departamento'),
                    type: 'text',
                    width: 'third',
                    placeholder: 'Quindío',
                },
                {
                    key: 'coverage',
                    label: translate('Cobertura'),
                    type: 'text',
                    width: 'third',
                    placeholder: translate('Eje cafetero'),
                },
            ],
        },
        {
            id: 'rates',
            nav: translate('Tarifas'),
            title: translate('Tarifas'),
            lead: translate(
                'Cada modalidad que te cobran, para calcular el costo de la salida.',
            ),
            fields: [
                {
                    key: 'rates',
                    label: translate('Tarifas acordadas'),
                    type: 'repeat',
                    width: 'full',
                    addLabel: translate('Agregar tarifa'),
                    columns: [
                        {
                            key: 'concept',
                            label: translate('Concepto'),
                            type: 'text',
                            placeholder: translate('Bus 40 puestos'),
                            width: 'minmax(0,1.5fr)',
                        },
                        {
                            key: 'amount',
                            label: translate('Valor'),
                            type: 'number',
                            placeholder: '320',
                            width: 'minmax(0,0.8fr)',
                        },
                        {
                            key: 'unit',
                            label: translate('Unidad'),
                            type: 'select',
                            options: rateUnitOptions(),
                            width: 'minmax(0,0.9fr)',
                        },
                    ],
                },
                {
                    key: 'currency',
                    label: translate('Moneda'),
                    type: 'select',
                    required: true,
                    width: 'half',
                    options: currencyOptions(),
                },
                {
                    key: 'rates_valid_until',
                    label: translate('Vigencia de tarifas'),
                    type: 'date',
                    width: 'half',
                },
            ],
        },
        {
            id: 'documents',
            nav: translate('Documentos'),
            title: translate('Documentos y cumplimiento'),
            lead: translate(
                'Vencimientos que se revisan antes de cada salida.',
            ),
            fields: [
                {
                    key: 'documents',
                    label: translate('Documentos'),
                    type: 'repeat',
                    width: 'full',
                    addLabel: translate('Agregar documento'),
                    columns: [
                        {
                            key: 'kind',
                            label: translate('Tipo'),
                            type: 'select',
                            options: documentTypeOptions(),
                            width: 'minmax(0,1.6fr)',
                        },
                        {
                            key: 'number',
                            label: translate('Número'),
                            type: 'text',
                            width: 'minmax(0,0.8fr)',
                        },
                        {
                            key: 'expires_at',
                            label: translate('Vence'),
                            type: 'date',
                            width: 'minmax(0,0.9fr)',
                        },
                    ],
                },
                {
                    key: 'notes',
                    label: translate('Notas internas'),
                    type: 'textarea',
                    width: 'full',
                    rows: 3,
                    placeholder: translate(
                        'Acuerdos, incidentes previos, preferencias',
                    ),
                },
            ],
        },
    ];
}

function hotelSections(): LogisticsSectionDef[] {
    return [
        {
            id: 'identification',
            nav: translate('Identificación'),
            title: translate('Identificación'),
            lead: translate('Nombre, tipo y datos de la propiedad.'),
            fields: [
                {
                    key: 'name',
                    label: translate('Nombre del hotel'),
                    type: 'text',
                    required: true,
                    width: 'full',
                    placeholder: 'Ecohotel La Montaña',
                },
                {
                    key: 'accommodation_type',
                    label: translate('Tipo de alojamiento'),
                    type: 'select',
                    required: true,
                    width: 'half',
                    options: accommodationTypeOptions(),
                },
                {
                    key: 'star_rating',
                    label: translate('Categoría'),
                    type: 'select',
                    width: 'half',
                    options: starRatingOptions(),
                },
                {
                    key: 'description',
                    label: translate('Descripción'),
                    type: 'textarea',
                    width: 'full',
                    rows: 3,
                    placeholder: translate(
                        'Qué encuentra el viajero al llegar',
                    ),
                },
                {
                    key: 'legal_name',
                    label: translate('Razón social'),
                    type: 'text',
                    width: 'half',
                    placeholder: 'Ecohotel La Montaña S.A.S.',
                },
                {
                    key: 'tax_id',
                    label: translate('NIT'),
                    type: 'text',
                    required: true,
                    width: 'half',
                    placeholder: '900.884.201-1',
                },
            ],
        },
        {
            id: 'location',
            nav: translate('Ubicación'),
            title: translate('Ubicación'),
            lead: translate('El guía verá este punto en el mapa del día.'),
            fields: [
                {
                    key: 'address',
                    label: translate('Dirección'),
                    type: 'address',
                    required: true,
                    width: 'full',
                    fills: {
                        latitude: 'latitude',
                        longitude: 'longitude',
                        city: 'city',
                        state: 'state',
                    },
                },
                {
                    key: 'city',
                    label: translate('Municipio'),
                    type: 'text',
                    required: true,
                    width: 'third',
                    placeholder: 'Salento',
                },
                {
                    key: 'state',
                    label: translate('Departamento'),
                    type: 'text',
                    width: 'third',
                    placeholder: 'Quindío',
                },
                {
                    key: 'country',
                    label: translate('País'),
                    type: 'text',
                    width: 'third',
                    placeholder: 'Colombia',
                },
                {
                    key: 'directions',
                    label: translate('Cómo llegar'),
                    type: 'textarea',
                    width: 'full',
                    rows: 2,
                    placeholder: translate(
                        'Últimos 2 km en destapado, apto para bus pequeño',
                    ),
                },
            ],
        },
        {
            id: 'rooms',
            nav: translate('Habitaciones'),
            title: translate('Habitaciones y tarifas'),
            lead: translate('Cada tipo con su tarifa negociada.'),
            fields: [
                {
                    key: 'rooms',
                    label: translate('Tipos de habitación'),
                    type: 'repeat',
                    width: 'full',
                    addLabel: translate('Agregar tipo'),
                    columns: [
                        {
                            key: 'name',
                            label: translate('Tipo de habitación'),
                            type: 'text',
                            placeholder: translate('Doble'),
                            width: 'minmax(0,1.5fr)',
                        },
                        {
                            key: 'quantity',
                            label: translate('Cantidad'),
                            type: 'number',
                            placeholder: '6',
                            width: 'minmax(0,0.6fr)',
                        },
                        {
                            key: 'nightly_rate',
                            label: translate('Tarifa por noche'),
                            type: 'number',
                            placeholder: '62',
                            width: 'minmax(0,1fr)',
                        },
                    ],
                },
                {
                    key: 'total_capacity',
                    label: translate('Capacidad total'),
                    type: 'number',
                    required: true,
                    width: 'third',
                    unit: translate('camas'),
                },
                {
                    key: 'currency',
                    label: translate('Moneda'),
                    type: 'select',
                    required: true,
                    width: 'third',
                    options: currencyOptions(),
                },
                {
                    key: 'rates_valid_until',
                    label: translate('Vigencia de tarifas'),
                    type: 'date',
                    width: 'third',
                },
                {
                    key: 'check_in',
                    label: translate('Check-in'),
                    type: 'text',
                    required: true,
                    width: 'half',
                    placeholder: '3:00 p. m.',
                },
                {
                    key: 'check_out',
                    label: translate('Check-out'),
                    type: 'text',
                    required: true,
                    width: 'half',
                    placeholder: '11:00 a. m.',
                },
            ],
        },
        {
            id: 'services',
            nav: translate('Servicios'),
            title: translate('Servicios incluidos'),
            lead: translate('Lo que el viajero recibe sin costo extra.'),
            fields: [
                {
                    key: 'amenities',
                    label: translate('Incluye'),
                    type: 'options',
                    multiple: true,
                    width: 'full',
                    options: amenityOptions(),
                },
                {
                    key: 'meal_plan',
                    label: translate('Alimentación'),
                    type: 'select',
                    width: 'half',
                    options: mealPlanOptions(),
                },
                {
                    key: 'diets',
                    label: translate('Dietas que manejan'),
                    type: 'text',
                    width: 'half',
                    placeholder: translate('Vegetariana, sin gluten'),
                },
                {
                    key: 'restrictions',
                    label: translate('Restricciones'),
                    type: 'textarea',
                    width: 'full',
                    rows: 2,
                    placeholder: translate(
                        'No admite mascotas, sin señal de celular',
                    ),
                },
            ],
        },
        {
            id: 'contact',
            nav: translate('Contacto'),
            title: translate('Contacto y políticas'),
            lead: translate('Con quién se confirma y bajo qué reglas.'),
            fields: [
                {
                    key: 'contact_name',
                    label: translate('Persona de contacto'),
                    type: 'text',
                    required: true,
                    width: 'half',
                    placeholder: translate('Recepción / nombre'),
                },
                {
                    key: 'contact_phone',
                    label: translate('Teléfono / WhatsApp'),
                    type: 'text',
                    required: true,
                    width: 'half',
                    placeholder: '+57 312 400 1188',
                },
                {
                    key: 'contact_email',
                    label: translate('Correo de reservas'),
                    type: 'email',
                    width: 'half',
                    placeholder: 'reservas@hotel.com',
                },
                {
                    key: 'emergency_contact',
                    label: translate('Contacto de emergencia'),
                    type: 'text',
                    width: 'half',
                    placeholder: translate('Nombre y teléfono'),
                },
                {
                    key: 'cancellation_policy',
                    label: translate('Política de cancelación'),
                    type: 'select',
                    required: true,
                    width: 'half',
                    options: cancellationPolicyOptions(),
                },
                {
                    key: 'payment_terms',
                    label: translate('Condiciones de pago'),
                    type: 'select',
                    required: true,
                    width: 'half',
                    options: paymentTermsOptions(),
                },
                {
                    key: 'notes',
                    label: translate('Notas internas'),
                    type: 'textarea',
                    width: 'full',
                    rows: 3,
                    placeholder: translate(
                        'Acuerdos, habitaciones preferidas, historial',
                    ),
                },
            ],
        },
    ];
}

export function sectionsFor(
    kind: LogisticsResourceKind,
): LogisticsSectionDef[] {
    if (kind === 'routes') {
        return routeSections();
    }

    if (kind === 'providers') {
        return providerSections();
    }

    return hotelSections();
}

/**
 * Latitud y longitud no son campos del formulario —nadie las teclea— pero sí
 * viajan: las rellena el buscador de direcciones. Sin esto el payload las
 * dejaba fuera y el punto elegido se perdía al guardar.
 */
function hiddenPointKeys(kind: LogisticsResourceKind): string[] {
    const keys: string[] = [];

    for (const section of sectionsFor(kind)) {
        for (const field of section.fields) {
            if (field.type !== 'address') {
                continue;
            }

            for (const key of [field.fills?.latitude, field.fills?.longitude]) {
                if (key !== undefined) {
                    keys.push(key);
                }
            }
        }
    }

    return keys;
}

function blankValue(field: LogisticsFieldDef): LogisticsFieldValue {
    if (field.type === 'repeat') {
        return [];
    }

    if (field.type === 'tags' || (field.type === 'options' && field.multiple)) {
        return [];
    }

    return '';
}

/**
 * Estado inicial del formulario. Un registro existente se vierte campo a campo
 * —incluidas sus listas— y una ficha nueva arranca vacía.
 */
export function formStateFor(
    kind: LogisticsResourceKind,
    record: LogisticsRecord | null,
): LogisticsFormState {
    const state: LogisticsFormState = {};
    const source = record as unknown as Record<string, unknown> | null;

    for (const section of sectionsFor(kind)) {
        for (const field of section.fields) {
            const raw = source?.[field.key];

            if (field.type === 'repeat') {
                state[field.key] = Array.isArray(raw)
                    ? raw.map((row) => rowToStrings(row, field))
                    : [];

                continue;
            }

            if (
                field.type === 'tags' ||
                (field.type === 'options' && field.multiple)
            ) {
                state[field.key] = Array.isArray(raw) ? raw.map(String) : [];

                continue;
            }

            state[field.key] =
                raw === null || raw === undefined
                    ? blankValue(field)
                    : String(raw);
        }
    }

    for (const key of hiddenPointKeys(kind)) {
        const raw = source?.[key];
        state[key] = raw === null || raw === undefined ? '' : String(raw);
    }

    return state;
}

function rowToStrings(
    row: unknown,
    field: LogisticsFieldDef,
): Record<string, string> {
    const source = (row ?? {}) as Record<string, unknown>;
    const result: Record<string, string> = {};

    for (const column of field.columns ?? []) {
        const value = source[column.key];
        result[column.key] =
            value === null || value === undefined ? '' : String(value);
    }

    return result;
}

/** Fila nueva de una lista repetible: los selects arrancan en su primera opción. */
export function blankRow(field: LogisticsFieldDef): Record<string, string> {
    const row: Record<string, string> = {};

    for (const column of field.columns ?? []) {
        row[column.key] =
            column.type === 'select' ? (column.options?.[0]?.value ?? '') : '';
    }

    return row;
}

export function isFilled(value: LogisticsFieldValue): boolean {
    if (Array.isArray(value)) {
        return value.length > 0;
    }

    return typeof value === 'string' && value.trim() !== '';
}

/**
 * Payload para la API: lo vacío viaja como `null` y las listas repetibles
 * sueltan las filas que quedaron en blanco, para no crear una parada sin
 * nombre porque alguien pulsó «Agregar» y se arrepintió.
 */
export function toPayload(
    kind: LogisticsResourceKind,
    state: LogisticsFormState,
): Record<string, unknown> {
    const payload: Record<string, unknown> = {};

    for (const section of sectionsFor(kind)) {
        for (const field of section.fields) {
            const value = state[field.key];

            if (field.type === 'repeat') {
                const rows = Array.isArray(value)
                    ? (value as Record<string, string>[])
                    : [];
                const anchor = field.columns?.[0]?.key ?? '';

                payload[field.key] = rows
                    .filter((row) => (row[anchor] ?? '').trim() !== '')
                    .map((row) => cleanRow(row));

                continue;
            }

            if (
                field.type === 'tags' ||
                (field.type === 'options' && field.multiple)
            ) {
                payload[field.key] = Array.isArray(value) ? value : [];

                continue;
            }

            const text = typeof value === 'string' ? value.trim() : '';
            payload[field.key] = text === '' ? null : text;
        }
    }

    for (const key of hiddenPointKeys(kind)) {
        const value = state[key];
        const text = typeof value === 'string' ? value.trim() : '';
        payload[key] = text === '' ? null : text;
    }

    return payload;
}

function cleanRow(row: Record<string, string>): Record<string, string | null> {
    const clean: Record<string, string | null> = {};

    for (const [key, value] of Object.entries(row)) {
        const text = value.trim();
        clean[key] = text === '' ? null : text;
    }

    return clean;
}
