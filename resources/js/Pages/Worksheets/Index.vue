<script setup>
/**
 * Listado de hojas de trabajo.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL FILTRO DE FECHA SE VE                                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sistema viejo aplicaba en silencio un "últimos tres meses" cuando no se
 * mandaba fecha. Los ensayos más viejos no aparecían y nada en la pantalla lo
 * decía: quien buscaba una hoja del año pasado concluía que se había perdido.
 *
 * Acá el rango vacío significa TODO, y la pantalla lo dice con todas las
 * letras junto al filtro. Un filtro que no se ve es un filtro que miente.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA FRANJA DE RESULTADOS ES DEL ESTÁNDAR, NO UN ADORNO                    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Entre los filtros y la tabla va la franja Fiori (`mi-tabletoolbar`): a la
 * izquierda CUÁNTAS hojas está mirando —y cuántas hay en total si un filtro
 * recortó—, a la derecha las herramientas que operan sobre esa lista. Es el
 * mismo bloque que el índice de Marcas, y este listado era el único que no lo
 * tenía: sus botones quedaban desperdigados entre la cabecera y los filtros, y
 * no había forma de saber cuánto se estaba viendo.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA FILA NO ES UN ENLACE                                                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Se entra a la hoja por el botón Ver o por la fecha, que están para eso. Con
 * toda la fila navegable, marcar un favorito, tildar una casilla o arrastrar
 * para copiar un dato terminaba abriendo la hoja.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Button, Card, DatePicker, Dropdown, Menu, MenuItem, Select, SelectOptGroup,
    SelectOption, Space, Tag, Tooltip,
} from 'ant-design-vue';
import {
    AppstoreOutlined, AudioOutlined, BarsOutlined, ClearOutlined, CloseOutlined,
    ControlOutlined, FilterOutlined, InfoCircleOutlined, PlusOutlined,
    ProfileOutlined, SaveOutlined, SearchOutlined, SortAscendingOutlined,
    SortDescendingOutlined, StarFilled, StarOutlined, TableOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import ColumnSelector from '@/Components/Common/ColumnSelector.vue';
import InlineFilterBuilder from '@/Components/Common/InlineFilterBuilder.vue';
import SavedViews from '@/Components/Common/SavedViews.vue';
import WorksheetStatusTag from '@/Components/Worksheets/WorksheetStatusTag.vue';
import WorksheetsActionsCell from '@/Components/Worksheets/WorksheetsActionsCell.vue';
import WorksheetsBulkBar from '@/Components/Worksheets/WorksheetsBulkBar.vue';
import WorksheetsBulkDeleteModal from '@/Components/Worksheets/WorksheetsBulkDeleteModal.vue';
import { useAuth } from '@/Composables/useAuth';
import { useColumnPreferences } from '@/Composables/useColumnPreferences';
import { useModuleBulkActions } from '@/Composables/useModuleBulkActions';
import { useModuleFavorites } from '@/Composables/useModuleFavorites';
import { useModuleListMeta } from '@/Composables/useModuleListMeta';
import { useModuleSavedViews } from '@/Composables/useModuleSavedViews';
import { useModuleUndoToast } from '@/Composables/useModuleUndoToast';
import { usePlanFeatures } from '@/Composables/usePlanFeatures';
import { useViewport } from '@/Composables/useViewport';
import { useVoiceSearch } from '@/Composables/useVoiceSearch';
import { useI18n } from '@/Plugins/i18n';
import { groupTests, isGrouped } from '@/Utils/testGroups';
import { plainDate } from './config/format';
import { worksheetsTableColumns } from './config/columns';

defineOptions({ layout: AppLayout });

const props = defineProps({
    worksheets:   { type: Object, required: true },
    tests:        { type: Array,  default: () => [] },
    analysts:     { type: Array,  default: () => [] },
    statuses:     { type: Array,  default: () => [] },
    filters:      { type: Object, default: () => ({}) },
    filterSchema: { type: Array,  default: () => [] },
});

const { t } = useI18n();
const { can, isSuper, hasRole } = useAuth();
const { canUse: canUsePlanFeature } = usePlanFeatures();
const { isMobile: isMobileScreen } = useViewport(768);

const isAdmin = computed(() => hasRole('admin'));

const allColumns = computed(() => worksheetsTableColumns(t, isMobileScreen.value));
const { visibleColumnKeys, columns } = useColumnPreferences(allColumns);
const colSel = ref(null);

/**
 * Las pruebas del filtro, por grupo (Físico Químico · Cromatografías · Otros).
 * Son 29 en una lista plana: sin los encabezados hay que leerlas todas para
 * encontrar una. El orden de los grupos y el de las pruebas dentro de cada uno
 * salen del dato, no de una lista escrita acá.
 */
