<script setup>
/**
 * Los VALORES CONSTANTES de la prueba, desde la bancada.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ACÁ Y NO SOLO EN LA FICHA DE LA PRUEBA                           │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sistema anterior le daba a cada prueba su propia pantalla de «Valores
 * Constantes», colgada del menú, al lado de «Muestras». El analista que estaba
 * cargando la bancada la abría desde ahí.
 *
 * Acá esos valores viven en el editor de columnas, junto al tipo, la unidad, los
 * decimales y la fórmula: un formulario grande donde la constante es un campo
 * más. Para cambiar el factor de KOH de un lote nuevo de titulante hay que salir
 * de la hoja, entrar al editor, buscar la columna y encontrar el campo entre
 * otros diez. Este modal hace lo que hacía aquella pantalla: muestra SOLO las
 * constantes, con su valor, y las guarda.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ ALCANZA EL CAMBIO                                                    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * La constante es de LA PRUEBA, no de esta hoja: cambiarla acá la cambia para
 * todas las hojas de esa prueba de acá en adelante. Y NO reescribe lo ya
 * cargado — las filas que ya tienen el valor viejo lo conservan, porque un
 * resultado ya calculado no se recalcula solo. El aviso del modal lo dice, para
 * que nadie espere que corrija hacia atrás.
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Alert, Input, Modal, Table } from 'ant-design-vue';

const props = defineProps({
    open:       { type: Boolean, default: false },
    /** La prueba de esta hoja: de ella son las constantes. */
    definition: { type: Object, required: true },
    /** Las columnas de la hoja. Acá se filtran las que son constantes. */
    fields:     { type: Array,  default: () => [] },
    canEdit:    { type: Boolean, default: false },
});

const emit = defineEmits(['update:open']);

/** Solo las que la prueba declara constantes: es lo que el servidor acepta. */
const constantes = computed(
    () => props.fields.filter((f) => f.is_reusable).sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)),
);

const valores = ref({});
const guardando = ref(false);

// Al abrirse se recarga desde las props: si alguien editó y cerró sin guardar,
// la próxima vez tiene que ver lo que hay en la base, no su borrador.
watch(() => props.open, (abierto) => {
    if (abierto) {
        valores.value = Object.fromEntries(
            constantes.value.map((f) => [f.id, f.default_value ?? '']),
        );
    }
});

const sucio = computed(() => constantes.value.some(
    (f) => (valores.value[f.id] ?? '') !== (f.default_value ?? ''),
));

const columnas = [
    { title: 'campo',  dataIndex: 'label', key: 'label' },
    { title: 'valor',  key: 'value', width: 200 },
];

const guardar = () => {
    if (!sucio.value) { emit('update:open', false); return; }

    guardando.value = true;
    router.post(
        route('lab_management.test_definitions.constants.update', props.definition.slug),
        { values: valores.value },
        {
            preserveScroll: true,
            onSuccess: () => emit('update:open', false),
            onFinish:  () => { guardando.value = false; },
        },
    );
};
</script>

<template>
    <Modal
        :open="open"
        :title="$t('worksheets.constants_title', { test: definition?.name })"
        :confirm-loading="guardando"
        :ok-text="$t('global.save')"
        :cancel-text="$t('global.close')"
        :ok-button-props="{ disabled: !canEdit || !sucio }"
        @ok="guardar"
        @cancel="emit('update:open', false)"
    >
        <Alert
            type="info"
            show-icon
            class="mb-4"
            :message="$t('worksheets.constants_scope')"
        />

        <Table
            :columns="columnas"
            :data-source="constantes"
            :pagination="false"
            row-key="id"
            size="small"
        >
            <template #headerCell="{ column }">
                <template v-if="column.key === 'label'">{{ $t('test_fields.label') }}</template>
                <template v-else>{{ $t('test_fields.default_value') }}</template>
            </template>

            <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'label'">
                    {{ record.label }}
                    <span v-if="record.unit" class="wc-unit">{{ record.unit }}</span>
                </template>
                <template v-else>
                    <Input
                        v-model:value="valores[record.id]"
                        :disabled="!canEdit"
                        :maxlength="255"
                        @press-enter="guardar"
                    />
                </template>
            </template>
        </Table>
    </Modal>
</template>

<style scoped>
.wc-unit { color: var(--color-text-secondary, #6b7280); font-size: 12px; margin-left: 4px; }
.mb-4 { margin-bottom: 16px; }
</style>
