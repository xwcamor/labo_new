<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Form, FormItem, Input, Textarea, InputNumber, Switch, Space, Alert, Select, Checkbox,
} from 'ant-design-vue';
import { FileDoneOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    testDefinition: { type: Object, default: null },
    // [{ value, label, code }] — las envía el controlador ya ordenadas.
    groups:         { type: Array,  default: () => [] },
    // Las familias que ya usan otras pruebas, para elegir en vez de tipear.
    families:       { type: Array,  default: () => [] },
});

const isEdit = computed(() => !!props.testDefinition);

/**
 * Con qué otras pruebas comparte tabla en el informe.
 *
 * Se ofrecen las familias que ya existen —"fisicoquimico" agrupa las trece
 * fisicoquímicas, como en el informe acreditado— y se admite escribir una
 * nueva. Vacío significa página propia, que es el default.
 */
const familySearch = ref('');

const familyOptions = computed(() => {
    const existentes = props.families.map((f) => ({ value: f, label: f }));
    const escrita = familySearch.value.trim();

    // Lo que se está escribiendo entra como opción si todavía no existe: así se
    // puede ABRIR una familia nueva (agrupar los tres azufres, por ejemplo) sin
    // que haga falta tocar código ni tener ya una prueba dentro.
    return escrita && !props.families.includes(escrita)
        ? [...existentes, { value: escrita, label: escrita }]
        : existentes;
});

const form = useForm({
    name:          props.testDefinition?.name ?? '',
    code:          props.testDefinition?.code ?? '',
    test_group_id: props.testDefinition?.test_group_id ?? null,
    description:   props.testDefinition?.description ?? '',
    container:     props.testDefinition?.container ?? '',
    chart_unit:    props.testDefinition?.chart_unit ?? '',
    report_comment_group: props.testDefinition?.report_comment_group ?? null,
    has_control:        props.testDefinition?.has_control ?? false,
    requires_control:   props.testDefinition?.requires_control ?? false,
    requires_duplicate: props.testDefinition?.requires_duplicate ?? false,
    is_grouped:         props.testDefinition?.is_grouped ?? false,
    replicates:    props.testDefinition?.replicates ?? 1,
    sort_order:    props.testDefinition?.sort_order ?? null,
    is_active:     props.testDefinition?.is_active ?? true,
});

/**
 * Exigir el patrón sin admitirlo es una combinación imposible: la hoja
 * bloquearía la carga de muestras pidiendo una fila que la prueba no acepta.
 * Marcar "exige" enciende "corre con" en vez de dejar que se guarde así.
 */
const onRequiresControl = (checked) => {
    if (checked) form.has_control = true;
    // Y no se puede exigir lo que la prueba está exenta de correr.
    if (checked) form.is_grouped = false;
};

/**
 * La EXENCIÓN apaga las dos exigencias.
 *
 * En el sistema anterior esta casilla se llamaba `is_grouped` y su formulario la
 * rotulaba "No usa Duplicados / No usa Patrón Control": era la única
 * configuración por prueba del control de calidad. Acá hace lo mismo, y apaga las
 * dos casillas en vez de dejar guardado un estado contradictorio ("exenta" y
 * "exige patrón" a la vez) que después nadie sabría cómo leer.
 */
const onExempt = (checked) => {
    if (!checked) return;
    form.requires_control = false;
    form.requires_duplicate = false;
};

