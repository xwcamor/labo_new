<script setup>
/**
 * Alta y edición de la cabecera de la recepción.
 *
 * Acá NO se emiten correlativos. Mientras la entrega es un borrador se corrige
 * el cliente, la cantidad de envases o el muestreador sin quemar números; los
 * números se emiten al confirmar, en la ficha, y una sola vez.
 *
 * En una recepción YA CONFIRMADA, el cliente y la fecha de recepción quedan
 * deshabilitados: sus correlativos ya están emitidos y el año de esos números
 * sale de esa fecha, así que cambiarlos dejaría muestras con un número de un
 * ejercicio y un dueño que no corresponden. El servidor los ignora igual — esto
 * es para que se vea, no para que se cumpla.
 */
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Alert, Checkbox, DatePicker, Form, FormItem, Input, InputNumber,
    Select, SelectOption, Textarea,
} from 'ant-design-vue';
import { InboxOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import { isoDate } from './config/format';

defineOptions({ layout: AppLayout });

const props = defineProps({
    reception:  { type: Object, default: null },
    customers:  { type: Array,  default: () => [] },
    samplers:   { type: Array,  default: () => [] },
    // Solo en el alta: el próximo correlativo, como referencia.
    nextNumber: { type: [String, null], default: null },
});

const isEdit = computed(() => !!props.reception);

/** Ya emitió correlativos: cliente y fecha de recepción quedan congelados. */
const isLocked = computed(() => isEdit.value && props.reception.status !== 'draft');

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    code:          props.reception?.code ?? '',
    service_order: props.reception?.service_order ?? '',
    customer_id:   props.reception?.customer_id ?? null,
    sampler_id:    props.reception?.sampler_id ?? null,
    sampler_name:  props.reception?.sampler_name ?? '',
    received_at:   isoDate(props.reception?.received_at) ?? today,
    due_at:        isoDate(props.reception?.due_at),
    packages:      props.reception?.packages ?? null,
    container_ok:  !!props.reception?.container_ok,
    volume_ok:     !!props.reception?.volume_ok,
    label_ok:      !!props.reception?.label_ok,
    is_urgent:     !!props.reception?.is_urgent,
    notes:         props.reception?.notes ?? '',
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('lab_management.receptions.update', props.reception.slug));

        return;
    }

    form.post(route('lab_management.receptions.store'));
};
</script>

