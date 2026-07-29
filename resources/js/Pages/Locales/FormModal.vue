<script setup>
/**
 * Alta y edición del locale, en diálogo (regla Fiori de la casa: menos de 7
 * campos no merece página completa). Los campos son los mismos del Form.vue de
 * página completa, que queda como respaldo.
 *
 * `languageOptions` viene de la página anfitriona (Index o Show): el controller
 * las pasa en index()/show() además de create()/edit(), para que el diálogo no
 * tenga que ir a buscarlas.
 */
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { FormItem, Input, Select, Switch, Space } from 'ant-design-vue';
import FormModal from '@/Components/Common/FormModal.vue';
import { useI18n } from '@/Plugins/i18n';

const props = defineProps({
    open:   { type: Boolean, default: false },
    // El registro a editar; null = alta.
    record: { type: Object,  default: null },
    // Catálogo de idiomas activos [{ value, label }] para el Select.
    languageOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const { t } = useI18n();

const isEdit = computed(() => !!props.record);

const form = useForm({
    name:        '',
    code:        '',
    language_id: null,
    is_active:   true,
});

// El diálogo se reusa entre aperturas: al abrir se carga el registro (o se
// limpia para el alta) y se descartan los errores de la vez anterior.
watch(() => props.open, (abierto) => {
    if (!abierto) return;
    form.clearErrors();
    form.name        = props.record?.name ?? '';
    form.code        = props.record?.code ?? '';
    form.language_id = props.record?.language_id ?? null;
    form.is_active   = props.record?.is_active ?? true;
});

const title = computed(() => (isEdit.value
    ? `${t('global.edit')} ${t('locales.record')}`
    : t('locales.new')));

const submit = () => {
    const opciones = { preserveScroll: true, onSuccess: () => emit('close') };

    if (isEdit.value) {
        form.put(route('system_management.locales.update', props.record.slug), opciones);
    } else {
        form.post(route('system_management.locales.store'), opciones);
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
        create-label-key="locales.new"
        @close="emit('close')"
        @submit="submit"
    >
        <FormItem
            :label="$t('locales.name')"
            :tooltip="$t('locales.name_help')"
            required
            :validate-status="form.errors.name ? 'error' : ''"
            :help="form.errors.name"
        >
            <Input
                v-model:value="form.name"
                :maxlength="255"
                showCount
                autofocus
                :placeholder="$t('locales.name_placeholder')"
            />
        </FormItem>

        <FormItem
            :label="$t('locales.code')"
            :tooltip="$t('locales.code_help')"
            required
            :validate-status="form.errors.code ? 'error' : ''"
            :help="form.errors.code"
        >
            <Input
                v-model:value="form.code"
                :maxlength="10"
                :placeholder="$t('locales.code_placeholder')"
            />
        </FormItem>

        <FormItem
            :label="$t('locales.language')"
            :tooltip="$t('locales.language_help')"
            required
            :validate-status="form.errors.language_id ? 'error' : ''"
            :help="form.errors.language_id"
        >
            <Select
                v-model:value="form.language_id"
                :options="languageOptions"
                :placeholder="$t('locales.language_placeholder')"
                show-search
                option-filter-prop="label"
            />
        </FormItem>

        <FormItem
            v-if="isEdit"
            :label="$t('locales.is_active')"
            :tooltip="$t('locales.is_active_help')"
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
