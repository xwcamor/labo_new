import dayjs from 'dayjs';

/**
 * Filtros del módulo Pruebas.
 *
 * El filtro por GRUPO recibe las opciones desde el backend (son tres filas: se
 * mandan enteras en vez de montar un endpoint de búsqueda). Los dos filtros de
 * control responden la pregunta que se hace de verdad al revisar el catálogo:
 * "qué pruebas exigen patrón" y "cuáles exigen duplicado".
 */
export const testDefinitionsFilterFields = (t, { groups = [] } = {}) => [
    { key: 'name',           label: t('test_definitions.filter_name'), type: 'tags' },
    { key: 'code',           label: t('test_definitions.code'),        type: 'text' },
    { key: 'test_group_id',  label: t('test_definitions.group'),       type: 'select', options: groups },
    { key: 'requires_control',   label: t('test_definitions.requires_control'),   type: 'select', options: [
        { value: true,  label: t('global.yes') },
        { value: false, label: t('global.no')  },
    ]},
    { key: 'requires_duplicate', label: t('test_definitions.requires_duplicate'), type: 'select', options: [
        { value: true,  label: t('global.yes') },
        { value: false, label: t('global.no')  },
    ]},
    { key: 'is_active',      label: t('test_definitions.is_active'),   type: 'select', options: [
        { value: true,  label: t('global.active')   },
        { value: false, label: t('global.inactive') },
    ]},
    { key: 'created_at',     label: t('global.created_at'),     type: 'date_range' },
    { key: 'only_favorites', label: t('global.only_favorites'), type: 'switch' },
];

/** Estado vacío del form de filtros (también usado por clearFilters). */
export const testDefinitionsEmptyFilters = () => ({
    name: [],
    code: '',
    test_group_id: null,
    requires_control: null,
    requires_duplicate: null,
    is_active: null,
    created_at: null,
    only_favorites: false,
});

/** Backend payload → form local (dates ISO → dayjs). */
export const hydrateTestDefinitionsFilters = (server) => ({
    name:       Array.isArray(server.name) ? server.name : [],
    code:       server.code || '',
    test_group_id:      server.test_group_id ?? null,
    requires_control:   server.requires_control ?? null,
    requires_duplicate: server.requires_duplicate ?? null,
    is_active:  server.is_active ?? null,
    created_at: (server.created_from && server.created_to)
        ? [dayjs(server.created_from), dayjs(server.created_to)]
        : null,
    only_favorites: server.only_favorites ?? false,
});

/** Form local → request params para Inertia reload. */
export const testDefinitionsFiltersToQuery = (f) => ({
    name:           f.name?.length ? f.name : undefined,
    code:           f.code || undefined,
    test_group_id:      f.test_group_id ?? undefined,
    requires_control:   f.requires_control ?? undefined,
    requires_duplicate: f.requires_duplicate ?? undefined,
    is_active:      f.is_active ?? undefined,
    created_from:   f.created_at?.[0]?.format('YYYY-MM-DD') ?? undefined,
    created_to:     f.created_at?.[1]?.format('YYYY-MM-DD') ?? undefined,
    only_favorites: f.only_favorites ? 1 : undefined,
});

/** Resumen legible para la portada del export PDF/Word. */
export const testDefinitionsFiltersSummary = (f, t, { groups = [] } = {}) => {
    const parts = [];
    if (f.name?.length) parts.push(`${t('test_definitions.filter_name')}: ${f.name.join(', ')}`);
    if (f.code)         parts.push(`${t('test_definitions.code')}: ${f.code}`);
    if (f.test_group_id) {
        const g = groups.find((o) => o.value === f.test_group_id);
        parts.push(`${t('test_definitions.group')}: ${g?.label ?? f.test_group_id}`);
    }
    if (f.requires_control !== null && f.requires_control !== undefined) {
        parts.push(`${t('test_definitions.requires_control')}: ${f.requires_control ? t('global.yes') : t('global.no')}`);
    }
    if (f.requires_duplicate !== null && f.requires_duplicate !== undefined) {
        parts.push(`${t('test_definitions.requires_duplicate')}: ${f.requires_duplicate ? t('global.yes') : t('global.no')}`);
    }
    if (f.is_active !== null && f.is_active !== undefined) {
        parts.push(`${t('test_definitions.is_active')}: ${f.is_active ? t('global.active') : t('global.inactive')}`);
    }
    if (f.created_at) parts.push(`${t('global.created_at')}: ${f.created_at[0]?.format('YYYY-MM-DD')} → ${f.created_at[1]?.format('YYYY-MM-DD')}`);
    return parts.join(' · ');
};

/**
 * Serialización de filtros para Saved Views (JSON-safe: dayjs → ISO strings).
 * Round-trip con `deserializeSavedFilters`.
 */
export const serializeSavedFilters = (f) => ({
    name:           f.name ?? [],
    code:           f.code ?? '',
    test_group_id:      f.test_group_id ?? null,
    requires_control:   f.requires_control ?? null,
    requires_duplicate: f.requires_duplicate ?? null,
    is_active:      f.is_active ?? null,
    created_at:     f.created_at?.[0]
        ? [f.created_at[0].format('YYYY-MM-DD'), f.created_at[1]?.format('YYYY-MM-DD')]
        : null,
    only_favorites: !!f.only_favorites,
});

export const deserializeSavedFilters = (f = {}) => ({
    name:           Array.isArray(f.name) ? f.name : [],
    code:           f.code ?? '',
    test_group_id:      f.test_group_id ?? null,
    requires_control:   f.requires_control ?? null,
    requires_duplicate: f.requires_duplicate ?? null,
    is_active:      f.is_active ?? null,
    created_at:     f.created_at?.[0] ? [dayjs(f.created_at[0]), dayjs(f.created_at[1])] : null,
    only_favorites: f.only_favorites ?? false,
});
