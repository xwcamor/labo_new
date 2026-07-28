/**
 * Columnas exportables del módulo Pruebas. Independientes de las visibles en
 * pantalla — el usuario elige en el ExportDialog qué exportar.
 *
 * Por defecto sale la definición de la prueba (código, nombre, grupo, envase,
 * banderas de control y repeticiones): es lo que el laboratorio revisa cuando
 * audita su propio catálogo. `legacy_id` queda disponible pero apagado, porque
 * solo sirve para rastrear la migración.
 *
 * Sin ID (no se exporta). `tenant` (workspace) SOLO se ofrece a super: el resto
 * ve únicamente las pruebas de su propio workspace. El backend lo gatea igual
 * (seguridad real); esto es solo para no listarla en el ExportDialog.
 */
export const testDefinitionsExportableColumns = (t, { isSuper = false } = {}) => [
    { key: 'code',       label: t('test_definitions.code'),       default: true  },
    { key: 'name',       label: t('test_definitions.name'),       default: true  },
    { key: 'group',      label: t('test_definitions.group'),      default: true  },
    { key: 'container',  label: t('test_definitions.container'),  default: true  },
    { key: 'chart_unit', label: t('test_definitions.chart_unit'), default: true  },
    { key: 'has_control',        label: t('test_definitions.has_control'),        default: true  },
    { key: 'requires_control',   label: t('test_definitions.requires_control'),   default: true  },
    { key: 'requires_duplicate', label: t('test_definitions.requires_duplicate'), default: true  },
    { key: 'is_grouped',         label: t('test_definitions.is_grouped'),         default: false },
    { key: 'replicates', label: t('test_definitions.replicates'),  default: true  },
    { key: 'description', label: t('test_definitions.description'), default: false },
    { key: 'legacy_id',  label: t('test_definitions.legacy_id'),   default: false },
    { key: 'sort_order', label: t('test_definitions.sort_order'),  default: false },
    { key: 'is_active',  label: t('test_definitions.is_active'),   default: true  },
    ...(isSuper ? [{ key: 'tenant', label: t('tenants.singular'), default: false }] : []),
    { key: 'created_at', label: t('global.created_at'),   default: false },
    { key: 'updated_at', label: t('global.updated_at'),   default: false },
    { key: 'creator',    label: t('global.created_by'),   default: false },
];

export const testDefinitionsExportEndpoints = () => ({
    excel: route('lab_management.test_definitions.export_excel'),
    pdf:   route('lab_management.test_definitions.export_pdf'),
    word:  route('lab_management.test_definitions.export_word'),
    csv:   route('lab_management.test_definitions.export_csv'),
});
