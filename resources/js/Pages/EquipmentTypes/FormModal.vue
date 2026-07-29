<script setup>
/**
 * Alta y edición del tipo de equipo, en diálogo (regla Fiori de la casa:
 * menos de 7 campos no merece página completa).
 *
 * La FORMA (tank/pole/dry) no es decorativa: decide el glifo con el que el
 * equipo se dibuja en toda la aplicación, por eso el select va acompañado de
 * la vista previa — elegir "a ciegas" producía tipos con el dibujo equivocado
 * que nadie corregía después.
 */
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { FormItem, Input, Select, Switch, Space } from 'ant-design-vue';
import FormModal from '@/Components/Common/FormModal.vue';
import EquipmentGlyph from '@/Components/Equipment/EquipmentGlyph.vue';
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
    shape:     'tank',
    is_active: true,
});

// El diálogo se reusa entre aperturas: al abrir se carga el registro (o se
// limpia para el alta) y se descartan los errores de la vez anterior.
watch(() => props.open, (abierto) => {
    if (!abierto) return;
    form.clearErrors();
    form.name      = props.record?.name ?? '';
    form.code      = props.record?.code ?? '';
    form.shape     = props.record?.shape ?? 'tank';
    form.is_active = props.record?.is_active ?? true;
});

const title = computed(() => (isEdit.value
    ? `${t('global.edit')} ${t('equipment_types.record')}`
    : t('equipment_types.new')));

const submit = () => {
    const opciones = { preserveScroll: true, onSuccess: () => emit('close') };

    if (isEdit.value) {
        form.put(route('business_management.equipment_types.update', props.record.slug), opciones);
    } else {
        form.post(route('business_management.equipment_types.store'), opciones);
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
        create-label-key="equipment_types.new"
        @close="emit('close')"
        @submit="submit"
    >
        <FormItem
            :label="$t('equipment_types.name')"
            :tooltip="$t('equipment_types.name_help')"
            required
            :validate-status="form.errors.name ? 'error' : ''"
            :help="form.errors.name"
        >
            <Input
                v-model:value="form.name"
                :maxlength="255"
                showCount
                autofocus
                :placeholder="$t('equipment_types.name_placeholder')"
            />
        </FormItem>

        <FormItem
            :label="$t('equipment_types.code')"
            :tooltip="$t('equipment_types.code_help')"
            :validate-status="form.errors.code ? 'error' : ''"
            :help="form.errors.code"
        >
            <Input
                v-model:value="form.code"
                :maxlength="40"
                :placeholder="$t('equipment_types.code')"
            />
        </FormItem>

        <FormItem
            :label="$t('equipment_types.shape')"
            :tooltip="$t('equipment_types.shape_help')"
            :validate-status="form.errors.shape ? 'error' : ''"
            :help="form.errors.shape"
        >
            <Select
                v-model:value="form.shape"
                :options="[
                    { value: 'tank', label: $t('equipment_types.shape_tank') },
                    { value: 'pole', label: $t('equipment_types.shape_pole') },
                    { value: 'dry',  label: $t('equipment_types.shape_dry') },
                ]"
            />
            <!-- Vista previa del glifo: mismo dibujo que verá el módulo de
                 equipos. Vive DENTRO del FormItem para quedar pegada al select
                 que la controla. -->
            <div class="shape-preview">
                <EquipmentGlyph :shape="form.shape" :phases="'three'" />
                <span class="shape-preview__cap">{{ $t('equipment_types.shape_preview') }}</span>
            </div>
        </FormItem>

        <FormItem
            v-if="isEdit"
            :label="$t('equipment_types.is_active')"
            :tooltip="$t('equipment_types.is_active_help')"
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
.shape-preview {
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}
.shape-preview > :first-child { width: 88px; height: 88px; }
.shape-preview__cap { font-size: 0.72rem; color: var(--color-text-muted, #6A6D70); }
</style>
