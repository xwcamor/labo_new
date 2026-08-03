<script setup>
/**
 * Préstamos del almacén.
 *
 * El alta va en un modal con la cabecera arriba y las líneas abajo, que es la
 * misma forma que tenía el sistema anterior. Las diferencias están en lo que
 * ahora NO deja hacer: prestar sin decir a quién, y prestar más de lo que hay.
 * El disponible viaja con cada artículo y se descuenta en vivo mientras se
 * arma el préstamo, para que el aviso llegue al elegir y no al guardar.
 */
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Alert, Button, Card, DatePicker, Input, InputNumber, Modal, Pagination,
    Select, SelectOption, Tag, Tooltip,
} from 'ant-design-vue';
import {
    PlusOutlined, DeleteOutlined, EyeOutlined, SwapOutlined,
} from '@ant-design/icons-vue';
import dayjs from 'dayjs';
import AppLayout from '@/Layouts/AppLayout.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import { useI18n } from '@/Plugins/i18n';
import { useAuth } from '@/Composables/useAuth';

defineOptions({ layout: AppLayout });

const props = defineProps({
    rows:     { type: Object, required: true },
    items:    { type: Array,  default: () => [] },
    users:    { type: Array,  default: () => [] },
    statuses: { type: Array,  default: () => [] },
    filters:  { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const { can } = useAuth();

// ── Filtros ──────────────────────────────────────────────────────────────
const filtros = reactive({
    status: props.filters.status ?? undefined,
    q:      props.filters.q ?? '',
    item:   props.filters.item ?? undefined,
    from:   props.filters.from ? dayjs(props.filters.from) : null,
    to:     props.filters.to ? dayjs(props.filters.to) : null,
});

let debounce = null;
watch(filtros, () => {
    clearTimeout(debounce);
    debounce = setTimeout(recargar, 350);
});

const recargar = (page = 1) => {
    router.get(route('lab_management.stock_loans.index'), {
        status: filtros.status,
        q: filtros.q || undefined,
        item: filtros.item,
        from: filtros.from?.format('YYYY-MM-DD'),
        to:   filtros.to?.format('YYYY-MM-DD'),
        page: page > 1 ? page : undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

// ── Alta ─────────────────────────────────────────────────────────────────
const abierto = ref(false);
const guardando = ref(false);
const errores = ref({});
const form = reactive({
    loaned_on: null,
    borrower_user_id: undefined,
    borrower_name: '',
    purpose: '',
    lines: [],
});

const nuevo = () => {
    errores.value = {};
    Object.assign(form, {
        loaned_on: dayjs(),
        borrower_user_id: undefined,
        borrower_name: '',
        purpose: '',
        lines: [{ stock_item_id: undefined, qty: 1, notes: '' }],
    });
    abierto.value = true;
};

const agregarLinea = () => form.lines.push({ stock_item_id: undefined, qty: 1, notes: '' });
const quitarLinea  = (i) => form.lines.splice(i, 1);

const articulo = (id) => props.items.find((a) => a.id === id);

/**
 * Lo que queda de un artículo DESCONTANDO lo que ya se puso en otras líneas de
 * este mismo préstamo. Sin esto, dos líneas de tres unidades del mismo frasco
 * pasaban las dos la comprobación de a una y el rechazo llegaba al guardar.
 */
const restante = (indice) => {
    const linea = form.lines[indice];
    const art = articulo(linea?.stock_item_id);
    if (!art) return null;

    const enOtras = form.lines.reduce((suma, otra, i) => (
        i !== indice && otra.stock_item_id === linea.stock_item_id ? suma + (Number(otra.qty) || 0) : suma
    ), 0);

    return art.available - enOtras;
};

const guardar = () => {
    guardando.value = true;
    errores.value = {};

    router.post(route('lab_management.stock_loans.store'), {
        ...form,
        loaned_on: form.loaned_on?.format('YYYY-MM-DD'),
    }, {
        preserveScroll: true,
        onSuccess: () => { abierto.value = false; },
        onError:   (e) => { errores.value = e; },
        onFinish:  () => { guardando.value = false; },
    });
};

const borrar = (fila) => {
    Modal.confirm({
        title:   t('global.delete_confirm_title'),
        content: t('stock_loans.delete_confirm'),
        okText:  t('global.delete'),
        okType:  'danger',
        cancelText: t('global.cancel'),
        onOk: () => router.delete(route('lab_management.stock_loans.destroy', fila.slug)),
    });
};

const err = (campo) => errores.value?.[campo] ?? null;

const columns = computed(() => [
    { title: t('stock_loans.loaned_on'), dataIndex: 'loaned_on', key: 'loaned_on', width: 130 },
    { title: t('stock_loans.borrower'),  dataIndex: 'borrower',  key: 'borrower',  width: 200 },
    { title: t('stock_loans.lines'),     key: 'lines' },
    { title: t('stock_loans.pending'),   dataIndex: 'pending',   key: 'pending',   width: 110, align: 'right' },
    { title: t('stock_loans.status'),    dataIndex: 'status',    key: 'status',    width: 150 },
    { title: t('global.actions'),        key: 'actions',         width: 110, align: 'right' },
]);
</script>

<template>
    <Head :title="$t('stock_loans.title')" />

    <div class="sap-index sl-page">
        <div class="mi-title">
            <div class="page-header__title">
                <div class="page-header__icon"><SwapOutlined /></div>
                <div class="page-header__heading">
                    <h1>{{ $t('stock_loans.title') }}</h1>
                    <p>{{ $t('stock_loans.subtitle') }}</p>
                </div>
            </div>
            <Button v-if="can('stock_loans.create')" type="primary" @click="nuevo()">
                <template #icon><PlusOutlined /></template>
                {{ $t('stock_loans.new') }}
            </Button>
        </div>

        <Card class="sl-filters" :body-style="{ padding: '12px 16px' }">
            <div class="sl-filters__row">
                <Input v-model:value="filtros.q" :placeholder="$t('stock_loans.search')" allow-clear style="max-width: 300px" />
                <Select v-model:value="filtros.status" :placeholder="$t('stock_loans.status')" allow-clear style="min-width: 180px">
                    <SelectOption v-for="s in statuses" :key="s" :value="s">
                        {{ $t(`stock_loans.status_${s}`) }}
                    </SelectOption>
                </Select>
                <Select
                    v-model:value="filtros.item"
                    :placeholder="$t('stock_loans.item')"
                    allow-clear
                    show-search
                    option-filter-prop="label"
                    style="min-width: 240px"
                >
                    <SelectOption v-for="a in items" :key="a.slug" :value="a.slug" :label="a.name">
                        {{ a.name }}
                    </SelectOption>
                </Select>
                <DatePicker v-model:value="filtros.from" :placeholder="$t('stock_loans.loaned_on')" format="DD-MM-YYYY" />
                <DatePicker v-model:value="filtros.to" :placeholder="$t('stock_loans.loaned_on')" format="DD-MM-YYYY" />
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
                    <div class="sl-empty">{{ $t('stock_loans.empty') }}</div>
                </template>

                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'loaned_on'">
                        {{ dayjs(record.loaned_on).format('DD-MM-YYYY') }}
                    </template>
                    <template v-else-if="column.key === 'borrower'">
                        {{ record.borrower || '—' }}
                    </template>
                    <!-- Qué se llevó, con lo que falta de cada cosa. Es la
                         columna que se lee cuando alguien pregunta por un
                         frasco: sin ella hay que abrir préstamo por préstamo. -->
                    <template v-else-if="column.key === 'lines'">
                        <div v-for="l in record.lines" :key="l.id" class="sl-line">
                            <span class="sl-line__name">{{ l.item?.name }}</span>
                            <span class="sl-line__qty">{{ l.qty }}</span>
                            <span v-if="l.pending > 0" class="sl-line__pend">
                                ({{ $t('stock_loans.pending').toLowerCase() }}: {{ l.pending }})
                            </span>
                        </div>
                    </template>
                    <template v-else-if="column.key === 'pending'">
                        <span :class="{ 'sl-out': record.pending > 0 }">{{ record.pending }}</span>
                    </template>
                    <template v-else-if="column.key === 'status'">
                        <Tag :bordered="false" :color="record.status === 'open' ? 'red' : 'green'">
                            {{ $t(`stock_loans.status_${record.status}`) }}
                        </Tag>
                    </template>
                    <template v-else-if="column.key === 'actions'">
                        <Tooltip :title="$t('global.view')">
                            <Link :href="route('lab_management.stock_loans.show', record.slug)">
                                <Button size="small"><EyeOutlined /></Button>
                            </Link>
                        </Tooltip>
                        <Tooltip v-if="can('stock_loans.delete')" :title="$t('global.delete')">
                            <Button size="small" danger class="sl-del" @click="borrar(record)"><DeleteOutlined /></Button>
                        </Tooltip>
                    </template>
                </template>
            </ResponsiveTable>

            <div v-if="rows.total > rows.per_page" class="sl-pager">
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
            :title="$t('stock_loans.new')"
            :confirm-loading="guardando"
            :ok-text="$t('global.save')"
            :cancel-text="$t('global.cancel')"
            width="760px"
            @ok="guardar"
        >
            <div class="sl-form">
                <Alert v-if="err('lines')" type="error" show-icon :message="err('lines')" class="sl-alert" />
                <Alert v-if="err('borrower_user_id')" type="error" show-icon :message="err('borrower_user_id')" class="sl-alert" />

                <div class="sl-form__row">
                    <label>
                        <span>{{ $t('stock_loans.loaned_on') }} <i>*</i></span>
                        <DatePicker
                            v-model:value="form.loaned_on"
                            format="DD-MM-YYYY"
                            style="width: 100%"
                            :status="err('loaned_on') ? 'error' : ''"
                            :disabled-date="(d) => d && d.isAfter(dayjs(), 'day')"
                        />
                        <small v-if="err('loaned_on')" class="sl-err">{{ err('loaned_on') }}</small>
                    </label>
                    <label>
                        <span>{{ $t('stock_loans.borrower_user') }}</span>
                        <Select
                            v-model:value="form.borrower_user_id"
                            allow-clear
                            show-search
                            option-filter-prop="label"
                            style="width: 100%"
                            :status="err('borrower_user_id') ? 'error' : ''"
                        >
                            <SelectOption v-for="u in users" :key="u.id" :value="u.id" :label="u.name">
                                {{ u.name }}
                            </SelectOption>
                        </Select>
                    </label>
                </div>

                <!-- Uno de los dos, no los dos: el de adentro se elige de la
                     lista, el de afuera se escribe. -->
                <label>
                    <span>{{ $t('stock_loans.borrower_name') }}</span>
                    <Input
                        v-model:value="form.borrower_name"
                        :placeholder="$t('stock_loans.borrower_name_placeholder')"
                        :disabled="!!form.borrower_user_id"
                        :status="err('borrower_name') ? 'error' : ''"
                    />
                    <small class="sl-hint">{{ $t('stock_loans.borrower_help') }}</small>
                </label>

                <label>
                    <span>{{ $t('stock_loans.purpose') }}</span>
                    <Input.TextArea
                        v-model:value="form.purpose"
                        :placeholder="$t('stock_loans.purpose_placeholder')"
                        :auto-size="{ minRows: 2, maxRows: 4 }"
                    />
                </label>

                <div class="sl-lines">
                    <div class="sl-lines__head">
                        <h4>{{ $t('stock_loans.lines') }}</h4>
                        <Button size="small" @click="agregarLinea">
                            <PlusOutlined /> {{ $t('stock_loans.add_line') }}
                        </Button>
                    </div>

                    <div v-for="(linea, i) in form.lines" :key="i" class="sl-lines__row">
                        <Select
                            v-model:value="linea.stock_item_id"
                            :placeholder="$t('stock_loans.item')"
                            show-search
                            option-filter-prop="label"
                            style="flex: 1; min-width: 220px"
                        >
                            <SelectOption
                                v-for="a in items"
                                :key="a.id"
                                :value="a.id"
                                :label="a.name"
                                :disabled="a.available <= 0"
                            >
                                {{ a.name }}
                                <span class="sl-avail">{{ $t('stock_loans.available_n', { n: a.available }) }}</span>
                            </SelectOption>
                        </Select>

                        <div class="sl-lines__qty">
                            <InputNumber v-model:value="linea.qty" :min="1" style="width: 100%" />
                            <!-- El tope se muestra al lado de la cantidad, no en
                                 un error después de guardar. -->
                            <small v-if="restante(i) !== null" :class="linea.qty > restante(i) ? 'sl-err' : 'sl-hint'">
                                {{ $t('stock_loans.available_n', { n: restante(i) }) }}
                            </small>
                        </div>

                        <Input v-model:value="linea.notes" :placeholder="$t('stock_loans.notes')" style="flex: 1" />

                        <Button
                            size="small"
                            danger
                            :disabled="form.lines.length === 1"
                            @click="quitarLinea(i)"
                        >
                            <DeleteOutlined />
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>

<style scoped>
.sl-filters { margin-bottom: 12px; }
.sl-filters__row { display: flex; gap: 12px; flex-wrap: wrap; }
.sl-line { font-size: 0.82rem; }
.sl-line__name { font-weight: 500; }
.sl-line__qty { margin-left: 6px; font-variant-numeric: tabular-nums; }
.sl-line__pend { margin-left: 6px; color: #C8281D; }
.sl-out { color: #C8281D; font-weight: 600; }
.sl-del { margin-left: 6px; }
.sl-empty { padding: 40px 16px; text-align: center; color: var(--color-text-muted); }
.sl-pager { display: flex; justify-content: flex-end; padding: 12px 16px; }

.sl-form { display: flex; flex-direction: column; gap: 12px; }
.sl-form label { display: flex; flex-direction: column; gap: 4px; }
.sl-form label > span { font-size: 0.8rem; color: var(--color-text-muted); }
.sl-form label > span i { color: #C8281D; font-style: normal; }
.sl-form__row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.sl-alert { margin-bottom: 4px; }

.sl-lines { border-top: 1px solid var(--color-border); padding-top: 10px; }
.sl-lines__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.sl-lines__head h4 { margin: 0; font-size: 0.85rem; }
.sl-lines__row { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 8px; }
.sl-lines__qty { width: 120px; display: flex; flex-direction: column; gap: 2px; }
.sl-avail { margin-left: 8px; font-size: 0.72rem; color: var(--color-text-muted); }
.sl-hint { font-size: 0.72rem; color: var(--color-text-muted); }
.sl-err { color: #C8281D; font-size: 0.72rem; }

@media (max-width: 640px) {
    .sl-form__row { grid-template-columns: 1fr; }
    .sl-lines__row { flex-wrap: wrap; }
}
</style>
