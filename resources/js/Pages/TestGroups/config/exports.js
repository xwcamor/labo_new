/**
 * Columnas exportables del módulo Grupos de pruebas. Independientes de las
 * visibles en pantalla — el usuario elige en el ExportDialog qué exportar.
 *
 * Sin ID (no se exporta). `tenant` (workspace) SOLO se ofrece a super: el resto
 * ve únicamente los grupos de su propio workspace. El backend lo gatea igual
 * (seguridad real); esto es solo para no listarla en el ExportDialog.
 */
export const testGroupsExportableColumns = (t, { isSuper = false } = {}) => [
    { key: 'code',        label: t('test_groups.code'),        default: true  },
    { key: 'name',        label: t('test_groups.name'),        default: true  },
    { key: 'tests_count', label: t('test_groups.tests_count'), default: true  },
    { key: 'sort_order',  label: t('test_groups.sort_order'),  default: true  },
    { key: 'is_active',   label: t('test_groups.is_active'),   default: true  },
    ...(isSuper ? [{ key: 'tenant', label: t('tenants.singular'), default: false }] : []),
    { key: 'created_at', label: t('global.created_at'),   default: false },
    { key: 'updated_at', label: t('global.updated_at'),   default: false },
    { key: 'creator',    label: t('global.created_by'),   default: false },
];

export const testGroupsExportEndpoints = () => ({
    excel: route('lab_management.test_groups.export_excel'),
    pdf:   route('lab_management.test_groups.export_pdf'),
    word:  route('lab_management.test_groups.export_word'),
    csv:   route('lab_management.test_groups.export_csv'),
});
