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
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Button, Card, Checkbox, DatePicker, Input, Select, SelectOption, Space, Tag, Tooltip,
} from 'ant-design-vue';
import {
    ClearOutlined, InboxOutlined, PlusOutlined, SearchOutlined, ThunderboltFilled,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import ReceptionStatusTag from '@/Components/Receptions/ReceptionStatusTag.vue';
import { useAuth } from '@/Composables/useAuth';
import { useI18n } from '@/Plugins/i18n';
import { plainDate } from './config/format';
import { receptionsTableColumns } from './config/columns';

defineOptions({ layout: AppLayout });

const props = defineProps({
    receptions: { type: Object, required: true },
    filters:    { type: Object, default: () => ({}) },
    customers:  { type: Array,  default: () => [] },
    statuses:   { type: Array,  default: () => [] },
});

const { t } = useI18n();
const { can } = useAuth();

const columns = computed(() => receptionsTableColumns(t));

// ── Filtros ──────────────────────────────────────────────────────────────
const statusFilter   = ref(props.filters.status ?? null);
const customerFilter = ref(props.filters.customer ?? null);
const urgentFilter   = ref(!!props.filters.urgent);
const sampleFilter   = ref(props.filters.sample ?? '');
const dateRange      = ref(
    props.filters.from || props.filters.to
        ? [props.filters.from ?? null, props.filters.to ?? null]
        : null,
);

const loading = ref(false);

const hasFilters = computed(() => !!(
    statusFilter.value || customerFilter.value || urgentFilter.value
    || sampleFilter.value || dateRange.value?.[0] || dateRange.value?.[1]
));

const apply = (extra = {}) => {
    router.get(
        route('lab_management.receptions.index'),
        {
            status:    statusFilter.value || undefined,
            customer:  customerFilter.value || undefined,
            urgent:    urgentFilter.value ? 1 : undefined,
            sample:    sampleFilter.value?.trim() || undefined,
            from:      dateRange.value?.[0] || undefined,
            to:        dateRange.value?.[1] || undefined,
            direction: props.filters.direction,
            per_page:  props.filters.per_page,
            ...extra,
        },
        {
            preserveScroll: true,
            preserveState:  true,
            onStart:  () => { loading.value = true; },
            onFinish: () => { loading.value = false; },
        },
    );
};

watch([statusFilter, customerFilter, urgentFilter, dateRange], () => apply({ page: 1 }));

// El buscador de número de muestra se manda con retardo: se tipea entero antes
// de que valga la pena consultar, y cada tecla sería una consulta con LIKE.
let searchTimer = null;
watch(sampleFilter, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => apply({ page: 1 }), 400);
});
onBeforeUnmount(() => clearTimeout(searchTimer));

const clear = () => {
    statusFilter.value = null;
    customerFilter.value = null;
    urgentFilter.value = false;
    sampleFilter.value = '';
    dateRange.value = null;
};

// ── Paginación y orden ───────────────────────────────────────────────────
const pagination = computed(() => ({
    current:  props.receptions.current_page,
    pageSize: props.receptions.per_page,
    total:    props.receptions.total,
    showSizeChanger: true,
    pageSizeOptions: ['10', '25', '50', '100'],
}));

/**
 * El servidor ordena SIEMPRE por fecha de recepción y solo admite la dirección
 * (`direction`). No se ofrece ordenar por otra columna para no dibujar una
 * flecha que después no hace nada.
 */
const onTableChange = (page, _filters, sorter) => {
    const direction = sorter?.order === 'ascend' ? 'asc'
        : sorter?.order === 'descend' ? 'desc'
            : props.filters.direction;

    apply({ page: page.current, per_page: page.pageSize, direction });
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

        <Card class="rc-filters" :body-style="{ padding: '14px 16px' }">
            <Space :size="10" wrap>
                <!-- El número de muestra: lo que el cliente cita por teléfono. -->
                <Input
                    v-model:value="sampleFilter"
                    allow-clear
                    class="rc-filters__search"
                    :placeholder="$t('receptions.search_sample')"
                >
                    <template #prefix><SearchOutlined /></template>
                </Input>

                <Select
                    v-model:value="statusFilter"
                    allow-clear
                    style="min-width: 170px"
                    :placeholder="$t('receptions.status')"
                >
                    <SelectOption v-for="status in statuses" :key="status" :value="status">
                        {{ $t(`receptions.status_${status}`) }}
                    </SelectOption>
                </Select>

                <Select
                    v-model:value="customerFilter"
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

                <Checkbox v-model:checked="urgentFilter">{{ $t('receptions.urgent_only') }}</Checkbox>

                <Button v-if="hasFilters" type="link" @click="clear">
                    <ClearOutlined /> {{ $t('global.clear_filters') }}
                </Button>
            </Space>
        </Card>

        <Card :body-style="{ padding: 0 }" class="grid-card">
            <ResponsiveTable
                :columns="columns"
                :data-source="receptions.data"
                :pagination="pagination"
                :loading="loading"
                :scroll="{ x: 'max-content' }"
                row-key="id"
                @change="onTableChange"
                @row-click="openReception"
            >
                <template #empty>
                    <div class="rc-empty">{{ $t('receptions.empty_hint') }}</div>
                </template>

                <template #bodyCell="{ column, record, text }">
                    <template v-if="column.key === 'received_at'">
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
    </div>
</template>

<style scoped>
.rc-filters { margin-bottom: 12px; }
.rc-filters__search { min-width: 220px; }
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
