import { CalendarOutlined, ExperimentOutlined, UserOutlined } from '@ant-design/icons-vue';

/**
 * Columnas del listado de hojas de trabajo.
 *
 * El orden responde a la pregunta que se hace quien entra: qué se corrió, qué
 * día y quién lo corrió; después, en qué punto del flujo está. El recuento de
 * filas y de muestras va al final porque es contexto, no criterio de búsqueda.
 *
 * La estrella abre el listado y las acciones lo cierran, fijas a la derecha: es
 * el mismo esqueleto que el resto de los índices del sistema, y el de bancada
 * era el único que no lo tenía.
 *
 * TODAS las columnas de dato se ordenan. El servidor las resuelve por su `key`
 * (`sorter.columnKey`) contra su lista blanca, incluidas las que viven en otra
 * tabla —prueba, analista, validador— y los dos recuentos. Antes solo se
 * ordenaba por fecha y estado, y "las hojas del analista tal, alfabéticas" no
 * tenía forma.
 */
export const worksheetsTableColumns = (t, isMobile = false) => [
    // El pin de favoritos. `is_favorite` lo calcula el servidor en la misma
    // consulta del listado (HasFavorites): no cuesta una consulta por fila.
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
        title: t('worksheets.run_date'),
        dataIndex: 'run_date',
        key: 'run_date',
        width: 140,
        sorter: true,
        mobile: { role: 'subtitle', icon: CalendarOutlined },
    },
    {
        title: t('worksheets.test_definition'),
        dataIndex: ['definition', 'name'],
        key: 'definition',
        sorter: true,
        mobile: { role: 'title', icon: ExperimentOutlined },
    },
    {
        title: t('worksheets.analyst'),
        dataIndex: ['analyst', 'name'],
        key: 'analyst',
        width: 200,
        sorter: true,
        mobile: { role: 'meta', icon: UserOutlined },
    },
    {
        title: t('worksheets.status'),
        dataIndex: 'status',
        key: 'status',
        width: 150,
        sorter: true,
        mobile: { role: 'status' },
    },
    {
        title: t('worksheets.rows_count'),
        dataIndex: 'rows_count',
        key: 'rows_count',
        width: 100,
        align: 'right',
        sorter: true,
        mobile: { role: 'meta' },
    },
    {
        title: t('worksheets.samples_count'),
        dataIndex: 'samples_count',
        key: 'samples_count',
        width: 110,
        align: 'right',
        sorter: true,
        mobile: { role: 'meta' },
    },
    {
        title: t('worksheets.validated_by'),
        dataIndex: ['validator', 'name'],
        key: 'validator',
        width: 190,
        sorter: true,
        mobile: { role: 'meta' },
    },
    {
        title: t('global.actions'),
        key: 'actions',
        width: isMobile ? 56 : 170,
        fixed: 'right',
        align: 'center',
        alwaysVisible: true,
        mobile: { role: 'actions' },
    },
];