<template>
    <Head :title="isEdit ? $t('receptions.edit_title') : $t('receptions.create_title')" />

    <div class="form-page sap-form">
        <SectionHeader
            :back-href="isEdit
                ? route('lab_management.receptions.show', reception.slug)
                : route('lab_management.receptions.index')"
            :title="isEdit ? $t('receptions.edit_title') : $t('receptions.create_title')"
            :subtitle="$t('receptions.create_subtitle')"
        >
            <template #icon><InboxOutlined /></template>
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
                    class="rc-form__alert"
                />

                <!-- El próximo correlativo, como referencia. No es el que se va
                     a emitir: entre esta pantalla y la confirmación pueden
                     entrar otras entregas. -->
                <Alert
                    v-if="!isEdit && nextNumber"
                    type="info"
                    show-icon
                    class="rc-form__alert"
                    :message="$t('receptions.next_number', { code: nextNumber })"
                    :description="$t('receptions.confirm_help')"
                />

                <Alert
                    v-if="isLocked"
                    type="info"
                    show-icon
                    class="rc-form__alert"
                    :message="$t('receptions.errors.confirmed_no_edit')"
                />

                <h2 class="form-section-title">{{ $t('receptions.section_header') }}</h2>

                <FormItem
                    :label="$t('receptions.code')"
                    :extra="$t('receptions.code_help')"
                    :validate-status="form.errors.code ? 'error' : ''"
                    :help="form.errors.code"
                >
                    <Input v-model:value="form.code" size="large" :maxlength="30" />
                </FormItem>

                <FormItem
                    :label="$t('receptions.service_order')"
                    :validate-status="form.errors.service_order ? 'error' : ''"
                    :help="form.errors.service_order"
                >
                    <Input v-model:value="form.service_order" size="large" :maxlength="60" />
                </FormItem>

                <FormItem
                    :label="$t('receptions.customer')"
                    required
                    :extra="$t('receptions.customer_help')"
                    :validate-status="form.errors.customer_id ? 'error' : ''"
                    :help="form.errors.customer_id"
                >
                    <Select
                        v-model:value="form.customer_id"
                        size="large"
                        show-search
                        option-filter-prop="label"
                        :disabled="isLocked"
                        :placeholder="$t('receptions.customer')"
                    >
                        <SelectOption
                            v-for="customer in customers"
                            :key="customer.id"
                            :value="customer.id"
                            :label="customer.name"
                        >
                            {{ customer.name }}
                        </SelectOption>
                    </Select>
                </FormItem>

                <!-- El muestreador no siempre es alguien del laboratorio: puede
                     ser personal del cliente o un tercero. Por eso hay las dos
                     formas y ninguna obliga a dar de alta un usuario. -->
                <FormItem
                    :label="$t('receptions.sampler')"
                    :extra="$t('receptions.sampler_help')"
                    :validate-status="form.errors.sampler_id ? 'error' : ''"
                    :help="form.errors.sampler_id"
                >
                    <Select
                        v-model:value="form.sampler_id"
                        size="large"
                        allow-clear
                        show-search
                        option-filter-prop="label"
                        :placeholder="$t('receptions.sampler')"
                    >
                        <SelectOption
                            v-for="user in samplers"
                            :key="user.id"
                            :value="user.id"
                            :label="user.name"
                        >
                            {{ user.name }}
                        </SelectOption>
                    </Select>
                </FormItem>

                <FormItem
                    :label="$t('receptions.sampler_name')"
                    :validate-status="form.errors.sampler_name ? 'error' : ''"
                    :help="form.errors.sampler_name"
                >
                    <Input v-model:value="form.sampler_name" size="large" :maxlength="120" />
                </FormItem>

                <FormItem
                    :label="$t('receptions.received_at')"
                    required
                    :extra="$t('receptions.received_at_help')"
                    :validate-status="form.errors.received_at ? 'error' : ''"
                    :help="form.errors.received_at"
                >
                    <DatePicker
                        v-model:value="form.received_at"
                        size="large"
                        value-format="YYYY-MM-DD"
                        style="width: 100%"
                        :disabled="isLocked"
                    />
                </FormItem>

                <FormItem
                    :label="$t('receptions.due_at')"
                    :validate-status="form.errors.due_at ? 'error' : ''"
                    :help="form.errors.due_at"
                >
                    <DatePicker
                        v-model:value="form.due_at"
                        size="large"
                        value-format="YYYY-MM-DD"
                        style="width: 100%"
                    />
                </FormItem>

                <FormItem
                    :label="$t('receptions.packages')"
                    :validate-status="form.errors.packages ? 'error' : ''"
                    :help="form.errors.packages"
                >
                    <InputNumber
                        v-model:value="form.packages"
                        size="large"
                        style="width: 100%"
                        :min="0"
                        :max="9999"
                    />
                </FormItem>

                <h2 class="form-section-title">{{ $t('receptions.section_check') }}</h2>

                <FormItem :wrapper-col="{ xs: 24, sm: { offset: 8, span: 16 }, md: { offset: 6, span: 13 } }">
                    <p class="rc-form__hint">{{ $t('receptions.check_help') }}</p>
                    <div class="rc-form__checks">
                        <Checkbox v-model:checked="form.container_ok">
                            {{ $t('receptions.container_ok') }}
                        </Checkbox>
                        <Checkbox v-model:checked="form.volume_ok">
                            {{ $t('receptions.volume_ok') }}
                        </Checkbox>
                        <Checkbox v-model:checked="form.label_ok">
                            {{ $t('receptions.label_ok') }}
                        </Checkbox>
                        <Checkbox v-model:checked="form.is_urgent">
                            {{ $t('receptions.is_urgent') }}
                        </Checkbox>
                    </div>
                </FormItem>

                <FormItem
                    :label="$t('receptions.notes')"
                    :validate-status="form.errors.notes ? 'error' : ''"
                    :help="form.errors.notes"
                >
                    <Textarea v-model:value="form.notes" :rows="3" :maxlength="2000" show-count />
                </FormItem>

                <FormFooter
                    :cancel-href="isEdit
                        ? route('lab_management.receptions.show', reception.slug)
                        : route('lab_management.receptions.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="receptions.new"
                    floating
                />
            </Form>
        </div>
    </div>
</template>

<style scoped>
.rc-form__alert { margin-bottom: 16px; }
.rc-form__hint { font-size: 0.8125rem; color: var(--color-text-muted); margin: 0 0 10px; }
.rc-form__checks { display: flex; flex-direction: column; gap: 10px; align-items: flex-start; }
/* Ant Design separa los checkbox contiguos con margen izquierdo: apilados en
   columna eso los desalinea del resto del formulario. */
.rc-form__checks :deep(.ant-checkbox-wrapper) { margin-left: 0; }
</style>
