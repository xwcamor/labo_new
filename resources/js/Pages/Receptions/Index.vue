<script setup>
/**
 * Listado de recepciones: lo que entró al laboratorio.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ SE BUSCA POR EL NÚMERO QUE EL CLIENTE CITA                               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El buscador va contra el NÚMERO DE MUESTRA ("2026-0695"), que es lo que el
 * cliente dice por teléfono, y el servidor lo resuelve por columna (`sample`).
 * En el sistema anterior ese código se armaba al vuelo y había tres lugares
 * distintos que lo partían en pedazos, cada uno con su propia forma de
 * romperse.
 *
 * Las dos columnas que importan —cuántas muestras trae y cuántas pruebas siguen
 * abiertas— las cuenta el servidor en la MISMA consulta del listado. Acá no se
 * pide nada por fila.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Button, Card, Checkbox, DatePicker, Input, Select, SelectOption, Space, Tag, Tooltip,
} from 'ant-design-vue';
import {
    ClearOutlined, FilterOutlined, InboxOutlined, PlusOutlined, SaveOutlined,
    SearchOutlined, StarFilled, StarOutlined, ThunderboltFilled,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import InlineFilterBuilder from '@/Components/Common/InlineFilterBuilder.vue';
import SavedViews from '@/Components/Common/SavedViews.vue';
import ReceptionStatusTag from '@/Components/Receptions/ReceptionStatusTag.vue';
import ReceptionsBulkBar from '@/Components/Receptions/ReceptionsBulkBar.vue';
import ReceptionsBulkDeleteModal from '@/Components/Receptions/ReceptionsBulkDeleteModal.vue';
import { useAuth } from '@/Composables/useAuth';
import { useModuleBulkActions } from '@/Composables/useModuleBulkActions';
import { useModuleFavorites } from '@/Composables/useModuleFavorites';
import { useModuleSavedViews } from '@/Composables/useModuleSavedViews';
import { usePlanFeatures } from '@/Composables/usePlanFeatures';
import { useViewport } from '@/Composables/useViewport';
import { useI18n } from '@/Plugins/i18n';
import { plainDate } from './config/format';
import { receptionsTableColumns } from './config/columns';

defineOptions({ layout: AppLayout });

const props = defineProps({
    receptions:   { type: Object, required: true },
    filters:      { type: Object, default: () => ({}) },
    filterSchema: { type: Array,  default: () => [] },
    customers:    { type: Array,  default: () => [] },
    statuses:     { type: Array,  default: () => [] },
});

const { t } = useI18n();
const { can } = useAuth();
const { canUse: canUsePlanFeature } = usePlanFeatures();
const { isMobile: isMobileScreen } = useViewport(768);

const allColumns = computed(() => receptionsTableColumns(t));
const columns = allColumns;

/**
 * Cuántos días faltan para la fecha comprometida.
 *
 * Se cuenta en DÍAS DE CALENDARIO y no en horas: el laboratorio dice "me quedan
 * dos días", no "me quedan 41 horas", y con horas una entrega comprometida para
 * hoy a las 9 aparecería vencida a las 10 de la mañana.
 *
 * Una entrega CERRADA ya no tiene plazo: mostrarle "-14" a algo que se entregó
 * es ruido rojo en la mitad del listado.
 */
const diasRestantes = (record) => {
    if (!record.due_at || record.status === 'closed') return null;

    const hoy = new Date();
    const fin = new Date(record.due_at);
    const dia = 24 * 60 * 60 * 1000;

    return Math.round(
        (Date.UTC(fin.getFullYear(), fin.getMonth(), fin.getDate())
            - Date.UTC(hoy.getFullYear(), hoy.getMonth(), hoy.getDate())) / dia,
    );
};

/** Vencido en rojo, los tres últimos días en ámbar, el resto sin color. */
const colorDePlazo = (dias) => {
    if (dias < 0) return 'red';
    if (dias <= 3) return 'orange';

    return 'default';
};

// ── Filtros rápidos (un solo objeto: lo serializan las vistas guardadas) ──
const emptyFilters = () => ({
    status: null, customer: null, urgent: false, sample: '',
    from: null, to: null, only_favorites: false,
});

