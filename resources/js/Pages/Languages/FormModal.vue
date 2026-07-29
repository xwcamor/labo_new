<script setup>
/**
 * Alta y edición del idioma, en diálogo (regla Fiori de la casa: menos de 7
 * campos no merece página completa). Los campos son los mismos del Form.vue de
 * página completa, que queda como respaldo.
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
    iso_code:  '',
    is_active: true,
});

// El diálogo se reusa entre aperturas: al abrir se carga el registro (o se
// limpia para el alta) y se descartan los errores de la vez anterior.
watch(() => props.open, (abierto) => {
    if (!abierto) return;
    form.clearErrors();
    form.name      = props.record?.name ?? '';
    form.iso_code  = props.record?.iso_code ?? '';
    form.is_active = props.record?.is_active ?? true;
});

const title = computed(() => (isEdit.value
    ? `${t('global.edit')} ${t('languages.record')}`
    : t('languages.new')));

const submit = () => {
    const opciones = { preserveScroll: true, onSuccess: () => emit('close') };

    if (isEdit.value) {
        form.put(route('system_management.languages.update', props.record.slug), opciones);
    } else {
        form.post(route('system_management.languages.store'), opciones);
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
        create-label-key="languages.new"
        @close="emit('close')"
        @submit="submit"
    >
        <FormItem
            :label="$t('languages.name')"
            :tooltip="$t('languages.name_help')"
            required
            :validate-status="form.errors.name ? 'error' : ''"
            :help="form.errors.name"
        >
            <Input
                v-model:value="form.name"
                :maxlength="255"
                showCount
                autofocus
                :placeholder="$t('languages.name_placeholder')"
            />
        </FormItem>

        <!-- El help siempre visible (igual que el Form de página): el formato
             ISO no es obvio y el error del servidor lo reemplaza al fallar. -->
        <FormItem
            :label="$t('languages.iso_code')"
            :tooltip="$t('languages.iso_code_help')"
            required
            :validate-status="form.errors.iso_code ? 'error' : ''"
            :help="form.errors.iso_code || $t('languages.iso_code_help')"
        >
            <Input
                v-model:value="form.iso_code"
                :maxlength="10"
                :placeholder="$t('languages.iso_code_placeholder')"
            />
        </FormItem>

        <FormItem
            v-if="isEdit"
            :label="$t('languages.is_active')"
            :tooltip="$t('languages.is_active_help')"
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
