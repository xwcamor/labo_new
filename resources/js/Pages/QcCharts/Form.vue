<script setup>
/**
 * Alta y edición de una carta de control.
 *
 * Los cinco límites se guardan explícitos y no solo la media y el desvío,
 * porque a veces los fija la norma o el criterio del laboratorio. Quien
 * prefiera derivarlos activa el cálculo automático y los cuatro extremos pasan
 * a ser lectura.
 */
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Alert, DatePicker, Form, FormItem, Input, InputNumber, Select, SelectOption,
    Space, Switch, Textarea,
} from 'ant-design-vue';
import { DotChartOutlined } from '@ant-design/icons-vue';
import dayjs from 'dayjs';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import { deriveLimits, fmtNumber, toNumber } from '@/Components/QcCharts/limits';

defineOptions({ layout: AppLayout });

const props = defineProps({
    chart:    { type: Object, default: null },
    tests:    { type: Array,  default: () => [] },
    analytes: { type: Array,  default: () => [] },
    modes:    { type: Array,  default: () => [] },
});

const isEdit = computed(() => !!props.chart);

const form = useForm({
    test_definition_id:  props.chart?.test_definition_id ?? null,
    test_field_id:       props.chart?.test_field_id ?? null,
    analyte_id:          props.chart?.analyte_id ?? null,
    label:               props.chart?.label ?? '',
    control_lot:         props.chart?.control_lot ?? '',

    ucl:                 toNumber(props.chart?.ucl),
    uwl:                 toNumber(props.chart?.uwl),
    center:              toNumber(props.chart?.center),
    lwl:                 toNumber(props.chart?.lwl),
    lcl:                 toNumber(props.chart?.lcl),

    sd:                  toNumber(props.chart?.sd),
    is_derived:          props.chart?.is_derived ?? false,
    warn_sigma:          toNumber(props.chart?.warn_sigma) ?? 2,
    action_sigma:        toNumber(props.chart?.action_sigma) ?? 3,

    repeatability_limit: toNumber(props.chart?.repeatability_limit),
    repeatability_mode:  props.chart?.repeatability_mode ?? (props.modes[0] ?? 'absolute'),

    effective_from:      props.chart?.effective_from ?? null,
    effective_to:        props.chart?.effective_to ?? null,

    comment:             props.chart?.comment ?? '',
    is_active:           props.chart?.is_active ?? true,
});

// ─── Prueba → sus columnas ───────────────────────────────────────────────
const selectedTest = computed(() =>
    props.tests.find((t) => t.id === form.test_definition_id) ?? null);

const testFields = computed(() => selectedTest.value?.fields ?? []);

// Al cambiar de prueba, la columna elegida deja de existir en la nueva: se
// limpia en vez de quedar apuntando a una columna de otra prueba. Es justo el
// cruce que el sistema viejo dejaba pasar en silencio.
watch(() => form.test_definition_id, (id, previous) => {
    if (previous === undefined || id === previous) return;
    if (!testFields.value.some((f) => f.id === form.test_field_id)) {
        form.test_field_id = null;
    }
});

// ─── Derivación de los cuatro extremos ───────────────────────────────────
const derived = computed(() => deriveLimits({
    center: form.center,
    sd: form.sd,
    warn_sigma: form.warn_sigma,
    action_sigma: form.action_sigma,
}));

/** Lo que se muestra en cada extremo: el derivado manda cuando está activo. */
const shown = (key) => (form.is_derived ? (derived.value[key] ?? null) : form[key]);

/**
 * El servidor exige que la alerta esté por DENTRO del control. Al revés la
 * carta queda invertida y no avisa nunca, así que se ataja antes de enviar en
 * lugar de gastar un viaje para que vuelva rechazado.
 */
const sigmaInvalid = computed(() => {
    const warn = toNumber(form.warn_sigma);
    const action = toNumber(form.action_sigma);
    return warn !== null && action !== null && warn >= action;
});

const clientError = ref(false);

const dateValue = (raw) => (raw ? dayjs(raw) : null);

const submit = () => {
    if (sigmaInvalid.value) {
        clientError.value = true;
        return;
    }
    clientError.value = false;

    form
        .transform((data) => ({
            ...data,
            // Con la derivación activa se envían los extremos calculados para
            // que las columnas guardadas no queden contradiciendo a la media y
            // el desvío. El número que finalmente rige lo recalcula el servidor.
            ...(data.is_derived ? derived.value : {}),
        }))
        .submit(
            isEdit.value ? 'put' : 'post',
            isEdit.value
                ? route('lab_management.qc_charts.update', props.chart.slug)
                : route('lab_management.qc_charts.store'),
        );
};
</script>

