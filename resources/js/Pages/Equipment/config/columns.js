import { ApartmentOutlined, CalendarOutlined } from '@ant-design/icons-vue';

/**
 * Columnas de la tabla principal de Equipment.
 *
 * El ID y el slug NO se muestran en el listado (datos técnicos): solo el super
 * los ve, y únicamente en el drawer de detalle y en el Show.
 *
 * `isSuper` agrega la columna Workspace (tenant): el super ve marcas cross-tenant,
 * así que necesita saber de qué workspace es cada una. El admin solo ve las suyas,
 * la columna sería redundante (y por eso tampoco aparece en el selector de columnas).
 */
export const equipmentTableColumns = (t, { isSuper = false, isMobile = false } = {}) => [
    { title: '★',                      dataIndex: 'is_favorite', key: 'favorite',   width: 52,  align: 'center', alwaysVisible: true, mobile: { role: 'pin' } },
    // Celda principal "rica": avatar + nombre + código como subtítulo (el código
    // ya no es columna aparte, va fundido aquí).
    { title: t('equipment.name'),     dataIndex: 'name',        key: 'name',       sorter: (a, b) => a.name.localeCompare(b.name), alwaysVisible: true, mobile: { role: 'title' } },
    { title: t('equipment.serial'), dataIndex: 'serial', key: 'serial', mobile: { role: 'meta' } },
    { title: t('equipment.tag'), dataIndex: 'tag', key: 'tag', width: 110, mobile: { role: 'meta' } },
    // La placa completa en una columna. Tres columnas de tensión dejaban el
    // índice con dos vacías en casi todos los equipos.
    { title: t('equipment.voltage_label'), dataIndex: 'voltage_label', key: 'voltage_label', width: 130, mobile: { role: 'meta' } },
    { title: t('equipment.power_label'), dataIndex: 'power_label', key: 'power_label', width: 130, mobile: { role: 'meta' } },
    { title: t('equipment.phases'), dataIndex: 'phases', key: 'phases', width: 80, mobile: { role: 'meta' } },
    { title: t('equipment.manufacture_year'), dataIndex: 'manufacture_year', key: 'manufacture_year', mobile: { role: 'meta' } },
    { title: t('equipment.oil_volume'), dataIndex: 'oil_volume', key: 'oil_volume', width: 130, mobile: { role: 'meta' } },
    { title: t('equipment.external_ref'), dataIndex: 'external_ref', key: 'external_ref', mobile: { role: 'meta' } },
    ...(isSuper ? [
        { title: t('tenants.singular'), dataIndex: ['tenant', 'name'], key: 'tenant', width: 180, sorter: true, mobile: { role: 'meta', icon: ApartmentOutlined } },
    ] : []),
    { title: t('equipment.is_active'), dataIndex: 'is_active',   key: 'status',     width: 150, sorter: (a, b) => Number(a.is_active) - Number(b.is_active), mobile: { role: 'status' } },
    { title: t('global.created_at'),   dataIndex: 'created_at',  key: 'created_at', sorter: true, width: 180, mobile: { role: 'meta', icon: CalendarOutlined }, defaultHidden: true },
    { title: t('global.actions'),      key: 'actions',           width: isMobile ? 56 : 150, fixed: 'right', align: 'center', alwaysVisible: true, mobile: { role: 'actions' } },
];
