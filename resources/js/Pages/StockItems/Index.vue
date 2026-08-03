<script setup>
/**
 * El catálogo de artículos del almacén.
 *
 * Tres números por fila y no uno: EXISTENCIA (lo declarado), PRESTADO (lo que
 * está afuera ahora) y DISPONIBLE (la resta). El sistema anterior mostraba solo
 * el primero, así que la columna "Stock" decía 10 aunque los diez estuvieran
 * repartidos entre las bancadas.
 */
import { computed, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Button, Card, Select, SelectOption, Modal, Input, InputNumber, Switch,
    Tag, Tooltip, Pagination,
} from 'ant-design-vue';
import {
    PlusOutlined, EditOutlined, DeleteOutlined, GoldOutlined, WarningFilled,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import { useI18n } from '@/Plugins/i18n';
import { useAuth } from '@/Composables/useAuth';

defineOptions({ layout: AppLayout });

const props = defineProps({
    rows:    { type: Object, required: true },
    units:   { type: Array,  default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const { can } = useAuth();

// ── Filtros ──────────────────────────────────────────────────────────────
const filtros = reactive({
    q:      props.filters.q ?? '',
    unit:   props.filters.unit ?? undefined,
    active: props.filters.active ?? undefined,
});

let debounce = null;
watch(filtros, () => {
    clearTimeout(debounce);
    debounce = setTimeout(recargar, 350);
});

const recargar = (page = 1) => {
    router.get(route('lab_management.stock_items.index'), {
        q: filtros.q || undefined,
        unit: filtros.unit,
        active: filtros.active,
        page: page > 1 ? page : undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

// ── Alta / edición ───────────────────────────────────────────────────────
const abierto = ref(false);
const guardando = ref(false);
const errores = ref({});
const editando = ref(null);
const form = reactive({
    code: '', name: '', unit: undefined,
    on_hand: 0, min_qty: null, location: '', is_active: true,
});

const nuevo = () => {
    editando.value = null;
    errores.value = {};
    Object.assign(form, {
        code: '', name: '', unit: props.units[0] ?? undefined,
        on_hand: 0, min_qty: null, location: '', is_active: true,
    });
    abierto.value = true;
};

const editar = (fila) => {
    editando.value = fila;
    errores.value = {};
    Object.assign(form, {
        code: fila.code, name: fila.name, unit: fila.unit ?? undefined,
        on_hand: fila.on_hand, min_qty: fila.min_qty,
        location: fila.location ?? '', is_active: fila.is_active,
    });
    abierto.value = true;
};

const guardar = () => {
    guardando.value = true;
    errores.value = {};

    const opciones = {
        preserveScroll: true,
        onSuccess: () => { abierto.value = false; },
        onError:   (e) => { errores.value = e; },
        onFinish:  () => { guardando.value = false; },
    };

    if (editando.value) {
        router.put(route('lab_management.stock_items.update', editando.value.slug), { ...form }, opciones);
    } else {
        router.post(route('lab_management.stock_items.store'), { ...form }, opciones);
    }
};

const borrar = (fila) => {
    Modal.confirm({
        title:   t('global.delete_confirm_title'),
        content: t('stock_items.delete_confirm'),
        okText:  t('global.delete'),
        okType:  'danger',
        cancelText: t('global.cancel'),
        onOk: () => router.delete(
            route('lab_management.stock_items.destroy', fila.slug),
            { preserveScroll: true },
        ),
    });
};

const err = (campo) => errores.value?.[campo] ?? null;

const columns = computed(() => [
    { title: t('stock_items.code'),      dataIndex: 'code', key: 'code',      width: 130 },
    { title: t('stock_items.name'),      dataIndex: 'name', key: 'name' },
    { title: t('stock_items.unit'),      dataIndex: 'unit', key: 'unit',      width: 110 },
    { title: t('stock_items.location'),  dataIndex: 'location', key: 'location', width: 150 },
    { title: t('stock_items.on_hand'),   dataIndex: 'on_hand', key: 'on_hand', width: 110, align: 'right' },
    { title: t('stock_items.on_loan'),   dataIndex: 'on_loan', key: 'on_loan', width: 110, align: 'right' },
    { title: t('stock_items.available'), dataIndex: 'available', key: 'available', width: 130, align: 'right' },
    { title: t('global.actions'),        key: 'actions', width: 110, align: 'right' },
]);
</script>

<template>
    <Head :title="$t('stock_items.title')" />

    <div class="sap-index si-page">
        <div class="mi-title">
            <div class="page-header__title">
                <div class="page-header__icon"><GoldOutlined /></div>
                <div class="page-header__heading">
                    <h1>{{ $t('stock_items.title') }}</h1>
                    <p>{{ $t('stock_items.subtitle') }}</p>
                </div>
            </div>
            <Button v-if="can('stock_items.create')" type="primary" @click="nuevo()">
                <template #icon><PlusOutlined /></template>
                {{ $t('stock_items.new') }}
            </Button>
        </div>

        <Card class="si-filters" :body-style="{ padding: '12px 16px' }">
            <div class="si-filters__row">
                <Input
                    v-model:value="filtros.q"
                    :placeholder="$t('stock_items.search')"
                    allow-clear
                    style="max-width: 320px"
                />
                <Select
                    v-model:value="filtros.unit"
                    :placeholder="$t('stock_items.unit')"
                    allow-clear
                    style="min-width: 160px"
                >
                    <SelectOption v-for="u in units" :key="u" :value="u">{{ u }}</SelectOption>
                </Select>
                <Select
                    v-model:value="filtros.active"
                    :placeholder="$t('stock_items.active')"
                    allow-clear
                    style="min-width: 160px"
                >
                    <SelectOption :value="true">{{ $t('global.yes') }}</SelectOption>
                    <SelectOption :value="false">{{ $t('global.no') }}</SelectOption>
                </Select>
            </div>
        </Card>

        <Card :body-style="{ padding: 0 }">
            <ResponsiveTable
                :columns="columns"
                :data-source="rows.data"
                :pagination="false"
                row-key="slug"
                view="table"
                :scroll="{ x: 'max-content' }"
            >
                <template #empty>
                    <div class="si-empty">{{ $t('stock_items.empty') }}</div>
                </template>

                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'code'">
                        <span class="si-code">{{ record.code }}</span>
                    </template>
                    <template v-else-if="column.key === 'name'">
                        {{ record.name }}
                        <Tag v-if="!record.is_active" :bordered="false" class="si-off">
                            {{ $t('global.inactive') }}
                        </Tag>
                    </template>
                    <template v-else-if="column.key === 'unit'">{{ record.unit || '—' }}</template>
                    <template v-else-if="column.key === 'location'">{{ record.location || '—' }}</template>
                    <template v-else-if="column.key === 'on_loan'">
                        <span :class="{ 'si-out': record.on_loan > 0 }">{{ record.on_loan }}</span>
                    </template>
                    <!-- Lo disponible es lo que se mira: por eso lleva el aviso
                         de bajo mínimo, y no la existencia declarada. -->
                    <template v-else-if="column.key === 'available'">
                        <Tooltip v-if="record.is_low" :title="$t('stock_items.low_hint')">
                            <span class="si-low"><WarningFilled /> {{ record.available }}</span>
                        </Tooltip>
                        <span v-else>{{ record.available }}</span>
                    </template>
                    <template v-else-if="column.key === 'actions'">
                        <Tooltip v-if="can('stock_items.edit')" :title="$t('global.edit')">
                            <Button size="small" @click="editar(record)"><EditOutlined /></Button>
                        </Tooltip>
                        <Tooltip v-if="can('stock_items.delete')" :title="$t('global.delete')">
                            <Button size="small" danger class="si-del" @click="borrar(record)"><DeleteOutlined /></Button>
                        </Tooltip>
                    </template>
                </template>
            </ResponsiveTable>

            <div v-if="rows.total > rows.per_page" class="si-pager">
                <Pagination
                    :current="rows.current_page"
                    :page-size="rows.per_page"
                    :total="rows.total"
                    :show-size-changer="false"
                    @change="recargar"
                />
            </div>
        </Card>

        <Modal
            v-model:open="abierto"
            :title="editando ? $t('stock_items.edit') : $t('stock_items.new')"
            :confirm-loading="guardando"
            :ok-text="$t('global.save')"
            :cancel-text="$t('global.cancel')"
            @ok="guardar"
        >
            <div class="si-form">
                <div class="si-form__row">
                    <label>
                        <span>{{ $t('stock_items.code') }} <i>*</i></span>
                        <Input v-model:value="form.code" :status="err('code') ? 'error' : ''" />
                        <small v-if="err('code')" class="si-err">{{ err('code') }}</small>
                    </label>
                    <label>
                        <span>{{ $t('stock_items.unit') }}</span>
                        <Select v-model:value="form.unit" allow-clear style="width: 100%">
                            <SelectOption v-for="u in units" :key="u" :value="u">{{ u }}</SelectOption>
                        </Select>
                    </label>
                </div>

                <label>
                    <span>{{ $t('stock_items.name') }} <i>*</i></span>
                    <Input v-model:value="form.name" :status="err('name') ? 'error' : ''" />
                    <small v-if="err('name')" class="si-err">{{ err('name') }}</small>
                </label>

                <div class="si-form__row">
                    <label>
                        <span>{{ $t('stock_items.on_hand') }} <i>*</i></span>
                        <InputNumber v-model:value="form.on_hand" :min="0" style="width: 100%"
                                     :status="err('on_hand') ? 'error' : ''" />
                        <small class="si-hint">{{ $t('stock_items.on_hand_help') }}</small>
                        <small v-if="err('on_hand')" class="si-err">{{ err('on_hand') }}</small>
                    </label>
                    <label>
                        <span>{{ $t('stock_items.min_qty') }}</span>
                        <InputNumber v-model:value="form.min_qty" :min="0" style="width: 100%"
                                     :status="err('min_qty') ? 'error' : ''" />
                        <small class="si-hint">{{ $t('stock_items.min_qty_help') }}</small>
                    </label>
                </div>

                <label>
                    <span>{{ $t('stock_items.location') }}</span>
                    <Input v-model:value="form.location" />
                </label>

                <label class="si-form__switch">
                    <Switch v-model:checked="form.is_active" size="small" />
                    <span>{{ $t('stock_items.active') }}</span>
                </label>
                <small class="si-hint">{{ $t('stock_items.active_help') }}</small>
            </div>
        </Modal>
    </div>
</template>

<style scoped>
.si-filters { margin-bottom: 12px; }
.si-filters__row { display: flex; gap: 12px; flex-wrap: wrap; }
.si-code { font-weight: 600; font-variant-numeric: tabular-nums; }
.si-off { margin-left: 6px; font-size: 0.7rem; }
.si-out { font-weight: 600; }
.si-low { color: #C8281D; font-weight: 600; }
.si-del { margin-left: 6px; }
.si-empty { padding: 40px 16px; text-align: center; color: var(--color-text-muted); }
.si-pager { display: flex; justify-content: flex-end; padding: 12px 16px; }

.si-form { display: flex; flex-direction: column; gap: 12px; }
.si-form label { display: flex; flex-direction: column; gap: 4px; }
.si-form label > span { font-size: 0.8rem; color: var(--color-text-muted); }
.si-form label > span i { color: #C8281D; font-style: normal; }
.si-form__row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.si-form__switch { flex-direction: row !important; align-items: center; gap: 8px; }
.si-hint { font-size: 0.72rem; color: var(--color-text-muted); }
.si-err { color: #C8281D; font-size: 0.75rem; }

@media (max-width: 640px) {
    .si-form__row { grid-template-columns: 1fr; }
}
</style>