const testGroups = computed(() => groupTests(props.tests, t('worksheets.group_none')));

/** Sin grupos que separar se dibuja la lista de siempre, sin encabezados. */
const showGroups = computed(() => isGrouped(testGroups.value));

// ── Filtros rápidos (un solo objeto: lo serializan las vistas guardadas) ──
const emptyFilters = () => ({
    test_definition: null, status: null, analyst: null, sample: '',
    from: null, to: null, only_favorites: false,
});

const hydrateFilters = (src = {}) => ({
    test_definition: src.test_definition ?? null,
    status:          src.status ?? null,
    analyst:         src.analyst ?? null,
    sample:          src.sample ?? '',
    from:            src.from ?? null,
    to:              src.to ?? null,
    only_favorites:  !!src.only_favorites,
});

const filters = ref(hydrateFilters(props.filters));

// El buscador de la franja escribe en el MISMO filtro que ya viajaba por la
// URL: no hay dos búsquedas que puedan contradecirse.
const quickSearch = computed({
    get: () => filters.value.sample,
    set: (v) => { filters.value.sample = v; },
});
const { micSupported, listening, startVoiceSearch } = useVoiceSearch(quickSearch);

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
    filters.value.test_definition || filters.value.status || filters.value.analyst
    || filters.value.sample || filters.value.from || filters.value.to
));

/** Sin rango no se manda fecha, y sin fecha el servidor no acota nada. */
const noDateFilter = computed(() => !filters.value.from && !filters.value.to);

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
    test_definition: filters.value.test_definition || undefined,
    status:          filters.value.status || undefined,
    analyst:         filters.value.analyst || undefined,
    sample:          filters.value.sample?.trim() || undefined,
    from:            filters.value.from || undefined,
    to:              filters.value.to || undefined,
    only_favorites:  filters.value.only_favorites ? 1 : undefined,
});

const apply = (extra = {}) => {
    const data = {
        ...buildQueryData(),
        sort:      props.filters.sort,
        direction: props.filters.direction,
        per_page:  props.filters.per_page,
        ...extra,
    };
    const cleaned = advancedWhere.value.filter(isFilterComplete);
    if (cleaned.length > 0) data.advanced_where = JSON.stringify(cleaned);

    router.get(
        route('lab_management.worksheets.index'),
        data,
        {
            preserveScroll: true,
            preserveState:  true,
            onStart:  () => { loading.value = true; },
            onFinish: () => { loading.value = false; },
        },
    );
};

// Mutar los filtros SIN navegar (lo usan las vistas guardadas para hidratar el
// estado y navegar UNA sola vez ellas mismas).
let suspended = false;
const suspendReload = (fn) => {
    suspended = true;
    try { fn(); } finally { nextTick(() => { suspended = false; }); }
};

// El buscador de número de muestra se manda con retardo: se tipea entero antes
// de que valga la pena consultar, y cada tecla sería una consulta con LIKE. El
// resto de los filtros comparte el retardo: un solo camino de aplicar.
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
onBeforeUnmount(() => clearTimeout(inlineTimer));