const hydrateFilters = (src = {}) => ({
    status:   src.status ?? null,
    customer: src.customer ?? null,
    urgent:   !!src.urgent,
    sample:   src.sample ?? '',
    from:     src.from ?? null,
    to:       src.to ?? null,
    only_favorites: !!src.only_favorites,
});

const filters = ref(hydrateFilters(props.filters));

// El RangePicker necesita [from, to] como un solo valor.
const dateRange = computed({
    get: () => (filters.value.from || filters.value.to
        ? [filters.value.from ?? null, filters.value.to ?? null]
        : null),
    set: (v) => {
        filters.value.from = v?.[0] || null;
        filters.value.to   = v?.[1] || null;
    },
});

const loading = ref(false);

const hasFilters = computed(() => !!(
    filters.value.status || filters.value.customer || filters.value.urgent
    || filters.value.sample || filters.value.from || filters.value.to
));

// ── Filtros avanzados (builder inline contra filterSchema del backend) ───
const advancedWhere = ref(Array.isArray(props.filters?.advanced_where) ? props.filters.advanced_where : []);
const savedViewsRef = ref(null);
const builderRef = ref(null);
const showFilters = ref(advancedWhere.value.length > 0);
const isFilterComplete = (c) => {
    if (!c || !c.field || !c.op) return false;
    if (Array.isArray(c.value)) return c.value.length > 0;
    return c.value !== '' && c.value !== null && c.value !== undefined;
};
const activeFilterCount = computed(() => advancedWhere.value.filter(isFilterComplete).length);
const toggleFilters = async () => {
    if (showFilters.value) {
        advancedWhere.value = advancedWhere.value.filter(isFilterComplete);
        showFilters.value = false;
        return;
    }
    showFilters.value = true;
    await nextTick();
    if (advancedWhere.value.length === 0) builderRef.value?.addRow();
};
watch(() => advancedWhere.value.length, (n) => {
    if (n === 0 && showFilters.value) showFilters.value = false;
});

const buildQueryData = () => ({
    status:         filters.value.status || undefined,
    customer:       filters.value.customer || undefined,
    urgent:         filters.value.urgent ? 1 : undefined,
    sample:         filters.value.sample?.trim() || undefined,
    from:           filters.value.from || undefined,
    to:             filters.value.to || undefined,
    only_favorites: filters.value.only_favorites ? 1 : undefined,
});

const apply = (extra = {}) => {
    const data = {
        ...buildQueryData(),
        direction: props.filters.direction,
        sort:      props.filters.sort,
        per_page:  props.filters.per_page,
        ...extra,
    };
    const cleaned = advancedWhere.value.filter(isFilterComplete);
    if (cleaned.length > 0) data.advanced_where = JSON.stringify(cleaned);

    router.get(
        route('lab_management.receptions.index'),
        data,
        {
            preserveScroll: true,
            preserveState:  true,
            onStart:  () => { loading.value = true; },
            onFinish: () => { loading.value = false; },
        },
    );
};

// Mutar los filtros SIN navegar (lo usan las vistas guardadas para hidratar
// el estado y navegar UNA sola vez ellas mismas).
let suspended = false;
const suspendReload = (fn) => {
    suspended = true;
    try { fn(); } finally { nextTick(() => { suspended = false; }); }
};

// El buscador de número de muestra se manda con retardo: se tipea entero antes
// de que valga la pena consultar, y cada tecla sería una consulta con LIKE.
// El resto de filtros comparte el retardo (300 ms): un solo camino de aplicar.
let searchTimer = null;
watch(filters, () => {
    if (suspended) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => apply({ page: 1 }), 300);
}, { deep: true });
onBeforeUnmount(() => clearTimeout(searchTimer));

const clearFilters = () => { filters.value = emptyFilters(); };

const clear = () => {
    advancedWhere.value = [];
    suspendReload(() => clearFilters());
    apply({ page: 1 });
};