const submit = () => {
    if (isEdit.value) {
        form.put(route('lab_management.test_definitions.update', props.testDefinition.slug));
    } else {
        form.post(route('lab_management.test_definitions.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('global.edit') + ' — ' + $t('test_definitions.singular') : $t('test_definitions.new')" />

    <div class="form-page sap-form">
        <SectionHeader
            :back-href="route('lab_management.test_definitions.index')"
            :title="isEdit ? $t('global.edit') + ' ' + $t('test_definitions.record') : $t('test_definitions.new')"
            :subtitle="isEdit ? testDefinition.name : $t('test_definitions.create_subtitle')"
        >
            <template #icon><FileDoneOutlined /></template>
        </SectionHeader>

        <div class="form-body">
            <Form layout="vertical" @submit.prevent="submit">

                <Alert
                    v-if="form.hasErrors && Object.keys(form.errors).length > 0"
                    type="error"
                    show-icon
                    :message="$t('global.fix_marked_fields')"
                    class="mb-4"
                />

                <h2 class="form-section-title">{{ $t('test_definitions.section_identification') }}</h2>
                <div class="form-grid">
                    <FormItem
                        class="form-grid__wide"
                        :label="$t('test_definitions.name')"
                        :tooltip="$t('test_definitions.name_help')"
                        required
                        :validate-status="form.errors.name ? 'error' : ''"
                        :help="form.errors.name"
                    >
                        <Input
                            v-model:value="form.name"
                            size="large"
                            :maxlength="150"
                            showCount
                            autofocus
                            :placeholder="$t('test_definitions.name_placeholder')"
                        />
                    </FormItem>

                    <FormItem
                        :label="$t('test_definitions.code')"
                        :tooltip="$t('test_definitions.code_help')"
                        :validate-status="form.errors.code ? 'error' : ''"
                        :help="form.errors.code"
                    >
                        <Input
                            v-model:value="form.code"
                            size="large"
                            :maxlength="60"
                            :placeholder="$t('test_definitions.code_placeholder')"
                        />
                    </FormItem>

                    <FormItem
                        :label="$t('test_definitions.group')"
                        :tooltip="$t('test_definitions.group_help')"
                        :validate-status="form.errors.test_group_id ? 'error' : ''"
                        :help="form.errors.test_group_id"
                    >
                        <Select
                            v-model:value="form.test_group_id"
                            size="large"
                            allow-clear
                            show-search
                            option-filter-prop="label"
                            :options="groups"
                            :placeholder="$t('test_definitions.group_none')"
                        />
                    </FormItem>

                    <FormItem
                        class="form-grid__wide"
                        :label="$t('test_definitions.description')"
                        :tooltip="$t('test_definitions.description_help')"
                        :validate-status="form.errors.description ? 'error' : ''"
                        :help="form.errors.description"
                    >
                        <Textarea v-model:value="form.description" :rows="3" :maxlength="2000" showCount />
                    </FormItem>
                </div>

                <h2 class="form-section-title form-section-title--spaced">{{ $t('test_definitions.section_sampling') }}</h2>
                <div class="form-grid">
                    <FormItem
                        :label="$t('test_definitions.container')"
                        :tooltip="$t('test_definitions.container_help')"
                        :validate-status="form.errors.container ? 'error' : ''"
                        :help="form.errors.container"
                    >
                        <Input v-model:value="form.container" size="large" :maxlength="100" />
                    </FormItem>

                    <FormItem
                        :label="$t('test_definitions.chart_unit')"
                        :tooltip="$t('test_definitions.chart_unit_help')"
                        :validate-status="form.errors.chart_unit ? 'error' : ''"
                        :help="form.errors.chart_unit"
                    >
                        <Input v-model:value="form.chart_unit" size="large" :maxlength="40" />
                    </FormItem>

                    <FormItem
                        :label="$t('test_definitions.report_comment_group')"
                        :tooltip="$t('test_definitions.report_comment_group_help')"
                        :validate-status="form.errors.report_comment_group ? 'error' : ''"
                        :help="form.errors.report_comment_group"
                    >
                        <Select
                            v-model:value="form.report_comment_group"
                            size="large"
                            allow-clear
                            show-search
                            :options="familyOptions"
                            :placeholder="$t('test_definitions.report_comment_group_own')"
                            :filter-option="false"
                            @search="familySearch = $event"
                        />
                    </FormItem>

                    <FormItem
                        :label="$t('test_definitions.replicates')"
                        :tooltip="$t('test_definitions.replicates_help')"
                        :validate-status="form.errors.replicates ? 'error' : ''"
                        :help="form.errors.replicates"
                    >
                        <InputNumber v-model:value="form.replicates" size="large" :min="1" :max="255" style="width: 100%" />
                    </FormItem>
                </div>

                <h2 class="form-section-title form-section-title--spaced">{{ $t('test_definitions.section_control') }}</h2>

                <!-- Las tres banderas van juntas y con la explicación arriba:
                     sueltas, "corre con patrón" y "exige patrón" se leen igual
                     y nadie sabe cuál marcar. -->
                <p class="section-intro">{{ $t('test_definitions.control_intro') }}</p>

                <div class="form-grid">
                    <FormItem class="form-grid__wide">
                        <div class="flag">
                            <Checkbox v-model:checked="form.has_control" :disabled="form.requires_control">
                                {{ $t('test_definitions.has_control') }}
                            </Checkbox>
                            <span class="flag__help">{{ $t('test_definitions.has_control_help') }}</span>
                        </div>

                        <div class="flag">
                            <Checkbox v-model:checked="form.requires_control" @change="onRequiresControl($event.target.checked)">
                                {{ $t('test_definitions.requires_control') }}
                            </Checkbox>
                            <span class="flag__help">{{ $t('test_definitions.requires_control_help') }}</span>
                        </div>

                        <div class="flag">
                            <Checkbox v-model:checked="form.requires_duplicate">
                                {{ $t('test_definitions.requires_duplicate') }}
                            </Checkbox>
                            <span class="flag__help">{{ $t('test_definitions.requires_duplicate_help') }}</span>
                        </div>

                        <div class="flag flag--legacy">
                            <Checkbox
                                v-model:checked="form.is_grouped"
                                @change="onExempt($event.target.checked)"
                            >
                                {{ $t('test_definitions.is_grouped') }}
                            </Checkbox>
                            <span class="flag__help">{{ $t('test_definitions.is_grouped_help') }}</span>
                        </div>
                    </FormItem>
                </div>

                <h2 class="form-section-title form-section-title--spaced">{{ $t('global.general_data') }}</h2>
                <div class="form-grid">
                    <FormItem
                        :label="$t('test_definitions.sort_order')"
                        :tooltip="$t('test_definitions.sort_order_help')"
                        :validate-status="form.errors.sort_order ? 'error' : ''"
                        :help="form.errors.sort_order"
                    >
                        <InputNumber v-model:value="form.sort_order" size="large" :min="0" :max="99999" style="width: 100%" />
                    </FormItem>

                    <FormItem
                        v-if="isEdit"
                        :label="$t('test_definitions.is_active')"
                        :tooltip="$t('test_definitions.is_active_help')"
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
                </div>

                <!-- legacy_id NO se edita: es el id de esta prueba en el
                     sistema Rails y lo escribe el importador. Se ve en la
                     ficha, como dato de trazabilidad. -->

                <FormFooter
                    :cancel-href="route('lab_management.test_definitions.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    floating
                />
            </Form>
        </div>
    </div>
</template>

<style scoped>
.state-label {
    font-size: 0.875rem;
    color: var(--color-text);
    font-weight: 500;
}
.mb-4 { margin-bottom: 16px; }

.section-intro {
    margin: 0 0 16px 0;
    color: var(--color-text-muted);
    font-size: 0.8125rem;
    line-height: 1.6;
    max-width: 70ch;
}

.flag { margin-bottom: 14px; }
.flag__help {
    display: block;
    margin-left: 24px;
    color: var(--color-text-muted);
    font-size: 0.78rem;
    line-height: 1.5;
}
/* La bandera heredada va separada: no es una decisión de hoy, es historia. */
.flag--legacy {
    margin-top: 18px;
    padding-top: 14px;
    border-top: 1px dashed var(--color-border, #e5e7eb);
}
</style>
