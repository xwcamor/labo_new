import { CalendarOutlined, ExperimentOutlined, UserOutlined } from '@ant-design/icons-vue';

/**
 * Columnas del listado de hojas de trabajo.
 *
 * El orden responde a lo que se pregunta quien entra: qué día, qué se corrió y
 * quién lo corrió; después cuánto trae —filas y muestras— y recién al final en
 * qué punto del flujo está. El tamaño de la corrida se lee antes que el estado
 * porque una hoja de tres filas y una de cuarenta son trabajos distintos, y eso
 * se decide antes de mirar si está terminada.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ ANALISTA NO ES "REGISTRADA POR"                                          │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El ANALISTA es quien corrió el ensayo: es el dato del laboratorio, el que
 * responde por la medición, y se puede cambiar (el supervisor a veces abre la
 * hoja del turno de otro). REGISTRADA POR es quien la creó en el sistema, que
 * es auditoría. Coinciden casi siempre, pero no son lo mismo, y por eso son dos
 * columnas: la de auditoría viene oculta y se enciende desde el selector.
 *
 * TODAS las columnas de dato se ordenan. El servidor las resuelve por su `key`
 * (`sorter.columnKey`) contra su lista blanca, incluidas las que viven en otra
 * tabla —prueba, analista, quien la completó— y los dos recuentos.
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
        // Quien CORRIÓ el ensayo. Ver el bloque de arriba.
        title: t('worksheets.analyst'),
        dataIndex: ['analyst', 'name'],
        key: 'analyst',
        width: 200,
        sorter: true,
        mobile: { role: 'meta', icon: UserOutlined },
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
        // El estado, con el candado al lado si la hoja está congelada. El
        // candado ES estado —dice que la hoja ya no admite cambios— y hasta
        // ahora solo se veía en la columna de acciones, al otro extremo de la
        // fila. En el sistema viejo "bloqueado" y "validado" eran el MISMO
        // campo, y de ahí salió que el filtro devolviera lo contrario de lo que
        // decía: acá son dos datos que se muestran juntos, no uno solo.
        title: t('worksheets.status'),
        dataIndex: 'status',
        key: 'status',
        width: 170,
        sorter: true,
        mobile: { role: 'status' },
    },
    {
        // Quien terminó de cargarla, no quien la revisó: nadie la revisa.
        title: t('worksheets.validated_by'),
        dataIndex: ['validator', 'name'],
        key: 'validator',
        width: 190,
        sorter: true,
        mobile: { role: 'meta' },
    },
    {
        // Auditoría: quién la creó en el sistema. Oculta por omisión — casi
        // siempre repite al analista, y cuando NO lo repite es justo el caso en
        // que hay que poder mirarla.
        title: t('worksheets.created_by'),
        dataIndex: ['creator', 'name'],
        key: 'creator',
        width: 190,
        defaultHidden: true,
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
