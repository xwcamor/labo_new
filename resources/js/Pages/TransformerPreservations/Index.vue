<script setup>
/**
 * Sistemas de preservación del aceite.
 *
 * Un catálogo de cuatro campos, en modal. Lo único que agrega sobre una lista
 * pelada es la columna de EQUIPOS QUE LA USAN: es lo que hay que mirar antes de
 * tocar una fila, y es lo que decide si el botón de baja sirve o si lo que
 * corresponde es apagar el interruptor de activa.
 */
import { computed, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Button, Card, Input, InputNumber, Modal, Switch, Tag, Tooltip,
} from 'ant-design-vue';
import {
    PlusOutlined, EditOutlined, DeleteOutlined, ApartmentOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const props = defineProps({
    rows:    { type: Array,  default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const busqueda = ref(props.filters.q ?? '');

let debounce = null;
watch(busqueda, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('business_management.transformer_preservations.index'), {
            q: busqueda.value || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 350);
});

const abierto = ref(false);
const guardando = ref(false);
const errores = ref({});
const editando = ref(null);
const form = reactive({ name: '', code: '', sort_order: null, is_active: true });

const nuevo = () => {
    editando.value = null;
    errores.value = {};
    Object.assign(form, { name: '', code: '', sort_order: null, is_active: true });
    abierto.value = true;
};

const editar = (fila) => {
    editando.value = fila;
    errores.value = {};
    Object.assign(form, {
        name: fila.name, code: fila.code ?? '',
        sort_order: fila.sort_order, is_active: fila.is_active,
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
        router.put(route('business_management.transformer_preservations.update', editando.value.slug), { ...form }, opciones);
    } else {
        router.post(route('business_management.transformer_preservations.store'), { ...form }, opciones);
    }
};

const borrar = (fila) => {
    Modal.confirm({
        title:   t('global.delete_confirm_title'),
        content: t('transformer_preservations.delete_confirm'),
        okText:  t('global.delete'),
        okType:  'danger',
        cancelText: t('global.cancel'),
        onOk: () => router.delete(
            route('business_management.transformer_preservations.destroy', fila.slug),
            { preserveScroll: true },
        ),
    });
};

const err = (campo) => errores.value?.[campo] ?? null;

const columns = computed(() => [
    { title: t('transformer_preservations.name'), dataIndex: 'name', key: 'name' },
    { title: t('transformer_preservations.code'), dataIndex: 'code', key: 'code', width: 140 },
    { title: t('transformer_preservations.order'), dataIndex: 'sort_order', key: 'sort_order', width: 110, align: 'right' },
    { title: t('transformer_preservations.in_use'), dataIndex: 'equipment_count', key: 'equipment_count', width: 150, align: 'right' },
    { title: t('global.actions'), key: 'actions', width: 110, align: 'right' },
]);
</script>

<template>
    <Head :title="$t('transformer_preservations.title')" />

    <div class="sap-index tp-page">
        <div class="mi-title">
            <div class="page-header__title">
                <div class="page-header__icon"><ApartmentOutlined /></div>
                <div class="page-header__heading">
                    <h1>{{ $t('transformer_preservations.title') }}</h1>
                    <p>{{ $t('transformer_preservations.subtitle') }}</p>
                </div>
            </div>
            <Button type="primary" @click="nuevo()">
                <template #icon><PlusOutlined /></template>
                {{ $t('transformer_preservations.new') }}
            </Button>
        </div>

        <Card class="tp-filters" :body-style="{ padding: '12px 16px' }">
            <Input
                v-model:value="busqueda"
                :placeholder="$t('transformer_preservations.search')"
                allow-clear
                style="max-width: 340px"
            />
        </Card>

        <Card :body-style="{ padding: 0 }">
            <ResponsiveTable
                :columns="columns"
                :data-source="rows"
                :pagination="false"
                row-key="slug"
                view="table"
                :scroll="{ x: 'max-content' }"
            >
                <template #empty>
                    <div class="tp-empty">{{ $t('transformer_preservations.empty') }}</div>
                </template>

                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'name'">
                        {{ record.name }}
                        <Tag v-if="!record.is_active" :bordered="false" class="tp-off">
                            {{ $t('global.inactive') }}
                        </Tag>
                    </template>
                    <template v-else-if="column.key === 'code'">{{ record.code || '—' }}</template>
                    <template v-else-if="column.key === 'sort_order'">{{ record.sort_order ?? '—' }}</template>
                    <template v-else-if="column.key === 'equipment_count'">{{ record.equipment_count }}</template>
                    <template v-else-if="column.key === 'actions'">
                        <Tooltip :title="$t('global.edit')">
                            <Button size="small" @click="editar(record)"><EditOutlined /></Button>
                        </Tooltip>
                        <!-- Con equipos usándola la baja se rechaza en el
                             servidor; acá se dice antes, en el tooltip. -->
                        <Tooltip
                            :title="record.equipment_count > 0
                                ? $t('transformer_preservations.errors.in_use', { n: record.equipment_count })
                                : $t('global.delete')"
                        >
                            <Button
                                size="small"
                                danger
                                class="tp-del"
                                :disabled="record.equipment_count > 0"
                                @click="borrar(record)"
                            >
                                <DeleteOutlined />
                            </Button>
                        </Tooltip>
                    </template>
                </template>
            </ResponsiveTable>
        </Card>

        <Modal
            v-model:open="abierto"
            :title="editando ? $t('transformer_preservations.edit') : $t('transformer_preservations.new')"
            :confirm-loading="guardando"
            :ok-text="$t('global.save')"
            :cancel-text="$t('global.cancel')"
            @ok="guardar"
        >
            <div class="tp-form">
                <label>
                    <span>{{ $t('transformer_preservations.name') }} <i>*</i></span>
                    <Input v-model:value="form.name" :status="err('name') ? 'error' : ''" />
                    <small v-if="err('name')" class="tp-err">{{ err('name') }}</small>
                </label>

                <div class="tp-form__row">
                    <label>
                        <span>{{ $t('transformer_preservations.code') }}</span>
                        <Input v-model:value="form.code" />
                    </label>
                    <label>
                        <span>{{ $t('transformer_preservations.order') }}</span>
                        <InputNumber v-model:value="form.sort_order" :min="0" style="width: 100%" />
                    </label>
                </div>

                <label class="tp-form__switch">
                    <Switch v-model:checked="form.is_active" size="small" />
                    <span>{{ $t('transformer_preservations.active') }}</span>
                </label>
                <small class="tp-hint">{{ $t('transformer_preservations.active_help') }}</small>
            </div>
        </Modal>
    </div>
</template>

<style scoped>
.tp-filters { margin-bottom: 12px; }
.tp-off { margin-left: 6px; font-size: 0.7rem; }
.tp-del { margin-left: 6px; }
.tp-empty { padding: 40px 16px; text-align: center; color: var(--color-text-muted); }

.tp-form { display: flex; flex-direction: column; gap: 12px; }
.tp-form label { display: flex; flex-direction: column; gap: 4px; }
.tp-form label > span { font-size: 0.8rem; color: var(--color-text-muted); }
.tp-form label > span i { color: #C8281D; font-style: normal; }
.tp-form__row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.tp-form__switch { flex-direction: row !important; align-items: center; gap: 8px; }
.tp-hint { font-size: 0.72rem; color: var(--color-text-muted); }
.tp-err { color: #C8281D; font-size: 0.75rem; }

@media (max-width: 640px) {
    .tp-form__row { grid-template-columns: 1fr; }
}
</style>
