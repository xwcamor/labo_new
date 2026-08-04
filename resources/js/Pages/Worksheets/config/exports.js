/**
 * Qué se puede exportar del listado de bancada, y a dónde va cada formato.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ SALE EL LISTADO, NO LOS VALORES MEDIDOS                                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Qué se corrió, qué día, quién, en qué estado y con cuántas muestras. Los
 * números del ensayo NO: el resultado se informa por su informe, que lleva
 * firma, código de verificación y límites de norma. Una planilla suelta con
 * los valores crudos y sin nada de eso es exactamente lo que el laboratorio
 * no puede mandarle a un cliente.
 *
 * Las claves son las MISMAS que valida el servidor (`buildExportOptions`): lo
 * que no esté en su lista blanca se descarta, aunque acá se ofrezca.
 */
export const worksheetsExportableColumns = (t) => [
    { key: 'run_date',         label: t('worksheets.run_date'),         default: true },
    { key: 'definition',       label: t('worksheets.test_definition'),  default: true },
    { key: 'analyst',          label: t('worksheets.analyst'),          default: true },
    { key: 'status',           label: t('worksheets.status'),           default: true },
    { key: 'rows_count',       label: t('worksheets.rows_count'),       default: true },
    { key: 'samples_count',    label: t('worksheets.samples_count'),    default: true },
    { key: 'validator',        label: t('worksheets.validated_by'),     default: true },
    { key: 'validated_at',     label: t('worksheets.validated_at') },
    // Las condiciones de la sala: son criterio real de auditoría, pero no
    // entran por omisión — la planilla de todos los días no las necesita.
    { key: 'ambient_temp_c',   label: t('worksheets.ambient_temp_c') },
    { key: 'ambient_humidity', label: t('worksheets.ambient_humidity') },
    { key: 'lab_pressure_hpa', label: t('worksheets.lab_pressure_hpa') },
    { key: 'notes',            label: t('worksheets.notes') },
    { key: 'created_at',       label: t('global.created_at') },
    { key: 'creator',          label: t('global.created_by') },
];

export const worksheetsExportEndpoints = () => ({
    csv:   route('lab_management.worksheets.export_csv'),
    excel: route('lab_management.worksheets.export_excel'),
    pdf:   route('lab_management.worksheets.export_pdf'),
    word:  route('lab_management.worksheets.export_word'),
});
