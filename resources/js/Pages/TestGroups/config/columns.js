import { ApartmentOutlined, CalendarOutlined, FileDoneOutlined } from '@ant-design/icons-vue';

/**
 * Columnas de la tabla principal de Grupos de pruebas.
 *
 * La columna de cuántas pruebas cuelgan del grupo no es decorativa: es lo que
 * dice si un grupo se puede desactivar o borrar sin dejar pruebas sin
 * clasificar. La alimenta un withCount en el controlador.
 *
 * El ID y el slug NO se muestran en el listado (datos técnicos): solo el super
 * los ve, y únicamente en la ficha.
 */
export const testGroupsTableColumns = (t, { isSuper = false, isMobile = false } = {}) => [
    { title: '★',                      dataIndex: 'is_favorite', key: 'favorite',   width: 52,  align: 'center', alwaysVisible: true, mobile: { role: 'pin' } },
    { title: t('test_groups.code'),     dataIndex: 'code',        key: 'code',       width: 170, sorter: (a, b) => (a.code || '').localeCompare(b.code || ''), alwaysVisible: true, mobile: { role: 'subtitle' } },
    { title: t('test_groups.name'),     dataIndex: 'name',        key: 'name',       sorter: (a, b) => (a.name || '').localeCompare(b.name || ''), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('test_groups.tests_count'), dataIndex: 'tests_count', key: 'tests_count', width: 130, align: 'right', mobile: { role: 'meta', icon: FileDoneOutlined } },
    { title: t('test_groups.sort_order'), dataIndex: 'sort_order', key: 'sort_order', width: 110, align: 'right', sorter: true },
    ...(isSuper ? [
        { title: t('tenants.singular'), dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, sorter: true, mobile: { role: 'meta', icon: ApartmentOutlined } },
    ] : []),
    { title: t('test_groups.is_active'), dataIndex: 'is_active',   key: 'status',     width: 150, sorter: (a, b) => Number(a.is_active) - Number(b.is_active), mobile: { role: 'status' } },
    { title: t('global.created_at'),   dataIndex: 'created_at',  key: 'created_at', sorter: true, width: 180, mobile: { role: 'meta', icon: CalendarOutlined }, defaultHidden: true },
    { title: t('global.actions'),      key: 'actions',           width: isMobile ? 56 : 150, fixed: 'right', align: 'center', alwaysVisible: true, mobile: { role: 'actions' } },
];
