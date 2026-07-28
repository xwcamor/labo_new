import dayjs from 'dayjs';

/**
 * Filtros del módulo Instrumentos.
 *
 * `calibration_status` es el filtro que justifica el módulo: responde "qué
 * equipos no puedo usar hoy" de un vistazo. El backend lo resuelve en SQL
 * sobre `calibration_due_at` (ver Instrument::scopeFilter), no en PHP, para que
 * pagine y ordene como cualquier otro.
 */
export const instrumentsFilterFields = (t) => [
    { key: 'name',           label: t('instruments.filter_name'), type: 'tags' },
    { key: 'code',           label: t('instruments.code'),        type: 'text' },
    { key: 'brand',          label: t('instruments.brand'),       type: 'text' },
    { key: 'location',       label: t('instruments.location'),    type: 'text' },
    { key: 'calibration_status', label: t('instruments.calibration_status'), type: 'select', options: [
        { value: 'valid',    label: t('instruments.cal_status_valid')    },
        { value: 'due_soon', label: t('instruments.cal_status_due_soon') },
        { value: 'expired',  label: t('instruments.cal_status_expired')  },
        { value: 'unknown',  label: t('instruments.cal_status_unknown')  },
    ]},
    { key: 'calibration_due_at', label: t('instruments.calibration_due_at'), type: 'date_range' },
    { key: 'is_active',      label: t('instruments.is_active'),   type: 'select', options: [
        { value: true,  label: t('global.active')   },
        { value: false, label: t('global.inactive') },
    ]},
    { key: 'created_at',     label: t('global.created_at'),     type: 'date_range' },
    { key: 'only_favorites', label: t('global.only_favorites'), type: 'switch' },
];

/** Estado vacío del form de filtros (también usado por clearFilters). */
export const instrumentsEmptyFilters = () => ({
    name: [],
    code: '',
    brand: '',
    location: '',
    calibration_status: null,
    calibration_due_at: null,
    is_active: null,
    created_at: null,
    only_favorites: false,
});

/** Backend payload → form local (dates ISO → dayjs). */
export const hydrateInstrumentsFilters = (server) => ({
    name:       Array.isArray(server.name) ? server.name : [],
    code:       server.code || '',
    brand:      server.brand || '',
    location:   server.location || '',
    calibration_status: server.calibration_status ?? null,
    calibration_due_at: (server.due_from && server.due_to)
        ? [dayjs(server.due_from), dayjs(server.due_to)]
        : null,
    is_active:  server.is_active ?? null,
    created_at: (server.created_from && server.created_to)
        ? [dayjs(server.created_from), dayjs(server.created_to)]
        : null,
    only_favorites: server.only_favorites ?? false,
});

/** Form local → request params para Inertia reload. */
export const instrumentsFiltersToQuery = (f) => ({
    name:           f.name?.length ? f.name : undefined,
    code:           f.code || undefined,
    brand:          f.brand || undefined,
    location:       f.location || undefined,
    calibration_status: f.calibration_status || undefined,
    due_from:       f.calibration_due_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    due_to:         f.calibration_due_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    is_active:      f.is_active ?? undefined,
    created_from:   f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:     f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_favorites: f.only_favorites ? 1 : undefined,
});

/** Resumen legible para la portada del export PDF/Word. */
export const instrumentsFiltersSummary = (f, t) => {
    const parts = [];
    if (f.name?.length)  parts.push(`${t('instruments.filter_name')}: ${f.name.join(', ')}`);
    if (f.code)          parts.push(`${t('instruments.code')}: ${f.code}`);
    if (f.brand)         parts.push(`${t('instruments.brand')}: ${f.brand}`);
    if (f.location)      parts.push(`${t('instruments.location')}: ${f.location}`);
    if (f.calibration_status) {
        parts.push(`${t('instruments.calibration_status')}: ${t('instruments.cal_status_' + f.calibration_status)}`);
    }
    if (f.calibration_due_at) {
        parts.push(`${t('instruments.calibration_due_at')}: ${f.calibration_due_at[0]?.format('YYYY-MM-DD')} → ${f.calibration_due_at[1]?.format('YYYY-MM-DD')}`);
    }
    if (f.is_active !== null && f.is_active !== undefined) {
        parts.push(`${t('instruments.is_active')}: ${f.is_active ? t('global.active') : t('global.inactive')}`);
    }
    if (f.created_at)    parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} → ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' · ');
};

/**
 * Serialización de filtros para Saved Views (JSON-safe: dayjs → ISO strings).
 * Round-trip con `deserializeSavedFilters`.
 */
export const serializeSavedFilters = (f) => ({
    name:           f.name ?? [],
    code:           f.code ?? '',
    brand:          f.brand ?? '',
    location:       f.location ?? '',
    calibration_status: f.calibration_status ?? null,
    calibration_due_at: f.calibration_due_at?.[0]
        ? [f.calibration_due_at[0].format('YYYY-MM-DD'), f.calibration_due_at[1]?.format('YYYY-MM-DD')]
        : null,
    is_active:      f.is_active ?? null,
    created_at:     f.created_at?.[0]
        ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')]
        : null,
    only_favorites: !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    name:           Array.isArray(f.name) ? f.name : [],
    code:           f.code ?? '',
    brand:          f.brand ?? '',
    location:       f.location ?? '',
    calibration_status: f.calibration_status ?? null,
    calibration_due_at: f.calibration_due_at?.[0]
        ? [dayjs(f.calibration_due_at[0]), dayjs(f.calibration_due_at[1])]
        : null,
    is_active:      f.is_active ?? null,
    created_at:     f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
    only_favorites: f.only_favorites ?? false,
});