// El builder inline aplica en vivo (solo cláusulas completas, con retardo).
let inlineTimer = null;
const applyInlineFilters = () => {
    clearTimeout(inlineTimer);
    inlineTimer = setTimeout(() => apply({ page: 1 }), 350);
};

// ── Vistas guardadas ─────────────────────────────────────────────────────
// Sin selector de columnas en este listado: se persisten todas las keys y el
// estado guardado queda compatible si algún día se agrega.
const visibleColumnKeys = ref(receptionsTableColumns((k) => k).map((c) => c.key));

const applySavedViewState = (clauses, meta) => {
    const data = { ...buildQueryData(), sort: meta.sort, direction: meta.direction, per_page: meta.perPage };
    if (clauses.length > 0) data.advanced_where = JSON.stringify(clauses);
    router.get(route('lab_management.receptions.index'), data, {
        preserveScroll: true,
        preserveState: true,
        onStart:  () => { loading.value = true; },
        onFinish: () => { loading.value = false; },
    });
};

const { currentViewState, applySavedState } = useModuleSavedViews({
    filters,
    visibleColumnKeys,
    allColumns,
    serverFilters: props.filters,
    serialize:     (f) => ({ ...f }),
    deserialize:   (s) => hydrateFilters(s ?? {}),
    clearFilters,
    reload: (extra = {}) => apply(extra),
    advancedWhere,
    applyWithAdvanced: applySavedViewState,
    suspendReload,
    defaults: { sort: 'received_at', direction: 'desc', perPage: 25 },
});

// ── Favoritos ────────────────────────────────────────────────────────────
const { submitting: favoriteSubmitting, toggle: toggleFavorite } = useModuleFavorites('receptions', 'receptions');

const onlyFavorites = computed(() => !!filters.value.only_favorites);
const toggleOnlyFavorites = () => {
    const next = !onlyFavorites.value;
    suspendReload(() => {
        clearFilters();
        filters.value.only_favorites = next;
    });
    advancedWhere.value = [];
    applySavedViewState([], {
        sort:      props.filters.sort,
        direction: props.filters.direction,
        perPage:   props.filters.per_page,
    });
};

// ── Baja masiva ──────────────────────────────────────────────────────────
// Las bloqueadas por candado no se seleccionan; las que tienen informes
// EMITIDOS las salta el servidor (contarlo acá costaría una consulta más por
// fila del listado).
const {
    selectedRowKeys, rowSelection, clearSelection,
    bulkOpen, bulkReason, bulkSubmitting, bulkError,
    openBulkDelete, confirmBulkDelete,
} = useModuleBulkActions({
    bulkDeleteRoute: 'lab_management.receptions.bulk_delete',
    resourceLabel:   t('receptions.records'),
    rowDisabled:     (r) => !!(r.is_locked ?? r.locked_at),
});

// ── Paginación y orden ───────────────────────────────────────────────────
const pagination = computed(() => ({
    current:  props.receptions.current_page,
    pageSize: props.receptions.per_page,
    total:    props.receptions.total,
    showSizeChanger: true,
    pageSizeOptions: ['10', '25', '50', '100'],
}));

/**
 * El orden viaja completo (columna + dirección) y el servidor lo valida por
 * lista blanca (`received_at`, `due_at`, `status`): lo que no está en la
 * lista cae al orden por fecha de recepción.
 */
const onTableChange = (page, _filters, sorter) => {
    const sort = sorter?.field || props.filters.sort;
    const direction = sorter?.order === 'ascend' ? 'asc'
        : sorter?.order === 'descend' ? 'desc'
            : props.filters.direction;

    apply({ page: page.current, per_page: page.pageSize, sort, direction });
};

const openReception = (record) => router.visit(
    route('lab_management.receptions.show', record.slug),
);

