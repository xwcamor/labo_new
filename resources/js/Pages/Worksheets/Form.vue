<script setup>
/**
 * Alta de la hoja de trabajo.
 *
 * Son cinco datos y ninguno más: qué prueba, qué día, quién la corre y en qué
 * condiciones estaba la sala. Las muestras no se cargan acá — se cargan en la
 * hoja, que es donde el analista las tiene delante.
 */
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    Alert, DatePicker, Form, FormItem, Input, InputNumber, Select, SelectOptGroup,
    SelectOption, Textarea,
} from 'ant-design-vue';
import { ExperimentOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import { useI18n } from '@/Plugins/i18n';
import { groupTests, isGrouped } from '@/Utils/testGroups';

defineOptions({ layout: AppLayout });

const props = defineProps({
    worksheet: { type: Object, default: null },
    tests:     { type: Array,  default: () => [] },
    // Prueba preseleccionada cuando se llega desde otra pantalla (?test=...).
    selected:  { type: [String, Number, null], default: null },
});

const page = usePage();
const { t } = useI18n();

/**
 * Las pruebas por grupo (Físico Químico · Cromatografías · Otros). Es la misma
 * agrupación que ofrece el filtro del listado: elegir la prueba de la hoja y
 * filtrar por prueba no pueden mostrar dos listas distintas.
 */
const testGroups = computed(() => groupTests(props.tests, t('worksheets.group_none')));

/** Sin grupos que separar se dibuja la lista de siempre, sin encabezados. */
const showGroups = computed(() => isGrouped(testGroups.value));

/** El `test` de la dirección puede venir como slug o como id. */
const preselected = computed(() => {
    if (props.selected === null || props.selected === undefined || props.selected === '') return null;

    const match = props.tests.find(
        (test) => String(test.slug) === String(props.selected) || String(test.id) === String(props.selected),
    );

    return match?.id ?? null;
});

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    test_definition_id: props.worksheet?.test_definition_id ?? preselected.value,
    run_date:           props.worksheet?.run_date?.slice(0, 10) ?? today,
    // El analista por omisión es quien abre la hoja: el servidor lo resuelve
    // con el usuario de la sesión. No se manda un id desde acá porque esta
    // pantalla no recibe la lista de usuarios.
    analyst_id:         null,
    ambient_temp_c:     props.worksheet?.ambient_temp_c ?? null,
    ambient_humidity:   props.worksheet?.ambient_humidity ?? null,
    notes:              props.worksheet?.notes ?? '',
});

const currentUserName = computed(() => page.props.auth?.user?.name ?? '');

const submit = () => form.post(route('lab_management.worksheets.store'));
</script>

<template>
    <Head :title="$t('worksheets.create')" />

    <div class="form-page sap-form">
        <SectionHeader
            :back-href="route('lab_management.worksheets.index')"
            :title="$t('worksheets.create')"
            :subtitle="$t('worksheets.intro')"
        >
            <template #icon><ExperimentOutlined /></template>
        </SectionHeader>

        <div class="form-body">
            <Form
                layout="horizontal"
                :label-col="{ xs: 24, sm: 8, md: 6 }"
                :wrapper-col="{ xs: 24, sm: 16, md: 13 }"
                label-align="right"
                :colon="true"
                @submit.prevent="submit"
            >
                <Alert
                    v-if="form.hasErrors"
                    type="error"
                    show-icon
                    :message="$t('global.fix_marked_fields')"
                    class="ws-form__alert"
                />

                <h2 class="form-section-title">{{ $t('global.general_data') }}</h2>

                <FormItem
                    :label="$t('worksheets.test_definition')"
                    required
                    :validate-status="form.errors.test_definition_id ? 'error' : ''"
                    :help="form.errors.test_definition_id"
                >
                    <Select
                        v-model:value="form.test_definition_id"
                        size="large"
                        show-search
                        option-filter-prop="label"
                        :placeholder="$t('worksheets.test_definition')"
                    >
                        <!-- Agrupadas por familia de ensayo: son 29 y en una
                             lista plana hay que leerlas todas para encontrar
                             una. La búsqueda sigue siendo por nombre. -->
                        <template v-if="showGroups">
                            <SelectOptGroup v-for="group in testGroups" :key="group.key" :label="group.label">
                                <SelectOption
                                    v-for="test in group.tests"
                                    :key="test.id"
                                    :value="test.id"
                                    :label="test.name"
                                >
                                    {{ test.name }}
                                </SelectOption>
                            </SelectOptGroup>
                        </template>

                        <template v-else>
                            <SelectOption v-for="test in tests" :key="test.id" :value="test.id" :label="test.name">
                                {{ test.name }}
                            </SelectOption>
                        </template>
                    </Select>
                </FormItem>

                <FormItem
                    :label="$t('worksheets.run_date')"
                    required
                    :validate-status="form.errors.run_date ? 'error' : ''"
                    :help="form.errors.run_date"
                >
                    <DatePicker
                        v-model:value="form.run_date"
                        size="large"
                        value-format="YYYY-MM-DD"
                        style="width: 100%"
                    />
                </FormItem>

                <!-- El analista se muestra y no se elige: esta pantalla no
                     recibe la lista de usuarios, y el servidor asigna a quien
                     abre la hoja. Se deja a la vista para que nadie cargue una
                     tanda creyendo que quedó a nombre de otro. -->
                <FormItem :label="$t('worksheets.analyst')">
                    <Input :value="currentUserName" size="large" disabled />
                </FormItem>

                <FormItem
                    :label="$t('worksheets.ambient_temp_c')"
                    :validate-status="form.errors.ambient_temp_c ? 'error' : ''"
                    :help="form.errors.ambient_temp_c"
                >
                    <InputNumber
                        v-model:value="form.ambient_temp_c"
                        size="large"
                        style="width: 100%"
                        :step="0.1"
                        addon-after="°C"
                    />
                </FormItem>

                <FormItem
                    :label="$t('worksheets.ambient_humidity')"
                    :validate-status="form.errors.ambient_humidity ? 'error' : ''"
                    :help="form.errors.ambient_humidity"
                >
                    <InputNumber
                        v-model:value="form.ambient_humidity"
                        size="large"
                        style="width: 100%"
                        :min="0"
                        :max="100"
                        :step="0.1"
                        addon-after="%"
                    />
                </FormItem>

                <FormItem
                    :label="$t('worksheets.notes')"
                    :validate-status="form.errors.notes ? 'error' : ''"
                    :help="form.errors.notes"
                >
                    <Textarea v-model:value="form.notes" :rows="3" :maxlength="2000" show-count />
                </FormItem>

                <FormFooter
                    :cancel-href="route('lab_management.worksheets.index')"
                    :is-edit="false"
                    :processing="form.processing"
                    create-label-key="worksheets.create"
                    floating
                />
            </Form>
        </div>
    </div>
</template>

<style scoped>
.ws-form__alert { margin-bottom: 16px; }
</style>
