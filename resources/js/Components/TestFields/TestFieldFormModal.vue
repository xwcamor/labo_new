<script setup>
/**
 * Alta y edición de una columna de la hoja de trabajo.
 *
 * El campo importante de esta pantalla es el ROL. El sistema Rails viejo lo
 * deducía de la POSICIÓN: la columna 1 era el número de muestra, la 2 la norma
 * y la última el resultado. Nada de eso estaba declarado, así que insertar una
 * columna en el medio rompía en silencio el enlace con la muestra, la norma del
 * informe y el gráfico de tendencias. Declararlo es lo que convierte al orden
 * en una decisión estética.
 */
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    Alert, Checkbox, Form, FormItem, Input, InputNumber, Modal, Select, SelectOption, Space,
} from 'ant-design-vue';

import FieldOptionsEditor from '@/Components/TestFields/FieldOptionsEditor.vue';
import FormulaField from '@/Components/TestFields/FormulaField.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** Slug de la prueba a la que pertenece la columna. */
    definitionSlug: { type: String, required: true },
    /** La columna que se edita, o null para un alta. */
    field: { type: Object, default: null },
    /** config/lab_field_types.php, tal cual lo manda el controlador. */
    fieldTypes: { type: Object, default: () => ({}) },
    roles: { type: Array, default: () => [] },
    analytes: { type: Array, default: () => [] },
    /** Posición sugerida para una columna nueva (al final). */
    nextOrder: { type: Number, default: 1 },
});

const emit = defineEmits(['update:open']);

const isEdit = computed(() => !!props.field);

const form = useForm({
    code:              props.field?.code ?? '',
    label:             props.field?.label ?? '',
    type:              props.field?.type ?? 'text',
    role:              props.field?.role ?? 'none',
    unit:              props.field?.unit ?? '',
    decimals:          props.field?.decimals ?? null,
    min_value:         props.field?.min_value ?? null,
    max_value:         props.field?.max_value ?? null,
    is_required:       props.field?.is_required ?? false,
    is_locked:         props.field?.is_locked ?? false,
    is_reusable:       props.field?.is_reusable ?? false,
    default_value:     props.field?.default_value ?? '',
    report_visible:    props.field?.report_visible ?? false,
    replicates:        props.field?.replicates ?? 1,
    formula:           props.field?.formula ?? '',
    output_analyte_id: props.field?.output_analyte_id ?? null,
    sort_order:        props.field?.sort_order ?? props.nextOrder,
    options:           (props.field?.options ?? []).map((o) => ({
        id: o.id,
        value: o.value,
        sort_order: o.sort_order,
        is_hidden: !!o.is_hidden,
        accreditation_flag: o.accreditation_flag ?? null,
    })),
});

const typeKeys = computed(() => Object.keys(props.fieldTypes ?? {}));

const typeMeta = computed(() => props.fieldTypes?.[form.type] ?? {});

const acceptsOptions = computed(() => !!typeMeta.value.options);

/**
 * La fórmula se ofrece si el tipo la admite, y también si la columna YA trae
 * una: en la importación del sistema viejo hay columnas con fórmula que no
 * están rotuladas como calculadas, y esconder el campo dejaría esa fórmula
 * activa e ineditable.
 */
const acceptsFormula = computed(() => !!typeMeta.value.formula || !!props.field?.formula);

const isResult = computed(() => form.role === 'result');

const close = () => emit('update:open', false);

const submit = () => {
    const options = { preserveScroll: true, onSuccess: close };

    if (isEdit.value) {
        form.put(
            route('lab_management.test_definitions.fields.update', [props.definitionSlug, props.field.id]),
            options,
        );
    } else {
        form.post(
            route('lab_management.test_definitions.fields.store', props.definitionSlug),
            options,
        );
    }
};
</script>

