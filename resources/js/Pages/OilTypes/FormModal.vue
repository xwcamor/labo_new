<script setup>
/**
 * Alta y edición del tipo de aceite, en diálogo (regla Fiori de la casa:
 * menos de 7 campos no merece página completa).
 *
 * "Copiar reglas de" no es un campo del registro: es una orden de una sola
 * vez que el servidor ejecuta después de guardar (clona los cuadros de
 * límites de otro aceite para que el nuevo no quede "Sin reglas"). Por eso
 * arranca SIEMPRE en null al abrir, y solo se ofrece cuando tiene sentido:
 * al crear, o al editar un aceite que todavía no tiene reglas propias (si ya
 * las tiene, copiarle otras las pisaría — mismo criterio que el controller).
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
    // Aceites que pueden servir de origen para copiar reglas. Los manda el
    // controller (index/show); vacío = no se ofrece el select.
    cloneSources: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const { t } = useI18n();

const isEdit = computed(() => !!props.record);

const form = useForm({
    name:             '',
    code:             '',
    is_active:        true,
    clone_rules_from: null,   // opcional: copiar reglas de un aceite existente
});

// El diálogo se reusa entre aperturas: al abrir se carga el registro (o se
// limpia para el alta) y se descartan los errores de la vez anterior.
watch(() => props.open, (abierto) => {
    if (!abierto) return;
    form.clearErrors();
    form.name             = props.record?.name ?? '';
    form.code             = props.record?.code ?? '';
    form.is_active        = props.record?.is_active ?? true;
    form.clone_rules_from = null;
});

// Copiarse de sí mismo no tiene sentido: al editar se saca el propio aceite
// de la lista (el listado manda las fuentes SIN excluir a nadie, porque el
// mismo diálogo sirve para cualquier fila).
const sources = computed(() =>
    props.cloneSources.filter((o) => o.id !== props.record?.id));

const showClone = computed(() =>
    sources.value.length > 0 && (!isEdit.value || !props.record?.has_rules));

const title = computed(() => (isEdit.value
    ? `${t('global.edit')} ${t('oil_types.record')}`
    : t('oil_types.new')));

const submit = () => {
    const opciones = { preserveScroll: true, onSuccess: () => emit('close') };

    if (isEdit.value) {
        form.put(route('business_management.oil_types.update', props.record.slug), opciones);
    } else {
        form.post(route('business_management.oil_types.store'), opciones);
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
        create-label-key="oil_types.new"
        @close="emit('close')"
        @submit="submit"
    >
        <FormItem
            :label="$t('oil_types.name')"
            :tooltip="$t('oil_types.name_help')"
            required
            :validate-status="form.errors.name ? 'error' : ''"
            :help="form.errors.name"
        >
            <Input
                v-model:value="form.name"
                :maxlength="255"
                showCount
                autofocus
                :placeholder="$t('oil_types.name_placeholder')"
            />
        </FormItem>

        <FormItem
            :label="$t('oil_types.code')"
            :tooltip="$t('oil_types.code_help')"
            :validate-status="form.errors.code ? 'error' : ''"
            :help="form.errors.code"
        >
            <Input
                v-model:value="form.code"
                :maxlength="40"
                :placeholder="$t('oil_types.code')"
            />
        </FormItem>

        <FormItem
            v-if="showClone"
            :label="$t('oil_types.clone_rules')"
            :tooltip="$t('oil_types.clone_rules_help')"
            :validate-status="form.errors.clone_rules_from ? 'error' : ''"
            :help="form.errors.clone_rules_from || $t('oil_types.clone_rules_hint')"
        >
            <Select
                v-model:value="form.clone_rules_from"
                allow-clear
                :placeholder="$t('oil_types.clone_rules_placeholder')"
                :options="sources.map(o => ({ value: o.id, label: o.name }))"
            />
        </FormItem>

        <FormItem
            v-if="isEdit"
            :label="$t('oil_types.is_active')"
            :tooltip="$t('oil_types.is_active_help')"
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
