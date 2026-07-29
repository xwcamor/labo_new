<script setup>
/**
 * Alta y edición del parámetro (analito), en diálogo (regla Fiori de la casa:
 * menos de 7 campos no merece página completa).
 *
 * El registro en sí es mínimo (nombre + código + estado): dónde se MIDE el
 * parámetro y contra qué límites se juzga viven en Pruebas y en los cuadros
 * de límites, no aquí. Los registros bloqueados (Lockable) o globales vistos
 * por un no-super nunca llegan a este diálogo: el botón de editar ya no se
 * ofrece en la fila ni en la ficha, y el servidor lo rechaza igual.
 */
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { FormItem, Input, Switch, Space } from 'ant-design-vue';
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
    name:      '',
    code:      '',
    is_active: true,
});

// El diálogo se reusa entre aperturas: al abrir se carga el registro (o se
// limpia para el alta) y se descartan los errores de la vez anterior.
watch(() => props.open, (abierto) => {
    if (!abierto) return;
    form.clearErrors();
    form.name      = props.record?.name ?? '';
    form.code      = props.record?.code ?? '';
    form.is_active = props.record?.is_active ?? true;
});

const title = computed(() => (isEdit.value
    ? `${t('global.edit')} ${t('analytes.record')}`
    : t('analytes.new')));

const submit = () => {
    const opciones = { preserveScroll: true, onSuccess: () => emit('close') };

    if (isEdit.value) {
        form.put(route('business_management.analytes.update', props.record.slug), opciones);
    } else {
        form.post(route('business_management.analytes.store'), opciones);
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
        create-label-key="analytes.new"
        @close="emit('close')"
        @submit="submit"
    >
        <FormItem
            :label="$t('analytes.name')"
            :tooltip="$t('analytes.name_help')"
            required
            :validate-status="form.errors.name ? 'error' : ''"
            :help="form.errors.name"
        >
            <Input
                v-model:value="form.name"
                :maxlength="255"
                showCount
                autofocus
                :placeholder="$t('analytes.name_placeholder')"
            />
        </FormItem>

        <FormItem
            :label="$t('analytes.code')"
            :tooltip="$t('analytes.code_help')"
            :validate-status="form.errors.code ? 'error' : ''"
            :help="form.errors.code"
        >
            <Input
                v-model:value="form.code"
                :maxlength="40"
                :placeholder="$t('analytes.code')"
            />
        </FormItem>

        <FormItem
            v-if="isEdit"
            :label="$t('analytes.is_active')"
            :tooltip="$t('analytes.is_active_help')"
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
