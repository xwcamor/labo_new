<script setup>
/**
 * Alta y edición del grupo de pruebas, en diálogo (regla Fiori de la casa:
 * menos de 7 campos no merece página completa).
 *
 * El código se DERIVA del nombre: "Fisico Quimico" → `fisico_quimico`. Es el
 * identificador técnico con el que las pruebas, el informe y los archivos de
 * idioma referencian al grupo, y dejarlo escribir a mano produjo un grupo
 * llamado "67". La misma regla corre en el servidor, así que lo que se ve
 * mientras se escribe es exactamente lo que se guarda. Al EDITAR no se
 * recalcula: si algo ya referencia ese código, cambiarlo por debajo rompe el
 * enlace en silencio — el campo queda de solo lectura.
 */
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { FormItem, Input, InputNumber, Switch, Space } from 'ant-design-vue';
import FormModal from '@/Components/Common/FormModal.vue';
import { useI18n } from '@/Plugins/i18n';

const props = defineProps({
    open:   { type: Boolean, default: false },
    // El registro a editar; null = alta.
    record: { type: Object,  default: null },
});

const emit = defineEmits(['close']);

const { t } = useI18n();

const isEdit = computed(() => !!props.record);

const form = useForm({
    name:       '',
    code:       '',
    sort_order: null,
    is_active:  true,
});

// El diálogo se reusa entre aperturas: al abrir se carga el registro (o se
// limpia para el alta) y se descartan los errores de la vez anterior.
watch(() => props.open, (abierto) => {
    if (!abierto) return;
    form.clearErrors();
    form.name       = props.record?.name ?? '';
    form.code       = props.record?.code ?? '';
    form.sort_order = props.record?.sort_order ?? null;
    form.is_active  = props.record?.is_active ?? true;
});

const derivarCodigo = (nombre) => (nombre ?? '')
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')   // fuera las tildes
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 40);

watch(() => form.name, (nombre) => {
    if (!isEdit.value) form.code = derivarCodigo(nombre);
});

const title = computed(() => (isEdit.value
    ? `${t('global.edit')} ${t('test_groups.record')}`
    : t('test_groups.new')));

const submit = () => {
    const opciones = { preserveScroll: true, onSuccess: () => emit('close') };

    if (isEdit.value) {
        form.put(route('lab_management.test_groups.update', props.record.slug), opciones);
    } else {
        form.post(route('lab_management.test_groups.store'), opciones);
    }
};
</script>

<template>
    <FormModal
        :open="open"
        :title="title"
        :is-edit="isEdit"
        :processing="form.processing"
        :has-errors="form.hasErrors"
        create-label-key="test_groups.new"
        @close="emit('close')"
        @submit="submit"
    >
        <FormItem
            :label="$t('test_groups.name')"
            :tooltip="$t('test_groups.name_help')"
            required
            :validate-status="form.errors.name ? 'error' : ''"
            :help="form.errors.name"
        >
            <Input
                v-model:value="form.name"
                :maxlength="100"
                showCount
                autofocus
                :placeholder="$t('test_groups.name_placeholder')"
            />
        </FormItem>

        <FormItem
            :label="$t('test_groups.code')"
            :tooltip="$t('test_groups.code_help')"
            :validate-status="form.errors.code ? 'error' : ''"
            :help="form.errors.code"
        >
            <Input :value="form.code" disabled :placeholder="$t('test_groups.code_placeholder')" />
        </FormItem>

        <FormItem
            :label="$t('test_groups.sort_order')"
            :tooltip="$t('test_groups.sort_order_help')"
            :validate-status="form.errors.sort_order ? 'error' : ''"
            :help="form.errors.sort_order"
        >
            <InputNumber v-model:value="form.sort_order" :min="0" :max="99999" style="width:160px" />
        </FormItem>

        <FormItem
            v-if="isEdit"
            :label="$t('test_groups.is_active')"
            :tooltip="$t('test_groups.is_active_help')"
            :validate-status="form.errors.is_active ? 'error' : ''"
            :help="form.errors.is_active"
        >
            <Space>
                <Switch v-model:checked="form.is_active" />
                <span class="state-label">
                    {{ form.is_active ? $t('global.active') : $t('global.inactive') }}
                </span>
            </Space>
        </FormItem>
    </FormModal>
</template>

<style scoped>
.state-label {
    font-size: 0.875rem;
    color: var(--color-text);
    font-weight: 500;
}
</style>
