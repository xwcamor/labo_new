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
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ AGRUPADAS, NO 29 CASILLAS SUELTAS                                        │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Las pruebas se piden por familia: al cliente que manda un aceite se le corre
 * el físico químico completo, o la cromatografía. Una rejilla plana de 29
 * casillas obliga a reconocerlas de a una y es donde se olvida la que faltaba.
 * El grupo viene de `test_groups` —un dato, no una lista escrita acá— y cada
 * encabezado marca o desmarca su familia entera.
 *
 * LAS CASILLAS NO VAN EN UN `CheckboxGroup`. Ant Design descarta del valor lo
 * que no está registrado en ESE grupo, así que un `CheckboxGroup` por familia
 * borraría lo marcado en las otras al tocar una casilla. Cada casilla se
 * controla sola contra la misma lista.
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Alert, Button, Checkbox, Input, Modal, Space,
} from 'ant-design-vue';
import { SearchOutlined } from '@ant-design/icons-vue';

import { useI18n } from '@/Plugins/i18n';
import { groupTests, isGrouped } from '@/Utils/testGroups';

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

/**
 * Lo que se ve, por familia de ensayo. Se agrupa DESPUÉS de buscar: un grupo
 * que quedó sin resultados no deja un encabezado colgado.
 */
const groups = computed(() => groupTests(visible.value, t('receptions.tests_no_group')));

/** Sin grupos que separar se dibuja la rejilla de siempre, sin encabezados. */
const showGroups = computed(() => isGrouped(groups.value));

const title = computed(() => (applyAll.value
    ? t('receptions.tests_of_all')
    : t('receptions.tests_of_sample', { code: props.sample?.code ?? '' })));

const isChecked = (test) => selected.value.includes(test.id);

const toggle = (test, checked) => {
    selected.value = checked
        ? [...new Set([...selected.value, test.id])]
        : selected.value.filter((id) => id !== test.id);
};

/**
 * Cuántas de la familia están pedidas. Es lo que dice el encabezado y lo que
 * decide si su casilla va marcada, a medias o vacía.
 */
const countOf = (group) => group.tests.filter(isChecked).length;

const allOf     = (group) => group.tests.length > 0 && countOf(group) === group.tests.length;
const someOf    = (group) => countOf(group) > 0 && !allOf(group);

/** Marcar o desmarcar la familia entera, sobre lo que la búsqueda deja ver. */
const toggleGroup = (group, checked) => {
    const ids = group.tests.map((test) => test.id);

    selected.value = checked
        ? [...new Set([...selected.value, ...ids])]
        : selected.value.filter((id) => !ids.includes(id));
};

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
            <!-- Una sección por familia de ensayo. El encabezado no es un
                 rótulo decorativo: su casilla marca o desmarca el grupo entero,
                 que es como el laboratorio pide las pruebas. -->
            <section v-for="group in groups" :key="group.key" class="rc-tests__section">
                <header v-if="showGroups" class="rc-tests__head">
                    <Checkbox
                        :checked="allOf(group)"
                        :indeterminate="someOf(group)"
                        @change="(event) => toggleGroup(group, event.target.checked)"
                    >
                        <span class="rc-tests__title">{{ group.label }}</span>
                    </Checkbox>
                    <span class="rc-tests__count">{{ countOf(group) }}/{{ group.tests.length }}</span>
                </header>

                <div class="rc-tests__group">
                    <Checkbox
                        v-for="test in group.tests"
                        :key="test.id"
                        :checked="isChecked(test)"
                        class="rc-tests__item"
                        @change="(event) => toggle(test, event.target.checked)"
                    >
                        {{ test.name }}
                    </Checkbox>
                </div>
            </section>

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

.rc-tests__section + .rc-tests__section { margin-top: 14px; }

/* El encabezado del grupo queda a la vista mientras se recorre su familia: con
   tres grupos y 29 pruebas, al llegar al final de una lista larga ya no se sabe
   cuál se está mirando. */
.rc-tests__head {
    position: sticky;
    top: 0;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 6px 2px;
    margin-bottom: 6px;
    background: var(--color-surface);
    border-bottom: 1px solid var(--color-border-soft);
}
.rc-tests__title { font-weight: 600; color: var(--color-text-strong); }
.rc-tests__count { font-size: 0.75rem; color: var(--color-text-muted); font-variant-numeric: tabular-nums; }

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