<template>
    <Modal
        :open="open"
        :title="isEdit ? $t('test_fields.edit') : $t('test_fields.create')"
        :confirm-loading="form.processing"
        :ok-text="isEdit ? $t('global.save_changes') : $t('global.create')"
        :cancel-text="$t('global.cancel')"
        width="760px"
        @ok="submit"
        @cancel="close"
        @update:open="(v) => emit('update:open', v)"
    >
        <Alert
            v-if="form.hasErrors && Object.keys(form.errors).length > 0"
            type="error"
            show-icon
            :message="$t('global.fix_marked_fields')"
            class="tff__alert"
        />

        <Form layout="vertical">
            <div class="tff__grid">
                <FormItem
                    :label="$t('test_fields.label')"
                    :tooltip="$t('test_fields.label_help')"
                    required
                    :validate-status="form.errors.label ? 'error' : ''"
                    :help="form.errors.label"
                >
                    <Input v-model:value="form.label" :maxlength="200" />
                </FormItem>

                <FormItem
                    :label="$t('test_fields.code')"
                    :tooltip="$t('test_fields.code_help')"
                    required
                    :validate-status="form.errors.code ? 'error' : ''"
                    :help="form.errors.code || $t('test_fields.code_help')"
                >
                    <Input v-model:value="form.code" :maxlength="60" class="tff__mono" />
                </FormItem>

                <FormItem
                    :label="$t('test_fields.type')"
                    :validate-status="form.errors.type ? 'error' : ''"
                    :help="form.errors.type || $t(`test_fields.types_help.${form.type}`)"
                >
                    <Select v-model:value="form.type">
                        <SelectOption v-for="key in typeKeys" :key="key" :value="key">
                            {{ $t(`test_fields.types.${key}`) }}
                        </SelectOption>
                    </Select>
                </FormItem>

                <FormItem
                    :label="$t('test_fields.role')"
                    :tooltip="$t('test_fields.role_help_intro')"
                    :validate-status="form.errors.role ? 'error' : ''"
                    :help="form.errors.role || $t(`test_fields.roles_help.${form.role}`)"
                >
                    <Select v-model:value="form.role">
                        <SelectOption v-for="role in roles" :key="role" :value="role">
                            {{ $t(`test_fields.roles.${role}`) }}
                        </SelectOption>
                    </Select>
                </FormItem>

                <FormItem
                    v-if="isResult"
                    class="tff__span"
                    :label="$t('test_fields.output_analyte')"
                    :tooltip="$t('test_fields.output_analyte_help')"
                    :validate-status="form.errors.output_analyte_id ? 'error' : ''"
                    :help="form.errors.output_analyte_id"
                >
                    <Select
                        v-model:value="form.output_analyte_id"
                        allow-clear
                        show-search
                        option-filter-prop="label"
                        :placeholder="$t('global.placeholders.select_attribute', { attribute: $t('test_fields.output_analyte') })"
                    >
                        <SelectOption v-for="a in analytes" :key="a.id" :value="a.id" :label="a.name">
                            {{ a.name }}
                            <span v-if="a.unit" class="tff__dim">({{ a.unit }})</span>
                        </SelectOption>
                    </Select>
                </FormItem>

                <FormItem
                    :label="$t('test_fields.unit')"
                    :validate-status="form.errors.unit ? 'error' : ''"
                    :help="form.errors.unit"
                >
                    <Input v-model:value="form.unit" :maxlength="30" />
                </FormItem>

                <FormItem
                    :label="$t('test_fields.decimals')"
                    :validate-status="form.errors.decimals ? 'error' : ''"
                    :help="form.errors.decimals"
                >
                    <InputNumber v-model:value="form.decimals" :min="0" :max="10" class="tff__num" />
                </FormItem>

                <FormItem
                    :label="$t('test_fields.min_value')"
                    :tooltip="$t('test_fields.range_help')"
                    :validate-status="form.errors.min_value ? 'error' : ''"
                    :help="form.errors.min_value"
                >
                    <InputNumber v-model:value="form.min_value" class="tff__num" />
                </FormItem>

                <FormItem
                    :label="$t('test_fields.max_value')"
                    :tooltip="$t('test_fields.range_help')"
                    :validate-status="form.errors.max_value ? 'error' : ''"
                    :help="form.errors.max_value"
                >
                    <InputNumber v-model:value="form.max_value" class="tff__num" />
                </FormItem>

                <FormItem
                    :label="$t('test_fields.replicates')"
                    :tooltip="$t('test_fields.replicates_help')"
                    :validate-status="form.errors.replicates ? 'error' : ''"
                    :help="form.errors.replicates"
                >
                    <InputNumber v-model:value="form.replicates" :min="1" :max="20" class="tff__num" />
                </FormItem>

                <FormItem
                    :label="$t('test_fields.sort_order')"
                    :validate-status="form.errors.sort_order ? 'error' : ''"
                    :help="form.errors.sort_order"
                >
                    <InputNumber v-model:value="form.sort_order" :min="0" class="tff__num" />
                </FormItem>

                <FormItem
                    :label="$t('test_fields.default_value')"
                    :tooltip="$t('test_fields.is_reusable_help')"
                    :validate-status="form.errors.default_value ? 'error' : ''"
                    :help="form.errors.default_value"
                >
                    <Input v-model:value="form.default_value" :maxlength="255" />
                </FormItem>

                <FormItem class="tff__span">
                    <Space :size="18" wrap>
                        <Checkbox v-model:checked="form.is_required">{{ $t('test_fields.is_required') }}</Checkbox>
                        <Checkbox v-model:checked="form.is_locked">{{ $t('test_fields.is_locked') }}</Checkbox>
                        <Checkbox v-model:checked="form.is_reusable">{{ $t('test_fields.is_reusable') }}</Checkbox>
                        <Checkbox v-model:checked="form.report_visible">{{ $t('test_fields.report_visible') }}</Checkbox>
                    </Space>
                    <p class="tff__hint">{{ $t('test_fields.report_visible_help') }}</p>
                </FormItem>
            </div>

            <FormItem
                v-if="acceptsFormula"
                :label="$t('test_fields.formula')"
                :validate-status="form.errors.formula ? 'error' : ''"
                :help="form.errors.formula"
            >
                <FormulaField
                    v-model="form.formula"
                    :code="form.code"
                    :definition-slug="definitionSlug"
                />
            </FormItem>

            <FormItem v-if="acceptsOptions" :label="$t('test_fields.options')">
                <FieldOptionsEditor v-model="form.options" />
            </FormItem>
        </Form>
    </Modal>
</template>

<style scoped>
.tff__alert { margin-bottom: 14px; }

.tff__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 16px;
}
.tff__span { grid-column: 1 / -1; }

.tff__mono { font-family: ui-monospace, Consolas, monospace; }
.tff__num  { width: 100%; }
.tff__dim  { color: var(--color-text-muted); }
.tff__hint {
    margin: 6px 0 0;
    font-size: 0.75rem;
    line-height: 1.45;
    color: var(--color-text-muted);
}

@media (max-width: 640px) {
    .tff__grid { grid-template-columns: minmax(0, 1fr); }
}
</style>
