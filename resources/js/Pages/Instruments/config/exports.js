/**
 * Columnas exportables del módulo Instrumentos. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog qué columnas exportar.
 *
 * Por defecto salen las que hacen falta para una auditoría de calibración
 * (código, nombre, marca/modelo/serie, fechas y estado): es el uso real de
 * este export. Las notas y el orden quedan disponibles pero apagados.
 *
 * Sin ID (no se exporta). `tenant` (workspace) SOLO se ofrece a super: el resto
 * ve únicamente instrumentos de su propio tenant. El backend lo gatea igual
 * (seguridad real); esto es solo para no listarla en el ExportDialog.
 */
export const instrumentsExportableColumns = (t, { isSuper = false } = {}) => [
    { key: 'name',        label: t('instruments.name'),        default: true  },
    { key: 'description', label: t('instruments.description'), default: true  },
    { key: 'brand',      label: t('instruments.brand'),  default: true  },
    { key: 'model',      label: t('instruments.model'),  default: true  },
    { key: 'serial',     label: t('instruments.serial'), default: true  },
    { key: 'calibrated_at',      label: t('instruments.calibrated_at'),      default: true  },
    { key: 'calibration_due_at', label: t('instruments.calibration_due_at'), default: true  },
    { key: 'calibration_status', label: t('instruments.calibration_status'), default: true  },
    { key: 'calibration_certificate', label: t('instruments.calibration_certificate'), default: true },
    { key: 'location',   label: t('instruments.location'),   default: true  },
    { key: 'notes',      label: t('instruments.notes'),      default: false },
    { key: 'sort_order', label: t('instruments.sort_order'), default: false },
    { key: 'is_active',  label: t('instruments.is_active'),  default: true  },
    ...(isSuper ? [{ key: 'tenant', label: t('tenants.singular'), default: false }] : []),
    { key: 'created_at', label: t('global.created_at'),   default: false },
    { key: 'updated_at', label: t('global.updated_at'),   default: false },
    { key: 'creator',    label: t('global.created_by'),   default: false },
];

export const instrumentsExportEndpoints = () => ({
    excel: route('business_management.instruments.export_excel'),
    pdf:   route('business_management.instruments.export_pdf'),
    word:  route('business_management.instruments.export_word'),
    csv:   route('business_management.instruments.export_csv'),
});
