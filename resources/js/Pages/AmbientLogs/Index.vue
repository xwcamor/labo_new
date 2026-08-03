<script setup>
/**
 * Bitácora de condiciones ambientales de las salas.
 *
 * Una lectura por sala y por día. Lo primero que se ve es si la de HOY ya está
 * cargada: es la pregunta que trae a esta pantalla, y buscarla entre las filas
 * del mes no la contesta.
 */
import { computed, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Button, Card, DatePicker, Select, SelectOption, Modal, Input, InputNumber,
    Tag, Tooltip, Alert,
} from 'ant-design-vue';
import {
    PlusOutlined, EditOutlined, DeleteOutlined, CloudOutlined, CheckCircleFilled,
} from '@ant-design/icons-vue';
import dayjs from 'dayjs';
import AppLayout from '@/Layouts/AppLayout.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const props = defineProps({
    rows:    { type: Object, required: true },
    rooms:   { type: Array,  default: () => [] },
    filters: { type: Object, default: () => ({}) },
    // Las salas que YA tienen la lectura de hoy.
    today:   { type: Array,  default: () => [] },
});

const { t } = useI18n();

// ── Filtros ──────────────────────────────────────────────────────────────
const filtros = reactive({
    room: props.filters.room ?? undefined,
    from: props.filters.from ? dayjs(props.filters.from) : null,
    to:   props.filters.to ? dayjs(props.filters.to) : null,
});

let debounce = null;
watch(filtros, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('lab_management.ambient_logs.index'), {
            room: filtros.room,
            from: filtros.from?.format('YYYY-MM-DD'),
            to:   filtros.to?.format('YYYY-MM-DD'),
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 350);
});

// ── Alta / edición ───────────────────────────────────────────────────────
const abierto = ref(false);
const guardando = ref(false);
const errores = ref({});
const editando = ref(null);
const form = reactive({
    room: null, logged_on: null,
    temperature_c: null, humidity_pct: null, pressure_hpa: null, notes: '',
});

const nuevo = (room = null) => {
    editando.value = null;
    errores.value = {};
    Object.assign(form, {
        room: room ?? props.rooms[0] ?? null,
        logged_on: dayjs(),
        temperature_c: null, humidity_pct: null, pressure_hpa: null, notes: '',
    });
    abierto.value = true;
};

const editar = (fila) => {
    editando.value = fila;
    errores.value = {};
    Object.assign(form, {
        room: fila.room,
        logged_on: dayjs(fila.logged_on),
        temperature_c: fila.temperature_c !== null ? Number(fila.temperature_c) : null,
        humidity_pct:  fila.humidity_pct !== null ? Number(fila.humidity_pct) : null,
        pressure_hpa:  fila.pressure_hpa !== null ? Number(fila.pressure_hpa) : null,
        notes: fila.notes ?? '',
    });
    abierto.value = true;
};

const guardar = () => {
    guardando.value = true;
    errores.value = {};

    const datos = { ...form, logged_on: form.logged_on?.format('YYYY-MM-DD') };
    const opciones = {
        preserveScroll: true,
        onSuccess: () => { abierto.value = false; },
        onError:   (e) => { errores.value = e; },
        onFinish:  () => { guardando.value = false; },
    };

    if (editando.value) {
        router.put(route('lab_management.ambient_logs.update', editando.value.slug), datos, opciones);
    } else {
        router.post(route('lab_management.ambient_logs.store'), datos, opciones);
    }
};

const borrar = (fila) => {
    Modal.confirm({
        title:   t('global.delete_confirm_title'),
        content: t('global.are_you_sure'),
        okText:  t('global.delete'),
        okType:  'danger',
        cancelText: t('global.cancel'),
        onOk: () => router.delete(
            route('lab_management.ambient_logs.destroy', fila.slug),
            { preserveScroll: true },
        ),
    });
};

