/**
 * Columnas exportables del módulo Equipment. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog qué columnas exportar.
 *
 * Sin ID (no se exporta). `tenant` (workspace) SOLO se ofrece a super: el resto
 * ve únicamente marcas de su propio tenant. El backend lo gatea igual
 * (seguridad real); esto es solo para no listarla en el ExportDialog.
 */
export const equipmentExportableColumns = (t, { isSuper = false } = {}) => [
    { key: 'name',       label: t('equipment.name'),      default: true  },
    { key: 'code',       label: t('equipment.code'),      default: true  },
    { key: 'sort_order', label: t('equipment.sort_order'), default: true  },
    { key: 'is_active',  label: t('equipment.is_active'), default: true  },
    ...(isSuper ? [{ key: 'tenant', label: t('tenants.singular'), default: false }] : []),
    { key: 'created_at', label: t('global.created_at'),   default: true  },
    { key: 'updated_at', label: t('global.updated_at'),   default: false },
    { key: 'creator',    label: t('global.created_by'),   default: false },
];

export const equipmentExportEndpoints = () => ({
    excel: route('business_management.equipment.export_excel'),
    pdf:   route('business_management.equipment.export_pdf'),
    word:  route('business_management.equipment.export_word'),
    csv:   route('business_management.equipment.export_csv'),
});
