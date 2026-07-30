import { ApartmentOutlined, CalendarOutlined, EnvironmentOutlined, ExperimentOutlined } from '@ant-design/icons-vue';

/**
 * Columnas de la tabla principal de Instrumentos.
 *
 * El orden no es cosmético: primero QUÉ equipo es (código + nombre, con la
 * marca/modelo como subtítulo) y enseguida SI SE PUEDE USAR (estado de
 * calibración y fecha de vencimiento). Ese par es la razón de ser del módulo;
 * en el sistema viejo el instrumento era una opción de un select y no había
 * dónde ver si estaba vigente.
 *
 * El ID y el slug NO se muestran en el listado (datos técnicos): solo el super
 * los ve, y únicamente en la ficha.
 */
export const instrumentsTableColumns = (t, { isSuper = false, isMobile = false } = {}) => [
    { title: '★',                      dataIndex: 'is_favorite', key: 'favorite',   width: 52,  align: 'center', alwaysVisible: true, mobile: { role: 'pin' } },
    // El NOMBRE es el código de calibración (PP-LA-01C-056): identificador de
    // ancho fijo, primera columna, y es por donde se busca un instrumento en la
    // bancada. La DESCRIPCIÓN va después y se repite entre equipos.
    { title: t('instruments.name'),        dataIndex: 'name',        key: 'name',        width: 170, sorter: (a, b) => (a.name || '').localeCompare(b.name || ''), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('instruments.description'), dataIndex: 'description', key: 'description', sorter: (a, b) => (a.description || '').localeCompare(b.description || ''), alwaysVisible: true, mobile: { role: 'subtitle' } },
    // PARA QUÉ SIRVE. Es la pregunta que el módulo no contestaba: la relación
    // columna-instrumento ya estaba en los datos del laboratorio y la pantalla
    // la ignoraba, así que 24 equipos se veían como una lista plana.
    { title: t('instruments.tests'), dataIndex: 'tests', key: 'tests', width: 240, alwaysVisible: true, mobile: { role: 'meta', icon: ExperimentOutlined } },
    { title: t('instruments.calibration_status'), dataIndex: 'calibration_due_at', key: 'calibration', width: 200, sorter: true, mobile: { role: 'status' } },
    { title: t('instruments.calibration_due_at'), dataIndex: 'calibration_due_at', key: 'due_at',     width: 140, sorter: true, mobile: { role: 'meta', icon: CalendarOutlined }, defaultHidden: true },
    { title: t('instruments.serial'),    dataIndex: 'serial',      key: 'serial',     width: 150, defaultHidden: true },
    { title: t('instruments.location'),  dataIndex: 'location',    key: 'location',   width: 180, mobile: { role: 'meta', icon: EnvironmentOutlined } },
    ...(isSuper ? [
        { title: t('tenants.singular'), dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, sorter: true, mobile: { role: 'meta', icon: ApartmentOutlined } },
    ] : []),
    { title: t('instruments.is_active'), dataIndex: 'is_active',   key: 'status',     width: 130, sorter: (a, b) => Number(a.is_active) - Number(b.is_active) },
    { title: t('global.created_at'),   dataIndex: 'created_at',  key: 'created_at', sorter: true, width: 180, mobile: { role: 'meta', icon: CalendarOutlined }, defaultHidden: true },
    { title: t('global.actions'),      key: 'actions',           width: isMobile ? 56 : 150, fixed: 'right', align: 'center', alwaysVisible: true, mobile: { role: 'actions' } },
];

/**
 * Color del semáforo de calibración. Se comparte entre el listado y la ficha
 * para que el mismo estado no se vea de dos colores distintos.
 */
export const calibrationTagColor = (status) => ({
    valid:    'success',
    due_soon: 'warning',
    expired:  'error',
    unknown:  'default',
}[status] ?? 'default');
