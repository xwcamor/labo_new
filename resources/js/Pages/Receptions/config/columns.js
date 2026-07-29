import {
    CalendarOutlined, ExperimentOutlined, TeamOutlined, ThunderboltOutlined,
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
    {
        title: t('receptions.received_at'),
        dataIndex: 'received_at',
        key: 'received_at',
        width: 140,
        sorter: true,
        mobile: { role: 'subtitle', icon: CalendarOutlined },
    },
    {
        title: t('receptions.code'),
        dataIndex: 'code',
        key: 'code',
        width: 170,
        mobile: { role: 'title' },
    },
    {
        title: t('receptions.customer'),
        dataIndex: ['customer', 'name'],
        key: 'customer',
        mobile: { role: 'meta', icon: TeamOutlined },
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