<template>
    <Head :title="isEdit ? $t('qc_charts.edit') : $t('qc_charts.create')" />

    <div class="form-page sap-form">
        <SectionHeader
            :back-href="route('lab_management.qc_charts.index')"
            :title="isEdit ? $t('qc_charts.edit') : $t('qc_charts.create')"
            :subtitle="$t('qc_charts.intro')"
        >
            <template #icon><DotChartOutlined /></template>
        </SectionHeader>

        <div class="form-body">
            <!-- `layout="vertical"` + `.form-grid`: el rótulo va ARRIBA del
                 campo y los campos se acomodan en varias columnas. Una carta
                 tiene veintiún datos; con el rótulo a la izquierda y uno por
                 fila la página se iba a varias pantallas sin que entrara una
                 sección completa. -->
            <Form layout="vertical" @submit.prevent="submit">
                <Alert
                    v-if="(form.hasErrors && Object.keys(form.errors).length > 0) || clientError"
                    type="error"
                    show-icon
                    :message="$t('global.fix_marked_fields')"
                    class="qcf-alert"
                />

                <!-- ── Qué se controla ─────────────────────────────────── -->
                <h2 class="form-section-title">{{ $t('global.general_data') }}</h2>
                <div class="form-grid">
                    <FormItem
                        :label="$t('qc_charts.test_definition')"
                        required
                        :validate-status="form.errors.test_definition_id ? 'error' : ''"
                        :help="form.errors.test_definition_id"
                    >
                        <Select
                            v-model:value="form.test_definition_id"
                            show-search
                            option-filter-prop="label"
                            :placeholder="$t('global.placeholders.select_attribute', { attribute: $t('qc_charts.test_definition') })"
                        >
                            <SelectOption v-for="test in tests" :key="test.id" :value="test.id" :label="test.name">
                                {{ test.name }}
                            </SelectOption>
                        </Select>
                    </FormItem>

                    <FormItem
                        :label="$t('qc_charts.test_field')"
                        :validate-status="form.errors.test_field_id ? 'error' : ''"
                        :help="form.errors.test_field_id"
                    >
                        <Select
                            v-model:value="form.test_field_id"
                            allow-clear
                            show-search
                            option-filter-prop="label"
                            :disabled="!form.test_definition_id"
                            :placeholder="$t('global.placeholders.select_attribute', { attribute: $t('qc_charts.test_field') })"
                        >
                            <SelectOption v-for="field in testFields" :key="field.id" :value="field.id" :label="field.label">
                                {{ field.label }}
                                <span v-if="field.unit" class="qcf-dim">({{ field.unit }})</span>
                            </SelectOption>
                        </Select>
                    </FormItem>

                    <FormItem
                        :label="$t('qc_charts.analyte')"
                        :validate-status="form.errors.analyte_id ? 'error' : ''"
                        :help="form.errors.analyte_id"
                    >
                        <Select
                            v-model:value="form.analyte_id"
                            allow-clear
                            show-search
                            option-filter-prop="label"
                            :placeholder="$t('global.placeholders.select_attribute', { attribute: $t('qc_charts.analyte') })"
                        >
                            <SelectOption v-for="a in analytes" :key="a.id" :value="a.id" :label="a.name">
                                {{ a.name }}
                                <span v-if="a.unit" class="qcf-dim">({{ a.unit }})</span>
                            </SelectOption>
                        </Select>
                    </FormItem>

                    <FormItem
                        :label="$t('qc_charts.label')"
                        :validate-status="form.errors.label ? 'error' : ''"
                        :help="form.errors.label"
                    >
                        <Input v-model:value="form.label" :maxlength="150" />
                    </FormItem>

                    <FormItem
                        :label="$t('qc_charts.control_lot')"
                        :validate-status="form.errors.control_lot ? 'error' : ''"
                        :help="form.errors.control_lot"
                    >
                        <Input v-model:value="form.control_lot" :maxlength="100" />
                    </FormItem>
                </div>

                <!-- ── Los cinco límites ───────────────────────────────── -->
                <h2 class="form-section-title form-section-title--spaced">{{ $t('qc_charts.limits_title') }}</h2>
                <div class="form-grid">
                    <Alert type="info" show-icon :message="$t('qc_charts.limits_help')" class="form-grid__wide qcf-alert" />

                    <FormItem
                        :label="$t('qc_charts.limits.lcs')"
                        :validate-status="form.errors.ucl ? 'error' : ''"
                        :help="form.errors.ucl"
                    >
                        <InputNumber
                            :value="shown('ucl')"
                            @update:value="(v) => form.ucl = v"
                            :disabled="form.is_derived"
                            class="qcf-number"
                        />
                    </FormItem>

                    <FormItem
                        :label="$t('qc_charts.limits.las')"
                        :validate-status="form.errors.uwl ? 'error' : ''"
                        :help="form.errors.uwl"
                    >
                        <InputNumber
                            :value="shown('uwl')"
                            @update:value="(v) => form.uwl = v"
                            :disabled="form.is_derived"
                            class="qcf-number"
                        />
                    </FormItem>

                    <FormItem
                        :label="$t('qc_charts.limits.lc')"
                        :tooltip="$t('qc_charts.center')"
                        :validate-status="form.errors.center ? 'error' : ''"
                        :help="form.errors.center"
                    >
                        <InputNumber v-model:value="form.center" class="qcf-number" />
                    </FormItem>

                    <FormItem
                        :label="$t('qc_charts.limits.lai')"
                        :validate-status="form.errors.lwl ? 'error' : ''"
                        :help="form.errors.lwl"
                    >
                        <InputNumber
                            :value="shown('lwl')"
                            @update:value="(v) => form.lwl = v"
                            :disabled="form.is_derived"
                            class="qcf-number"
                        />
                    </FormItem>

                    <FormItem
                        :label="$t('qc_charts.limits.lci')"
                        :validate-status="form.errors.lcl ? 'error' : ''"
                        :help="form.errors.lcl"
                    >
                        <InputNumber
                            :value="shown('lcl')"
                            @update:value="(v) => form.lcl = v"
                            :disabled="form.is_derived"
                            class="qcf-number"
                        />
                    </FormItem>

                    <FormItem
                        :label="$t('qc_charts.sd')"
                        :validate-status="form.errors.sd ? 'error' : ''"
                        :help="form.errors.sd"
                    >
                        <InputNumber v-model:value="form.sd" :min="0" class="qcf-number" />
                    </FormItem>

                    <FormItem :label="$t('qc_charts.is_derived')">
                        <Space>
                            <Switch v-model:checked="form.is_derived" />
                            <span class="qcf-state">
                                {{ form.is_derived ? $t('global.yes') : $t('global.no') }}
                            </span>
                        </Space>
                    </FormItem>

                    <FormItem
                        :label="$t('qc_charts.warn_sigma')"
                        :validate-status="(form.errors.warn_sigma || sigmaInvalid) ? 'error' : ''"
                        :help="form.errors.warn_sigma || $t('qc_charts.sigma_help')"
                    >
                        <InputNumber
                            v-model:value="form.warn_sigma"
                            :min="0.5"
                            :max="10"
                            :step="0.5"
                            class="qcf-number"
                        />
                    </FormItem>

                    <FormItem
                        :label="$t('qc_charts.action_sigma')"
                        :validate-status="(form.errors.action_sigma || sigmaInvalid) ? 'error' : ''"
                        :help="form.errors.action_sigma"
                    >
                        <InputNumber
                            v-model:value="form.action_sigma"
                            :min="0.5"
                            :max="10"
                            :step="0.5"
                            class="qcf-number"
                        />
                    </FormItem>

                    <!-- Vista previa: lo que quedaría si se guarda con la
                         derivación activa. La cuenta definitiva es la del servidor. -->
                    <FormItem v-if="form.is_derived" class="form-grid__wide">
                        <ul class="qcf-preview">
                            <li>
                                <span>{{ $t('qc_charts.limits_short.lcs') }}</span>
                                <b>{{ fmtNumber(derived.ucl) }}</b>
                            </li>
                            <li>
                                <span>{{ $t('qc_charts.limits_short.las') }}</span>
                                <b>{{ fmtNumber(derived.uwl) }}</b>
                            </li>
                            <li>
                                <span>{{ $t('qc_charts.limits_short.lc') }}</span>
                                <b>{{ fmtNumber(form.center) }}</b>
                            </li>
                            <li>
                                <span>{{ $t('qc_charts.limits_short.lai') }}</span>
                                <b>{{ fmtNumber(derived.lwl) }}</b>
                            </li>
                            <li>
                                <span>{{ $t('qc_charts.limits_short.lci') }}</span>
                                <b>{{ fmtNumber(derived.lcl) }}</b>
                            </li>
                        </ul>
                    </FormItem>
                </div>

                <!-- ── Vigencia ────────────────────────────────────────── -->
                <h2 class="form-section-title form-section-title--spaced">{{ $t('qc_charts.validity') }}</h2>
                <div class="form-grid">
                    <Alert type="info" show-icon :message="$t('qc_charts.validity_help')" class="form-grid__wide qcf-alert" />

                    <FormItem
                        :label="$t('qc_charts.effective_from')"
                        :validate-status="form.errors.effective_from ? 'error' : ''"
                        :help="form.errors.effective_from"
                    >
                        <DatePicker
                            autocomplete="off"
                            :value="dateValue(form.effective_from)"
                            @update:value="(d) => form.effective_from = d ? d.format('YYYY-MM-DD') : null"
                            format="YYYY-MM-DD"
                            class="qcf-number"
                        />
                    </FormItem>

                    <FormItem
                        :label="$t('qc_charts.effective_to')"
                        :validate-status="form.errors.effective_to ? 'error' : ''"
                        :help="form.errors.effective_to"
                    >
                        <DatePicker
                            autocomplete="off"
                            :value="dateValue(form.effective_to)"
                            @update:value="(d) => form.effective_to = d ? d.format('YYYY-MM-DD') : null"
                            format="YYYY-MM-DD"
                            class="qcf-number"
                        />
                    </FormItem>
                </div>

                <!-- ── Repetibilidad ───────────────────────────────────── -->
                <h2 class="form-section-title form-section-title--spaced">{{ $t('qc_charts.duplicates') }}</h2>
                <div class="form-grid">
                    <Alert type="info" show-icon :message="$t('qc_charts.repeatability_help')" class="form-grid__wide qcf-alert" />

                    <FormItem
                        :label="$t('qc_charts.repeatability_limit')"
                        :validate-status="form.errors.repeatability_limit ? 'error' : ''"
                        :help="form.errors.repeatability_limit"
                    >
                        <InputNumber v-model:value="form.repeatability_limit" :min="0" class="qcf-number" />
                    </FormItem>

                    <FormItem
                        :label="$t('qc_charts.repeatability_mode')"
                        :validate-status="form.errors.repeatability_mode ? 'error' : ''"
                        :help="form.errors.repeatability_mode"
                    >
                        <Select v-model:value="form.repeatability_mode">
                            <SelectOption v-for="mode in modes" :key="mode" :value="mode">
                                {{ $t(`qc_charts.mode.${mode}`) }}
                            </SelectOption>
                        </Select>
                    </FormItem>
                </div>

                <!-- ── Cierre ──────────────────────────────────────────── -->
                <h2 class="form-section-title form-section-title--spaced">{{ $t('qc_charts.comment') }}</h2>
                <div class="form-grid">
                    <FormItem
                        class="form-grid__wide"
                        :label="$t('qc_charts.comment')"
                        :validate-status="form.errors.comment ? 'error' : ''"
                        :help="form.errors.comment"
                    >
                        <Textarea v-model:value="form.comment" :rows="3" :maxlength="2000" show-count />
                    </FormItem>

                    <FormItem :label="$t('qc_charts.is_active')">
                        <Space>
                            <Switch v-model:checked="form.is_active" />
                            <span class="qcf-state">
                                {{ form.is_active ? $t('global.active') : $t('global.inactive') }}
                            </span>
                        </Space>
                    </FormItem>
                </div>

                <FormFooter
                    :cancel-href="route('lab_management.qc_charts.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="qc_charts.create"
                    floating
                />
            </Form>
        </div>
    </div>
</template>

<style scoped>
.qcf-alert  { margin-bottom: 16px; }
/* Sin max-width: la columna de la rejilla ya acota el ancho del campo. */
.qcf-number { width: 100%; }
.qcf-state  { font-size: 0.875rem; color: var(--color-text); font-weight: 500; }
.qcf-dim    { color: var(--color-text-muted); }

/* Vista previa de la derivación: cinco pares sigla/valor, alineados con los
   campos para que se lean como el mismo bloque. */
.qcf-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    margin: 0;
    padding: 10px 14px;
    list-style: none;
    border: 1px dashed var(--color-border);
    border-radius: 8px;
    background: var(--color-surface);
}
.qcf-preview li {
    display: flex;
    flex-direction: column;
    line-height: 1.3;
}
.qcf-preview span {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: var(--color-text-muted);
}
.qcf-preview b {
    font-size: 0.875rem;
    font-variant-numeric: tabular-nums;
    color: var(--color-text);
}
</style>
