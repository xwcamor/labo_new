import { CalendarOutlined, ExperimentOutlined, UserOutlined } from '@ant-design/icons-vue';

/**
 * Columnas del listado de hojas de trabajo.
 *
 * El orden responde a la pregunta que se hace quien entra: qué se corrió, qué
 * día y quién lo corrió; después, en qué punto del flujo está. El recuento de
 * filas y de muestras va al final porque es contexto, no criterio de búsqueda.
 */
export const worksheetsTableColumns = (t) => [
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
        mobile: { role: 'title', icon: ExperimentOutlined },
    },
    {
        title: t('worksheets.analyst'),
        dataIndex: ['analyst', 'name'],
        key: 'analyst',
        width: 200,
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
        mobile: { role: 'meta' },
    },
    {
        title: t('worksheets.samples_count'),
        dataIndex: 'samples_count',
        key: 'samples_count',
        width: 110,
        align: 'right',
        mobile: { role: 'meta' },
    },
    {
        title: t('worksheets.validated_by'),
        dataIndex: ['validator', 'name'],
        key: 'validator',
        width: 190,
        mobile: { role: 'meta' },
    },
];
