import {
    CalendarOutlined, ExperimentOutlined, TeamOutlined, ThunderboltOutlined,
    UserOutlined,
} from '@ant-design/icons-vue';

/**
 * Columnas del listado de recepciones.
 *
 * El orden responde a lo que se pregunta quien entra: qué día entró, con qué
 * número y de quién. Recién después, cuánto trae y cuánto falta — que es el
 * dato por el que el laboratorio decide qué entrega sigue abierta.
 *
 * `outstanding_count` lo cuenta el servidor en la MISMA consulta del listado
 * (un `withCount` con filtro). No se deriva acá ni se pide por fila: eso es lo
 * que en el sistema anterior convertía abrir el listado en cientos de
 * consultas.
 */
export const receptionsTableColumns = (t) => [
    // El pin de favoritos: la estrella que fija la entrega arriba del listado.
    // `is_favorite` lo calcula el servidor en la misma consulta (HasFavorites).
    {
        title: '★',
        dataIndex: 'is_favorite',
        key: 'favorite',
        width: 52,
        align: 'center',
        alwaysVisible: true,
        mobile: { role: 'pin' },
    },
    {
        title: t('receptions.received_at'),
        dataIndex: 'received_at',
        key: 'received_at',
        width: 140,
        sorter: true,
        mobile: { role: 'subtitle', icon: CalendarOutlined },
    },
    {
        // La FECHA COMPROMETIDA. Estaba en el sistema anterior (F.Entrega) y
        // acá no salía, aunque la columna existe en la base desde el principio:
        // es la mitad que falta para saber si una entrega va a tiempo.
        title: t('receptions.due_at'),
        dataIndex: 'due_at',
        key: 'due_at',
        width: 130,
        sorter: true,
        mobile: { role: 'meta' },
    },
    {
        // DÍAS RESTANTES, con su color. En el papel del laboratorio esto no es
        // adorno: es por lo que se decide qué entrega se trabaja hoy. Se
        // calcula en la pantalla —es la resta de dos fechas que ya viajan— y
        // por eso no cuesta una consulta más.
        title: t('receptions.days_left'),
        key: 'days_left',
        width: 110,
        align: 'center',
        mobile: { role: 'meta' },
    },
    {
        title: t('receptions.code'),
        dataIndex: 'code',
        key: 'code',
        width: 150,
        mobile: { role: 'title' },
    },
    {
        // Nº DE ORDEN DE SERVICIO, con su marca de PENDIENTE.
        //
        // Es el número con el que el CLIENTE cita la entrega, y muchas veces
        // llega días después de la muestra. El sistema anterior lo mostraba con
        // una etiqueta roja «Pendiente» cuando faltaba, y eso era una lista de
        // trabajo: las entregas que hay que ir a completar. Acá el campo
        // existía desde el principio y no se mostraba en el listado.
        title: t('receptions.service_order'),
        dataIndex: 'service_order',
        key: 'service_order',
        width: 150,
        mobile: { role: 'meta' },
    },
    {
        title: t('receptions.customer'),
        dataIndex: ['customer', 'name'],
        key: 'customer',
        mobile: { role: 'meta', icon: TeamOutlined },
    },
    {
        // QUIÉN EXTRAJO LA MUESTRA. En el sistema anterior era una columna del
        // listado y se filtraba por ella: sirve para reclamarle a la cuadrilla
        // que trajo mal una entrega, que es un uso real y frecuente.
        title: t('receptions.sampler'),
        dataIndex: ['sampler', 'name'],
        key: 'sampler',
        width: 170,
        mobile: { role: 'meta', icon: UserOutlined },
    },
    {
        title: t('receptions.samples'),
        dataIndex: 'samples_count',
        key: 'samples_count',
        width: 110,
        align: 'right',
        mobile: { role: 'meta', icon: ExperimentOutlined },
    },
    {
        // El semáforo de avance: Equipo · Pruebas · Datos · Informes. Son los
        // cuatro chequeos que el sistema anterior mostraba como iconos por
        // fila, pero acá los cuenta el servidor en la misma consulta del
        // listado (allá eran banderas cacheadas que solo se refrescaban al
        // abrir la remisión, y el listado mentía hasta la próxima visita).
        title: t('receptions.progress'),
        key: 'progress',
        width: 210,
        mobile: { role: 'meta' },
    },
    {
        title: t('receptions.status'),
        dataIndex: 'status',
        key: 'status',
        width: 140,
        mobile: { role: 'status' },
    },
    {
        title: t('receptions.is_urgent'),
        dataIndex: 'is_urgent',
        key: 'is_urgent',
        width: 110,
        mobile: { role: 'meta', icon: ThunderboltOutlined, hideWhenZero: true },
    },
];
