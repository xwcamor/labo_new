import { ApartmentOutlined, CalendarOutlined } from '@ant-design/icons-vue';

/**
 * Columnas de la tabla principal de Analytes.
 *
 * El ID y el slug NO se muestran en el listado (datos técnicos): solo el super
 * los ve, y únicamente en el drawer de detalle y en el Show.
 *
 * `isSuper` agrega la columna Workspace (tenant): el super ve marcas cross-tenant,
 * así que necesita saber de qué workspace es cada una. El admin solo ve las suyas,
 * la columna sería redundante (y por eso tampoco aparece en el selector de columnas).
 */
export const analytesTableColumns = (t, { isSuper = false, isMobile = false } = {}) => [
    { title: '★',                      dataIndex: 'is_favorite', key: 'favorite',   width: 52,  align: 'center', alwaysVisible: true, mobile: { role: 'pin' } },
    // Celda principal "rica": avatar + nombre + código como subtítulo (el código
    // ya no es columna aparte, va fundido aquí).
    { title: t('analytes.name'),     dataIndex: 'name',        key: 'name',       sorter: (a, b) => a.name.localeCompare(b.name), alwaysVisible: true, mobile: { role: 'title' } },
    ...(isSuper ? [
        { title: t('tenants.singular'), dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, sorter: true, mobile: { role: 'meta', icon: ApartmentOutlined } },
    ] : []),
    // Dónde se mide y contra qué se juzga: es la relación con las pruebas, que
    // era justamente lo que no se veía. Sin ordenamiento porque son recuentos
    // de una relación, no columnas de la tabla.
    { title: t('analytes.measured_in'), key: 'measured_in', width: 260, mobile: { role: 'meta' } },
    { title: t('analytes.limits'),      key: 'limits',      width: 120, align: 'right', mobile: { role: 'meta' } },
    { title: t('analytes.is_active'), dataIndex: 'is_active',   key: 'status',     width: 150, sorter: (a, b) => Number(a.is_active) - Number(b.is_active), mobile: { role: 'status' } },
    { title: t('global.created_at'),   dataIndex: 'created_at',  key: 'created_at', sorter: true, width: 180, mobile: { role: 'meta', icon: CalendarOutlined }, defaultHidden: true },
    { title: t('global.actions'),      key: 'actions',           width: isMobile ? 56 : 150, fixed: 'right', align: 'center', alwaysVisible: true, mobile: { role: 'actions' } },
];