const err = (campo) => errores.value?.[campo] ?? null;
const salaLabel = (room) => t(`ambient_logs.rooms.${room}`);
const faltanHoy = computed(() => props.rooms.filter((r) => !props.today.includes(r)));

const columns = computed(() => [
    { title: t('ambient_logs.logged_on'), dataIndex: 'logged_on', key: 'logged_on', width: 130 },
    { title: t('ambient_logs.room'),      dataIndex: 'room',      key: 'room',      width: 190 },
    { title: t('ambient_logs.temperature_c'), dataIndex: 'temperature_c', key: 'temperature_c', width: 150, align: 'right' },
    { title: t('ambient_logs.humidity_pct'),  dataIndex: 'humidity_pct',  key: 'humidity_pct',  width: 150, align: 'right' },
    { title: t('ambient_logs.pressure_hpa'),  dataIndex: 'pressure_hpa',  key: 'pressure_hpa',  width: 160, align: 'right' },
    { title: t('ambient_logs.notes'),     dataIndex: 'notes',     key: 'notes' },
    { title: t('global.actions'),         key: 'actions',         width: 110, align: 'right' },
]);

const numero = (v) => (v === null || v === undefined ? '—' : Number(v).toFixed(1));
</script>

<template>
    <Head :title="$t('ambient_logs.title')" />

    <div class="sap-index al-page">
        <div class="mi-title">
            <div class="page-header__title">
                <div class="page-header__icon"><CloudOutlined /></div>
                <div class="page-header__heading">
                    <h1>{{ $t('ambient_logs.title') }}</h1>
                    <p>{{ $t('ambient_logs.subtitle') }}</p>
                </div>
            </div>
            <Button type="primary" @click="nuevo()">
                <template #icon><PlusOutlined /></template>
                {{ $t('ambient_logs.new') }}
            </Button>
        </div>

        <!-- Lo primero: qué sala todavía no tiene la lectura de hoy. -->
        <Alert
            v-if="faltanHoy.length > 0"
            type="warning"
            show-icon
            class="al-missing"
            :message="$t('ambient_logs.missing_today')"
        >
            <template #description>
                <div class="al-missing__rooms">
                    <Button
                        v-for="room in faltanHoy"
                        :key="room"
                        size="small"
                        @click="nuevo(room)"
                    >
                        {{ salaLabel(room) }}
                    </Button>
                </div>
            </template>
        </Alert>
        <Alert v-else type="success" show-icon class="al-missing" :message="$t('ambient_logs.all_today')" />

        <Card class="al-filters" :body-style="{ padding: '12px 16px' }">
            <div class="al-filters__row">
                <Select
                    v-model:value="filtros.room"
                    :placeholder="$t('ambient_logs.room')"
                    allow-clear
                    style="min-width: 220px"
                >
                    <SelectOption v-for="room in rooms" :key="room" :value="room">
                        {{ salaLabel(room) }}
                    </SelectOption>
                </Select>
                <DatePicker v-model:value="filtros.from" :placeholder="$t('ambient_logs.from')" format="DD-MM-YYYY" />
                <DatePicker v-model:value="filtros.to" :placeholder="$t('ambient_logs.to')" format="DD-MM-YYYY" />
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
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'logged_on'">
                        {{ dayjs(record.logged_on).format('DD-MM-YYYY') }}
                        <CheckCircleFilled
                            v-if="dayjs(record.logged_on).isSame(dayjs(), 'day')"
                            class="al-today"
                        />
                    </template>
                    <template v-else-if="column.key === 'room'">
                        <Tag :bordered="false">{{ salaLabel(record.room) }}</Tag>
                    </template>
                    <template v-else-if="column.key === 'temperature_c'">{{ numero(record.temperature_c) }}</template>
                    <template v-else-if="column.key === 'humidity_pct'">{{ numero(record.humidity_pct) }}</template>
                    <template v-else-if="column.key === 'pressure_hpa'">{{ numero(record.pressure_hpa) }}</template>
                    <template v-else-if="column.key === 'actions'">
                        <Tooltip :title="$t('global.edit')">
                            <Button size="small" @click="editar(record)"><EditOutlined /></Button>
                        </Tooltip>
                        <Tooltip :title="$t('global.delete')">
                            <Button size="small" danger class="al-del" @click="borrar(record)"><DeleteOutlined /></Button>
                        </Tooltip>
                    </template>
                </template>
            </ResponsiveTable>
        </Card>

        <Modal
            v-model:open="abierto"
            :title="editando ? $t('ambient_logs.edit') : $t('ambient_logs.new')"
            :confirm-loading="guardando"
            :ok-text="$t('global.save')"
            :cancel-text="$t('global.cancel')"
            @ok="guardar"
        >
            <div class="al-form">
                <label>
                    <span>{{ $t('ambient_logs.room') }} <i>*</i></span>
                    <Select v-model:value="form.room" :status="err('room') ? 'error' : ''" style="width: 100%">
                        <SelectOption v-for="room in rooms" :key="room" :value="room">
                            {{ salaLabel(room) }}
                        </SelectOption>
                    </Select>
                    <small v-if="err('room')" class="al-err">{{ err('room') }}</small>
                </label>

                <label>
                    <span>{{ $t('ambient_logs.logged_on') }} <i>*</i></span>
                    <DatePicker
                        v-model:value="form.logged_on"
                        format="DD-MM-YYYY"
                        style="width: 100%"
                        :status="err('logged_on') ? 'error' : ''"
                        :disabled-date="(d) => d && d.isAfter(dayjs(), 'day')"
                    />
                    <small v-if="err('logged_on')" class="al-err">{{ err('logged_on') }}</small>
                </label>

                <div class="al-form__row">
                    <label>
                        <span>{{ $t('ambient_logs.temperature_c') }}</span>
                        <InputNumber v-model:value="form.temperature_c" :step="0.1" style="width: 100%"
                                     :status="err('temperature_c') ? 'error' : ''" />
                        <small v-if="err('temperature_c')" class="al-err">{{ err('temperature_c') }}</small>
                    </label>
                    <label>
                        <span>{{ $t('ambient_logs.humidity_pct') }}</span>
                        <InputNumber v-model:value="form.humidity_pct" :step="0.1" style="width: 100%"
                                     :status="err('humidity_pct') ? 'error' : ''" />
                        <small v-if="err('humidity_pct')" class="al-err">{{ err('humidity_pct') }}</small>
                    </label>
                    <label>
                        <span>{{ $t('ambient_logs.pressure_hpa') }}</span>
                        <InputNumber v-model:value="form.pressure_hpa" :step="0.1" style="width: 100%"
                                     :status="err('pressure_hpa') ? 'error' : ''" />
                        <small v-if="err('pressure_hpa')" class="al-err">{{ err('pressure_hpa') }}</small>
                    </label>
                </div>

                <label>
                    <span>{{ $t('ambient_logs.notes') }}</span>
                    <Input.TextArea v-model:value="form.notes" :auto-size="{ minRows: 2, maxRows: 4 }" />
                </label>
            </div>
        </Modal>
    </div>
</template>

<style scoped>
.al-missing { margin-bottom: 12px; }
.al-missing__rooms { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
.al-filters { margin-bottom: 12px; }
.al-filters__row { display: flex; gap: 12px; flex-wrap: wrap; }
.al-today { color: #1D7044; margin-left: 6px; font-size: 12px; }
.al-del { margin-left: 6px; }

.al-form { display: flex; flex-direction: column; gap: 12px; }
.al-form label { display: flex; flex-direction: column; gap: 4px; }
.al-form label > span { font-size: 0.8rem; color: var(--color-text-muted); }
.al-form label > span i { color: #C8281D; font-style: normal; }
.al-form__row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.al-err { color: #C8281D; font-size: 0.75rem; }

@media (max-width: 640px) {
    .al-form__row { grid-template-columns: 1fr; }
}
</style>