// ── La franja: contador de resultados y realce de filas recientes ────────
const { isHighlighted, counterLabel } = useModuleListMeta({
    pagination: computed(() => props.worksheets),
    hasActiveFilters: computed(() => hasFilters.value || activeFilterCount.value > 0),
    t,
});

// ── Modo de vista (tabla · tarjetas · lista), recordado por navegador ────
const VIEW_KEY = 'worksheets.view_mode';
const viewMode = ref('table');
onMounted(() => {
    const guardado = localStorage.getItem(VIEW_KEY);
    if (guardado === 'cards' || guardado === 'table' || guardado === 'list') viewMode.value = guardado;
});
watch(viewMode, (v) => localStorage.setItem(VIEW_KEY, v));

const viewOptions = computed(() => [
    { value: 'table', label: t('global.view_table'),      icon: TableOutlined },
    { value: 'list',  label: t('global.view_list_short'), icon: BarsOutlined },
    { value: 'cards', label: t('global.view_cards'),      icon: AppstoreOutlined },
]);
const currentView = computed(() => viewOptions.value.find((o) => o.value === viewMode.value) ?? viewOptions.value[0]);
const setView = ({ key }) => { viewMode.value = key; };

// ── Orden ────────────────────────────────────────────────────────────────
// En tabla se ordena por la cabecera. En tarjetas y en móvil no hay cabecera
// que clickear, así que la franja ofrece el mismo orden como desplegable.
const sortOptions = computed(() => allColumns.value
    .filter((c) => c.sorter)
    .map((c) => ({ value: c.key, label: c.title })));
const currentSort = computed(() => props.filters.sort ?? 'run_date');
const currentDir  = computed(() => props.filters.direction ?? 'desc');
const currentSortLabel = computed(
    () => sortOptions.value.find((o) => o.value === currentSort.value)?.label ?? t('worksheets.run_date'),
);
const setSort = ({ key }) => {
    // Volver a elegir la misma columna invierte la dirección: es lo que hace
    // la cabecera de la tabla, y el desplegable no puede decir otra cosa.
    const direction = key === currentSort.value && currentDir.value === 'desc' ? 'asc' : 'desc';
    apply({ page: 1, sort: key, direction });
};