/**
 * Los cuatro chequeos de avance de una recepción, como los cuatro iconos del
 * sistema anterior (Series · Trabajos · Datos · Informes) pero derivados en la
 * consulta del listado, nunca cacheados:
 *
 *   equipo   → muestras sin equipo enlazado. OJO: esto NO es un error por sí
 *              solo. El laboratorio confirmó (2026-07-30) que el número de
 *              muestra se asocia al transformador DESPUÉS, y que hay muestras
 *              que no vienen de ningún equipo —un cilindro de aceite nuevo, un
 *              envase suelto— y se informan igual. El chip avisa de lo que
 *              falta ENLAZAR, no de un ensayo perdido: su resultado se escribe
 *              y sale en el informe de cualquier manera.
 *   pruebas  → muestras sin ningún ensayo pedido (un frasco que nadie procesa)
 *   datos    → ensayos pedidos que siguen sin cargarse o a medio cargar
 *   informes → muestras que todavía no tienen su informe emitido
 *
 * Cada chip dice CUÁNTAS faltan; en verde, esa parte está completa.
 */
const progressChips = (record) => {
    const total = record.samples_count ?? 0;

    return [
        { key: 'equipment', pending: record.unlinked_count ?? 0 },
        { key: 'tests',     pending: record.untested_count ?? 0 },
        { key: 'data',      pending: record.outstanding_count ?? 0 },
        { key: 'reports',   pending: total - (record.reported_count ?? 0) },
    ];
};
</script>

