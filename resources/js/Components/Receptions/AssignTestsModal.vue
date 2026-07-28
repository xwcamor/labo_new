<script setup>
/**
 * Qué pruebas se le piden a una muestra, o a todas las de la entrega.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LAS CASILLAS Y LOS NOMBRES SON LA MISMA COSA                             │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Cada casilla lleva el ID de su prueba. En el sistema anterior las casillas y
 * los nombres se dibujaban como dos listas independientes en dos columnas,
 * alineadas solo visualmente: si una prueba se daba de baja del catálogo, las
 * casillas se corrían respecto de los nombres y el usuario marcaba la prueba
 * equivocada, en silencio.
 *
 * Se manda la lista COMPLETA de lo pedido, no un delta: lo que no se marca y
 * todavía no se ensayó se da de baja. Lo que ya tiene trabajo hecho el servidor
 * lo conserva —un ensayo corrido tiene que seguir constando—, y por eso el
 * "aplicar a todas" no es un `update_all` masivo como el viejo botón de
 * "Forzar Pruebas".
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Alert, Button, Checkbox, CheckboxGroup, Input, Modal, Space,
} from 'ant-design-vue';
import { SearchOutlined } from '@ant-design/icons-vue';

import { useI18n } from '@/Plugins/i18n';

const props = defineProps({
    open:      { type: Boolean, default: false },
    reception: { type: Object, required: true },
    // La muestra a la que se le piden las pruebas. En `null` se pide a todas.
    sample:    { type: Object, default: null },
    tests:     { type: Array,  default: () => [] },
});

const emit = defineEmits(['update:open']);

const { t } = useI18n();

const selected = ref([]);
const search = ref('');
const processing = ref(false);

const applyAll = computed(() => props.sample === null);

/** Lo que ya se le pide a la muestra. Lo dado de baja no cuenta como pedido. */
const currentOf = (sample) => (sample?.tests ?? [])
    .filter((test) => test.status !== 'cancelled')
    .map((test) => test.test_definition_id);

// Al abrir se parte de lo que HAY, no de una lista vacía: abrir el cuadro y
// aceptar sin tocar nada no puede dar de baja lo que ya se pedía.
watch(() => props.open, (isOpen) => {
    if (!isOpen) return;
    selected.value = applyAll.value ? [] : currentOf(props.sample);
    search.value = '';
});

const visible = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.tests;

    return props.tests.filter(
        (test) => `${test.name} ${test.code}`.toLowerCase().includes(term),
    );
});

const title = computed(() => (applyAll.value
    ? t('receptions.tests_of_all')
    : t('receptions.tests_of_sample', { code: props.sample?.code ?? '' })));

const selectVisible = () => {
    const ids = visible.value.map((test) => test.id);
    selected.value = [...new Set([...selected.value, ...ids])];
};

const clearAll = () => { selected.value = []; };

const submit = () => {
    router.post(
        route('lab_management.receptions.tests', props.reception.slug),
        {
            tests:     selected.value,
            sample_id: props.sample?.id ?? null,
            apply_all: applyAll.value,
        },
        {
            preserveScroll: true,
            onStart:   () => { processing.value = true; },
            onFinish:  () => { processing.value = false; },
            onSuccess: () => emit('update:open', false),
        },
    );
};
</script>

<template>
    <Modal
        :open="open"
        :title="title"
        :width="720"
        :confirm-loading="processing"
        :ok-text="$t('global.save')"
        :cancel-text="$t('global.cancel')"
        @ok="submit"
        @cancel="emit('update:open', false)"
    >
        <!-- Aplicar a todas REEMPLAZA el pedido de cada muestra. Se dice antes,
             no después: es la operación que más lejos llega de un solo clic. -->
        <Alert
            v-if="applyAll"
            type="warning"
            show-icon
            class="rc-tests__warn"
            :message="$t('receptions.assign_to_all')"
            :description="$t('receptions.assign_to_all_hint')"
        />

        <div class="rc-tests__bar">
            <Input
                v-model:value="search"
                allow-clear
                :placeholder="$t('global.search')"
                class="rc-tests__search"
            >
                <template #prefix><SearchOutlined /></template>
            </Input>

            <Space :size="4">
                <Button type="link" size="small" @click="selectVisible">{{ $t('global.all') }}</Button>
                <Button type="link" size="small" @click="clearAll">{{ $t('global.none') }}</Button>
            </Space>
        </div>

        <div class="rc-tests__list">
            <CheckboxGroup v-model:value="selected" class="rc-tests__group">
                <Checkbox
                    v-for="test in visible"
                    :key="test.id"
                    :value="test.id"
                    class="rc-tests__item"
                >
                    {{ test.name }}
                </Checkbox>
            </CheckboxGroup>

            <p v-if="visible.length === 0" class="rc-tests__empty">
                {{ $t('global.no_results') }}
            </p>
        </div>
    </Modal>
</template>

<style scoped>
.rc-tests__warn { margin-bottom: 12px; }
.rc-tests__bar {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.rc-tests__search { max-width: 280px; }
.rc-tests__list { max-height: 46vh; overflow-y: auto; padding-right: 4px; }
/* Rejilla que se adapta: en móvil una columna, en escritorio dos o tres. */
.rc-tests__group {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 8px 12px;
    width: 100%;
}
/* Ant Design pone margen izquierdo a los checkbox contiguos: en una rejilla
   eso desalinea la primera columna de cada fila. */
.rc-tests__item { margin-left: 0 !important; }
.rc-tests__empty { color: var(--color-text-muted); font-size: 0.8125rem; padding: 12px 2px; }
</style>