// ── Vistas guardadas ─────────────────────────────────────────────────────
const applySavedViewState = (clauses, meta) => {
    const data = { ...buildQueryData(), sort: meta.sort, direction: meta.direction, per_page: meta.perPage };
    if (clauses.length > 0) data.advanced_where = JSON.stringify(clauses);
    router.get(route('lab_management.worksheets.index'), data, {
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
    defaults: { sort: 'run_date', direction: 'desc', perPage: 25 },
});

// ── Favoritos ────────────────────────────────────────────────────────────
const { submitting: favoriteSubmitting, toggle: toggleFavorite } = useModuleFavorites('worksheets', 'worksheets');

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
// Las bloqueadas por candado no se seleccionan (el servidor también las salta).
const {
    selectedRowKeys, rowSelection, clearSelection,
    bulkOpen, bulkReason, bulkSubmitting, bulkError,
    openBulkDelete, confirmBulkDelete,
} = useModuleBulkActions({
    bulkDeleteRoute: 'lab_management.worksheets.bulk_delete',
    resourceLabel:   t('worksheets.records'),
    rowDisabled:     (r) => !!(r.is_locked ?? r.locked_at),
});

// ── "Eliminado. Deshacer (60 s)" ─────────────────────────────────────────
// Dar de baja una hoja retira sus resultados del informe, marca sus puntos de
// control de calidad y devuelve sus ensayos a la cola. Sin este aviso, el
// error de un clic solo se arreglaba entrando a rehacer la carga entera.
useModuleUndoToast('lab_management.worksheets.undo_last_delete');

// ── Paginación y orden de la tabla ───────────────────────────────────────
const pagination = computed(() => ({
    current:  props.worksheets.current_page,
    pageSize: props.worksheets.per_page,
    total:    props.worksheets.total,
    showSizeChanger: true,
    pageSizeOptions: ['10', '25', '50', '100'],
}));

/**
 * El orden viaja por la CLAVE de la columna (`columnKey`), no por su
 * `dataIndex`: las columnas que salen de una relación lo tienen como arreglo
 * (`['analyst','name']`) y eso no es un nombre que el servidor pueda validar.
 */
const onTableChange = (page, _filters, sorter) => {
    const sort = sorter?.columnKey || sorter?.field || props.filters.sort;
    const direction = sorter?.order === 'ascend' ? 'asc'
        : sorter?.order === 'descend' ? 'desc'
            : props.filters.direction;

    apply({ page: page.current, per_page: page.pageSize, sort, direction });
};
</script>

<template>
    <Head :title="$t('worksheets.title')" />

    <div class="sap-index">
        <div class="mi-title">
            <div class="page-header__title">
                <div class="page-header__icon"><ProfileOutlined /></div>
                <div class="page-header__heading">
                    <h1>{{ $t('worksheets.title') }}</h1>
                    <p>{{ $t('worksheets.intro') }}</p>
                </div>
            </div>
        </div>

        <!-- Vistas rápidas + guardadas, y el interruptor de "solo favoritas". -->
        <div class="mi-console mi-console--v2">
            <div v-if="canUsePlanFeature('saved_views')" class="mi-viewsbar">
                <SavedViews
                    ref="savedViewsRef"
                    layout="bar"
                    variant="tabs"
                    module="worksheets"
                    :show-add="false"
                    :current-state="currentViewState"
                    :show-favorites="true"
                    :favorites-active="onlyFavorites"
                    @apply="applySavedState"
                    @default-loaded="applySavedState"
                    @toggle-favorites="toggleOnlyFavorites"
                />
            </div>

            <!-- ColumnSelector montado oculto: solo expone open() al engranaje. -->
            <span class="mi-colsel-host" aria-hidden="true">
                <ColumnSelector
                    ref="colSel"
                    :columns="allColumns"
                    v-model="visibleColumnKeys"
                    storage-key="worksheets"
                />
            </span>
        </div>

        <Card class="ws-filters" :body-style="{ padding: '14px 16px' }">
            <Space :size="10" wrap>
                <Select
                    v-model:value="filters.test_definition"
                    allow-clear
                    show-search
                    option-filter-prop="label"
                    style="min-width: 220px"
                    :placeholder="$t('worksheets.test_definition')"
                >
                    <!-- Agrupadas por familia de ensayo. La búsqueda sigue
                         filtrando por el nombre de la prueba: el encabezado
                         ordena la lectura, no obliga a elegir grupo primero. -->
                    <template v-if="showGroups">
                        <SelectOptGroup v-for="group in testGroups" :key="group.key" :label="group.label">
                            <SelectOption
                                v-for="test in group.tests"
                                :key="test.slug"
                                :value="test.slug"
                                :label="test.name"
                            >
                                {{ test.name }}
                            </SelectOption>
                        </SelectOptGroup>
                    </template>

                    <template v-else>
                        <SelectOption
                            v-for="test in tests"
                            :key="test.slug"
                            :value="test.slug"
                            :label="test.name"
                        >
                            {{ test.name }}
                        </SelectOption>
                    </template>
                </Select>

                <Select
                    v-model:value="filters.status"
                    allow-clear
                    style="min-width: 180px"
                    :placeholder="$t('worksheets.status')"
                >
                    <SelectOption v-for="status in statuses" :key="status" :value="status">
                        {{ $t(`worksheets.state.${status}`) }}
                    </SelectOption>
                </Select>

                <!-- Por analista: es la pregunta "qué corrí yo esta semana", y
                     era la única de las tres columnas que no se podía filtrar.
                     La lista son los analistas QUE TIENEN hojas, no el padrón
                     entero de usuarios. -->
                <Select
                    v-model:value="filters.analyst"
                    allow-clear
                    show-search
                    option-filter-prop="label"
                    style="min-width: 200px"
                    :placeholder="$t('worksheets.analyst_filter')"
                >
                    <SelectOption
                        v-for="user in analysts"
                        :key="user.id"
                        :value="user.id"
                        :label="user.name"
                    >
                        {{ user.name }}
                    </SelectOption>
                </Select>

                <DatePicker.RangePicker
                    v-model:value="dateRange"
                    value-format="YYYY-MM-DD"
                    style="min-width: 260px"
                    :placeholder="[$t('global.from'), $t('global.to')]"
                />

                <!-- El estado del filtro de fecha, escrito. Sin rango se listan
                     TODAS las fechas: no hay ningún recorte por detrás. -->
                <Tag :bordered="false" class="ws-filters__scope">
                    <InfoCircleOutlined />
                    {{ $t('worksheets.run_date') }}:
                    <strong v-if="noDateFilter">{{ $t('global.all') }}</strong>
                    <strong v-else>
                        {{ plainDate(filters.from) || $t('global.all') }}
                        —
                        {{ plainDate(filters.to) || $t('global.all') }}
                    </strong>
                </Tag>

                <Button v-if="hasFilters || activeFilterCount > 0" type="link" @click="clear">
                    <ClearOutlined /> {{ $t('global.clear_filters') }}
                </Button>
            </Space>
        </Card>

        <!-- Franja Fiori: cuántas hojas a la izquierda, herramientas a la
             derecha. Va pegada a la tabla porque es de la tabla. -->
        <div class="mi-tabletoolbar">
            <div class="mi-tabletoolbar__left">
                <span class="mi-toolbar-count">{{ counterLabel }}</span>
            </div>
            <div class="mi-tabletoolbar__right">
                <!-- El número de muestra: lo que el cliente cita por teléfono.
                     Busca contra la COLUMNA de la fila de bancada, no partiendo
                     el texto. -->
                <label class="mi-bar mi-bar--toolbar" :class="{ 'is-active': quickSearch }">
                    <SearchOutlined class="mi-bar__icon" />
                    <input
                        v-model="quickSearch"
                        class="mi-bar__input"
                        :placeholder="$t('worksheets.search_sample')"
                        autocomplete="off"
                        spellcheck="false"
                        type="text"
                    />
                    <button v-if="quickSearch" type="button" class="mi-bar__act" :title="$t('global.clear')" @click="quickSearch = ''">
                        <CloseOutlined />
                    </button>
                    <Tooltip v-if="micSupported" :title="$t('global.voice_search')">
                        <button
                            type="button"
                            class="mi-bar__act mi-bar__mic"
                            :class="{ 'mi-bar__mic--on': listening }"
                            @click="startVoiceSearch"
                        >
                            <AudioOutlined />
                        </button>
                    </Tooltip>
                </label>

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

                <!-- En tarjetas y en móvil no hay cabecera que clickear: el
                     mismo orden, como desplegable. -->
                <span v-if="viewMode !== 'table' || isMobileScreen" class="mi-sortgroup">
                    <Dropdown :trigger="['click']" placement="bottomRight">
                        <Tooltip :title="$t('global.sort_by_hint')">
                            <Button class="sort-btn">
                                <SortAscendingOutlined v-if="currentDir === 'asc'" />
                                <SortDescendingOutlined v-else />
                                <span class="sort-btn__label">{{ $t('global.sort_by') }}: {{ currentSortLabel }}</span>
                            </Button>
                        </Tooltip>
                        <template #overlay>
                            <Menu :selected-keys="[currentSort]" @click="setSort">
                                <MenuItem v-for="o in sortOptions" :key="o.value">{{ o.label }}</MenuItem>
                            </Menu>
                        </template>
                    </Dropdown>
                </span>

                <Tooltip v-if="viewMode === 'table'" :title="$t('global.columns')">
                    <Button class="mi-iconbtn" @click="colSel?.open()"><ControlOutlined /></Button>
                </Tooltip>

                <Dropdown :trigger="['click']" placement="bottomRight">
                    <Tooltip :title="$t('global.view_mode_hint')">
                        <Button class="sort-btn">
                            <component :is="currentView.icon" />
                            <span class="sort-btn__label">{{ $t('global.view_mode') }}: {{ currentView.label }}</span>
                        </Button>
                    </Tooltip>
                    <template #overlay>
                        <Menu :selected-keys="[viewMode]" @click="setView">
                            <MenuItem v-for="o in viewOptions" :key="o.value">
                                <component :is="o.icon" /> {{ o.label }}
                            </MenuItem>
                        </Menu>
                    </template>
                </Dropdown>

                <Tooltip v-if="can('worksheets.create')" :title="$t('worksheets.create')">
                    <Link :href="route('lab_management.worksheets.create')">
                        <Button type="primary" class="mi-iconbtn mi-create-btn" :aria-label="$t('worksheets.create')">
                            <PlusOutlined />
                        </Button>
                    </Link>
                </Tooltip>
            </div>
        </div>

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
            <!-- Sin `@row-click`: se entra por Ver o por la fecha. -->
            <ResponsiveTable
                :columns="columns"
                :data-source="worksheets.data"
                :pagination="pagination"
                :loading="loading"
                :row-selection="can('worksheets.delete') ? rowSelection : null"
                :scroll="{ x: 'max-content' }"
                :view="viewMode"
                :row-class-name="(r) => (isHighlighted(r.id) ? 'row-highlight' : '')"
                row-key="id"
                @change="onTableChange"
            >
                <template #empty>
                    <div class="ws-empty">{{ $t('worksheets.empty') }}</div>
                </template>

                <template #bodyCell="{ column, record, text, isMobile, compact }">
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

                    <template v-else-if="column.key === 'run_date'">
                        <Link :href="route('lab_management.worksheets.show', record.slug)" class="ws-link">
                            {{ plainDate(record.run_date) }}
                        </Link>
                    </template>

                    <template v-else-if="column.key === 'definition'">
                        {{ record.definition?.name ?? '—' }}
                    </template>

                    <template v-else-if="column.key === 'analyst'">
                        {{ record.analyst?.name ?? '—' }}
                    </template>

                    <template v-else-if="column.key === 'status'">
                        <WorksheetStatusTag :status="record.status" />
                    </template>

                    <template v-else-if="column.key === 'validator'">
                        {{ record.validator?.name ?? '—' }}
                    </template>

                    <WorksheetsActionsCell
                        v-else-if="column.key === 'actions'"
                        :record="record"
                        :is-mobile="isMobile"
                        :compact="compact"
                        :is-super="isSuper"
                        :is-admin="isAdmin"
                        :can-edit="can('worksheets.edit')"
                        :can-delete="can('worksheets.delete')"
                    />

                    <template v-else>{{ text ?? '—' }}</template>
                </template>
            </ResponsiveTable>
        </Card>

        <WorksheetsBulkBar
            v-if="selectedRowKeys.length > 0"
            :count="selectedRowKeys.length"
            :is-mobile="isMobileScreen"
            :can-delete="can('worksheets.delete')"
            @cancel="clearSelection"
            @delete="openBulkDelete"
        />

        <WorksheetsBulkDeleteModal
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
.ws-filters { margin-bottom: 12px; }
.ws-filters__scope { color: var(--color-text-muted); }
.ws-filters__scope strong { color: var(--color-text); }
.ws-link { font-weight: 600; }
.ws-empty { padding: 40px 16px; text-align: center; color: var(--color-text-muted); }

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
</style>