<template>
    <Head :title="$t('receptions.index_title')" />

    <div class="sap-index">
        <div class="mi-title">
            <div class="page-header__title">
                <div class="page-header__icon"><InboxOutlined /></div>
                <div class="page-header__heading">
                    <h1>{{ $t('receptions.index_title') }}</h1>
                    <p>{{ $t('receptions.index_subtitle') }}</p>
                </div>
            </div>
            <div class="mi-title__actions">
                <Tooltip v-if="can('receptions.create')" :title="$t('receptions.new')">
                    <Link :href="route('lab_management.receptions.create')">
                        <Button type="primary">
                            <PlusOutlined /> {{ $t('receptions.new') }}
                        </Button>
                    </Link>
                </Tooltip>
            </div>
        </div>

        <!-- Vistas guardadas + el toggle de "solo favoritos" (estándar de los
             índices; gateado por plan como en el resto de los módulos). -->
        <div v-if="canUsePlanFeature('saved_views')" class="mi-viewsbar rc-viewsbar">
            <SavedViews
                ref="savedViewsRef"
                layout="bar"
                variant="tabs"
                module="receptions"
                :show-add="false"
                :current-state="currentViewState"
                :show-favorites="true"
                :favorites-active="onlyFavorites"
                @apply="applySavedState"
                @default-loaded="applySavedState"
                @toggle-favorites="toggleOnlyFavorites"
            />
        </div>

        <Card class="rc-filters" :body-style="{ padding: '14px 16px' }">
            <Space :size="10" wrap>
                <!-- El número de muestra: lo que el cliente cita por teléfono. -->
                <Input
                    v-model:value="filters.sample"
                    allow-clear
                    class="rc-filters__search"
                    :placeholder="$t('receptions.search_sample')"
                >
                    <template #prefix><SearchOutlined /></template>
                </Input>

                <Select
                    v-model:value="filters.status"
                    allow-clear
                    style="min-width: 170px"
                    :placeholder="$t('receptions.status')"
                >
                    <SelectOption v-for="status in statuses" :key="status" :value="status">
                        {{ $t(`receptions.status_${status}`) }}
                    </SelectOption>
                </Select>

                <Select
                    v-model:value="filters.customer"
                    allow-clear
                    show-search
                    option-filter-prop="label"
                    style="min-width: 230px"
                    :placeholder="$t('receptions.customer')"
                >
                    <SelectOption
                        v-for="customer in customers"
                        :key="customer.slug"
                        :value="customer.slug"
                        :label="customer.name"
                    >
                        {{ customer.name }}
                    </SelectOption>
                </Select>

                <DatePicker.RangePicker
                    v-model:value="dateRange"
                    value-format="YYYY-MM-DD"
                    style="min-width: 260px"
                    :placeholder="[$t('global.from'), $t('global.to')]"
                />

                <Checkbox v-model:checked="filters.urgent">{{ $t('receptions.urgent_only') }}</Checkbox>

                <!-- El builder de filtros avanzados (contra filterSchema). -->
                <Tooltip :title="$t('global.filters')">
                    <Button
                        class="mi-iconbtn"
                        :class="{ 'mi-iconbtn--active': showFilters || activeFilterCount > 0 }"
                        @click="toggleFilters"
                    >
                        <FilterOutlined />
                        <span v-if="activeFilterCount > 0" class="mi-iconbtn__count">{{ activeFilterCount }}</span>
                    </Button>
                </Tooltip>

                <Button v-if="hasFilters || activeFilterCount > 0" type="link" @click="clear">
                    <ClearOutlined /> {{ $t('global.clear_filters') }}
                </Button>
            </Space>
        </Card>

        <div v-if="showFilters" class="mi-builder mi-builder--table">
            <InlineFilterBuilder
                ref="builderRef"
                v-model="advancedWhere"
                :schema="props.filterSchema"
                show-conjunction
                @change="applyInlineFilters"
            >
                <template #actions>
                    <Button v-if="hasFilters || activeFilterCount > 0" type="link" class="bidx-clear" @click="clear">
                        <ClearOutlined /> {{ $t('global.clear_filters') }}
                    </Button>
                    <Button v-if="canUsePlanFeature('saved_views')" type="link" class="bidx-savefilter" @click="savedViewsRef?.openSave()">
                        <SaveOutlined /> {{ $t('global.save_filter') }}
                    </Button>
                </template>
            </InlineFilterBuilder>
        </div>

        <Card :body-style="{ padding: 0 }" class="grid-card">
            <ResponsiveTable
                :columns="columns"
                :data-source="receptions.data"
                :pagination="pagination"
                :loading="loading"
                :row-selection="can('receptions.delete') ? rowSelection : null"
                :scroll="{ x: 'max-content' }"
                row-key="id"
                @change="onTableChange"
                @row-click="openReception"
            >
                <template #empty>
                    <div class="rc-empty">{{ $t('receptions.empty_hint') }}</div>
                </template>

                <template #bodyCell="{ column, record, text }">
                    <template v-if="column.key === 'favorite'">
                        <button
                            type="button"
                            class="fav-btn"
                            :class="{ 'fav-btn--on': record.is_favorite }"
                            :disabled="favoriteSubmitting === record.id"
                            @click.stop="toggleFavorite(record)"
                        >
                            <StarFilled v-if="record.is_favorite" />
                            <StarOutlined v-else />
                        </button>
                    </template>

                    <template v-else-if="column.key === 'received_at'">
                        {{ plainDate(record.received_at) || '—' }}
                    </template>

                    <template v-else-if="column.key === 'code'">
                        <Link
                            :href="route('lab_management.receptions.show', record.slug)"
                            class="rc-link"
                        >
                            {{ record.code || `#${record.id}` }}
                        </Link>
                    </template>

                    <template v-else-if="column.key === 'due_at'">
                        {{ plainDate(record.due_at) || '—' }}
                    </template>

                    <!-- DÍAS RESTANTES. El color es el semáforo del sistema
                         anterior: sin fecha comprometida no hay plazo que
                         mostrar; vencido va en rojo; los tres últimos días en
                         ámbar. Es la columna por la que se decide qué entrega
                         se trabaja hoy. -->
                    <template v-else-if="column.key === 'days_left'">
                        <Tag
                            v-if="diasRestantes(record) !== null"
                            :bordered="false"
                            :color="colorDePlazo(diasRestantes(record))"
                        >{{ diasRestantes(record) }}</Tag>
                        <span v-else>—</span>
                    </template>

                    <!-- El Nº de orden de servicio llega días después de la
                         muestra. Marcarlo PENDIENTE en rojo convierte la
                         columna en una lista de trabajo, que es para lo que la
                         usaba el laboratorio. -->
                    <template v-else-if="column.key === 'service_order'">
                        <span v-if="record.service_order">{{ record.service_order }}</span>
                        <Tag v-else :bordered="false" color="red">
                            {{ $t('receptions.service_order_pending') }}
                        </Tag>
                    </template>

                    <template v-else-if="column.key === 'sampler'">
                        {{ record.sampler?.name ?? record.sampler_name ?? '—' }}
                    </template>

                    <template v-else-if="column.key === 'customer'">
                        {{ record.customer?.name ?? '—' }}
                    </template>

                    <template v-else-if="column.key === 'samples_count'">
                        {{ record.samples_count ?? 0 }}
                    </template>

                    <!-- El semáforo de avance por fila. Verde = esa parte está
                         completa; ámbar = cuántas faltan. Sin muestras no hay
                         nada que semaforizar. -->
                    <template v-else-if="column.key === 'progress'">
                        <span v-if="!(record.samples_count > 0)">—</span>
                        <span v-else class="rc-progress">
                            <Tooltip
                                v-for="chip in progressChips(record)"
                                :key="chip.key"
                                :title="chip.pending > 0
                                    ? $t(`receptions.prog_${chip.key}_pending`, { count: chip.pending })
                                    : $t(`receptions.prog_${chip.key}_done`)"
                            >
                                <span
                                    class="rc-chip"
                                    :class="chip.pending > 0 ? 'rc-chip--pending' : 'rc-chip--done'"
                                >
                                    {{ $t(`receptions.prog_${chip.key}`) }}
                                    <template v-if="chip.pending > 0"> {{ chip.pending }}</template>
                                    <template v-else> ✓</template>
                                </span>
                            </Tooltip>
                        </span>
                    </template>

                    <template v-else-if="column.key === 'status'">
                        <ReceptionStatusTag :status="record.status" />
                    </template>

                    <template v-else-if="column.key === 'is_urgent'">
                        <Tag v-if="record.is_urgent" color="red" :bordered="false">
                            <ThunderboltFilled /> {{ $t('receptions.is_urgent') }}
                        </Tag>
                        <span v-else>—</span>
                    </template>

                    <template v-else>{{ text ?? '—' }}</template>
                </template>
            </ResponsiveTable>
        </Card>

        <ReceptionsBulkBar
            v-if="selectedRowKeys.length > 0"
            :count="selectedRowKeys.length"
            :is-mobile="isMobileScreen"
            :can-delete="can('receptions.delete')"
            @cancel="clearSelection"
            @delete="openBulkDelete"
        />

        <ReceptionsBulkDeleteModal
            v-model:open="bulkOpen"
            v-model:reason="bulkReason"
            :count="selectedRowKeys.length"
            :submitting="bulkSubmitting"
            :error-msg="bulkError"
            @confirm="confirmBulkDelete"
        />
    </div>
