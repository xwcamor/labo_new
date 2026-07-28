import { ApartmentOutlined, CalendarOutlined, FolderOpenOutlined } from '@ant-design/icons-vue';

/**
 * Columnas de la tabla principal de Pruebas.
 *
 * Después del nombre va el GRUPO (es como el laboratorio piensa el catálogo:
 * Físico Químico / Cromatografía / Otros) y enseguida las banderas de control,
 * que son las que cambian el comportamiento de la hoja de trabajo.
 *
 * El ID y el slug NO se muestran en el listado (datos técnicos): solo el super
 * los ve, y únicamente en la ficha.
 */
export const testDefinitionsTableColumns = (t, { isSuper = false, isMobile = false } = {}) => [
    { title: '★',                      dataIndex: 'is_favorite', key: 'favorite',   width: 52,  align: 'center', alwaysVisible: true, mobile: { role: 'pin' } },
    // Celda principal "rica": nombre con el código como subtítulo.
    { title: t('test_definitions.name'),  dataIndex: 'name',     key: 'name',       sorter: (a, b) => (a.name || '').localeCompare(b.name || ''), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('test_definitions.group'), dataIndex: ['group', 'name'], key: 'group', width: 180, sorter: true, mobile: { role: 'meta', icon: FolderOpenOutlined } },
    { title: t('test_definitions.section_control'), key: 'control', width: 210 },
    { title: t('test_definitions.replicates'), dataIndex: 'replicates', key: 'replicates', width: 120, align: 'right', sorter: true },
    { title: t('test_definitions.container'),  dataIndex: 'container',  key: 'container',  width: 180, defaultHidden: true },
    { title: t('test_definitions.fields_count'), dataIndex: 'fields_count', key: 'fields_count', width: 120, align: 'right', defaultHidden: true },
    { title: t('test_definitions.sort_order'), dataIndex: 'sort_order', key: 'sort_order', width: 110, align: 'right', sorter: true, defaultHidden: true },
    ...(isSuper ? [
        { title: t('tenants.singular'), dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, sorter: true, mobile: { role: 'meta', icon: ApartmentOutlined } },
    ] : []),
    { title: t('test_definitions.is_active'), dataIndex: 'is_active', key: 'status', width: 130, sorter: (a, b) => Number(a.is_active) - Number(b.is_active), mobile: { role: 'status' } },
    { title: t('global.created_at'),   dataIndex: 'created_at',  key: 'created_at', sorter: true, width: 180, mobile: { role: 'meta', icon: CalendarOutlined }, defaultHidden: true },
    { title: t('global.actions'),      key: 'actions',           width: isMobile ? 56 : 150, fixed: 'right', align: 'center', alwaysVisible: true, mobile: { role: 'actions' } },
];
