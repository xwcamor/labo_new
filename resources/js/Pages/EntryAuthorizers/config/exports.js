/**
 * Columnas exportables del módulo EntryAuthorizers. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog qué columnas exportar.
 *
 * Sin ID (no se exporta). `tenant` (workspace) SOLO se ofrece a super: el resto
 * ve únicamente marcas de su propio tenant. El backend lo gatea igual
 * (seguridad real); esto es solo para no listarla en el ExportDialog.
 */
export const entryAuthorizersExportableColumns = (t, { isSuper = false } = {}) => [
    { key: 'name',       label: t('entry_authorizers.name'),      default: true  },
    { key: 'code',       label: t('entry_authorizers.code'),      default: true  },
    { key: 'sort_order', label: t('entry_authorizers.sort_order'), default: true  },
    { key: 'is_active',  label: t('entry_authorizers.is_active'), default: true  },
    ...(isSuper ? [{ key: 'tenant', label: t('tenants.singular'), default: false }] : []),
    { key: 'created_at', label: t('global.created_at'),   default: true  },
    { key: 'updated_at', label: t('global.updated_at'),   default: false },
    { key: 'creator',    label: t('global.created_by'),   default: false },
];

export const entryAuthorizersExportEndpoints = () => ({
    excel: route('business_management.entry_authorizers.export_excel'),
    pdf:   route('business_management.entry_authorizers.export_pdf'),
    word:  route('business_management.entry_authorizers.export_word'),
    csv:   route('business_management.entry_authorizers.export_csv'),
});