</template>

<style scoped>
.rc-viewsbar { margin-bottom: 10px; }
.rc-filters { margin-bottom: 12px; }
.rc-filters__search { min-width: 220px; }

/* La estrella de favorito (mismo trato que el resto de los índices). */
.fav-btn {
    background: transparent;
    border: 0;
    cursor: pointer;
    color: var(--color-icon-mute);
    font-size: 1.1rem;
    padding: 4px;
    line-height: 1;
    transition: color 0.12s ease, transform 0.12s ease;
}
.fav-btn:hover { transform: scale(1.15); }
.fav-btn:disabled { cursor: wait; opacity: 0.6; }
.fav-btn :deep(svg)       { fill: var(--color-icon-mute) !important; }
.fav-btn:hover :deep(svg) { fill: var(--color-warning) !important; }
.fav-btn--on :deep(svg)   { fill: var(--color-warning) !important; }
.rc-link { font-weight: 600; }

/* El semáforo de avance: chips compactos, legibles de un vistazo. El texto no
   se pinta solo de color (el número acompaña) para que el daltónico también
   lo lea. */
.rc-progress { display: inline-flex; gap: 4px; flex-wrap: wrap; }
.rc-chip {
    display: inline-block;
    padding: 1px 7px;
    border-radius: 10px;
    font-size: 0.72rem;
    font-weight: 600;
    line-height: 1.5;
    white-space: nowrap;
}
.rc-chip--done    { background: rgba(29, 112, 68, 0.12);  color: #1D7044; }
.rc-chip--pending { background: rgba(212, 112, 14, 0.14); color: #b45309; }
.rc-empty { padding: 40px 16px; text-align: center; color: var(--color-text-muted); }
</style>
