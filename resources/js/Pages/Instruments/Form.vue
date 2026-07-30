<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Form, FormItem, Input, Textarea, InputNumber, Switch, Space, Alert, DatePicker,
} from 'ant-design-vue';
import { ToolOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    instrument: { type: Object, default: null },
});

const isEdit = computed(() => !!props.instrument);

// Los DatePicker van con `value-format="YYYY-MM-DD"`: el modelo guarda la
// fecha como texto plano, igual que viaja al backend. La calibración es un día
// de calendario, no un instante — meter zona horaria acá corre el vencimiento
// un día para quien esté en otro huso.
const form = useForm({
    name:        props.instrument?.name ?? '',
    description: props.instrument?.description ?? '',
    brand:      props.instrument?.brand ?? '',
    model:      props.instrument?.model ?? '',
    serial:     props.instrument?.serial ?? '',
    calibrated_at:      props.instrument?.calibrated_at ?? null,
    calibration_due_at: props.instrument?.calibration_due_at ?? null,
    calibration_certificate: props.instrument?.calibration_certificate ?? '',
    location:   props.instrument?.location ?? '',
    notes:      props.instrument?.notes ?? '',
    sort_order: props.instrument?.sort_order ?? null,
    is_active:  props.instrument?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('business_management.instruments.update', props.instrument.slug));
    } else {
        form.post(route('business_management.instruments.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('global.edit') + ' — ' + $t('instruments.singular') : $t('instruments.new')" />

    <div class="form-page sap-form">
        <SectionHeader
            :back-href="route('business_management.instruments.index')"
            :title="isEdit ? $t('global.edit') + ' ' + $t('instruments.record') : $t('instruments.new')"
            :subtitle="isEdit ? instrument.name : $t('instruments.create_subtitle')"
        >
            <template #icon><ToolOutlined /></template>
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

                <h2 class="form-section-title">{{ $t('instruments.section_identification') }}</h2>
                <div class="form-grid">
                    <!-- El NOMBRE va PRIMERO: es el código de calibración, o
                         sea la identidad del equipo, y es lo único obligatorio
                         de este bloque. -->
                    <FormItem
                        :label="$t('instruments.name')"
                        :tooltip="$t('instruments.name_help')"
                        required
                        :validate-status="form.errors.name ? 'error' : ''"
                        :help="form.errors.name"
                    >
                        <Input
                            v-model:value="form.name"
                            :maxlength="255"
                            autofocus
                            :placeholder="$t('instruments.name_placeholder')"
                        />
                    </FormItem>
                    <FormItem
                        class="form-grid__wide"
                        :label="$t('instruments.description')"
                        :tooltip="$t('instruments.description_help')"
                        :validate-status="form.errors.description ? 'error' : ''"
                        :help="form.errors.description"
                    >
                        <Input
                            v-model:value="form.description"
                            :maxlength="2000"
                            showCount
                            :placeholder="$t('instruments.description_placeholder')"
                        />
                    </FormItem>
                    <FormItem
                        :label="$t('instruments.brand')"
                        :tooltip="$t('instruments.brand_help')"
                        :validate-status="form.errors.brand ? 'error' : ''"
                        :help="form.errors.brand"
                    >
                        <Input v-model:value="form.brand" :maxlength="100" />
                    </FormItem>
                    <FormItem
                        :label="$t('instruments.model')"
                        :tooltip="$t('instruments.model_help')"
                        :validate-status="form.errors.model ? 'error' : ''"
                        :help="form.errors.model"
                    >
                        <Input v-model:value="form.model" :maxlength="100" />
                    </FormItem>
                    <FormItem
                        :label="$t('instruments.serial')"
                        :tooltip="$t('instruments.serial_help')"
                        :validate-status="form.errors.serial ? 'error' : ''"
                        :help="form.errors.serial"
                    >
                        <Input v-model:value="form.serial" :maxlength="100" />
                    </FormItem>
                </div>

                <h2 class="form-section-title form-section-title--spaced">{{ $t('instruments.section_calibration') }}</h2>
                <div class="form-grid">
                    <FormItem
                        :label="$t('instruments.calibrated_at')"
                        :tooltip="$t('instruments.calibrated_at_help')"
                        :validate-status="form.errors.calibrated_at ? 'error' : ''"
                        :help="form.errors.calibrated_at"
                    >
                        <DatePicker
                            autocomplete="off" v-model:value="form.calibrated_at" value-format="YYYY-MM-DD" style="width:100%" />
                    </FormItem>
                    <FormItem
                        :label="$t('instruments.calibration_due_at')"
                        :tooltip="$t('instruments.calibration_due_at_help')"
                        :validate-status="form.errors.calibration_due_at ? 'error' : ''"
                        :help="form.errors.calibration_due_at"
                    >
                        <DatePicker
                            autocomplete="off" v-model:value="form.calibration_due_at" value-format="YYYY-MM-DD" style="width:100%" />
                    </FormItem>
                    <FormItem
                        :label="$t('instruments.calibration_certificate')"
                        :tooltip="$t('instruments.calibration_certificate_help')"
                        :validate-status="form.errors.calibration_certificate ? 'error' : ''"
                        :help="form.errors.calibration_certificate"
                    >
                        <Input v-model:value="form.calibration_certificate" :maxlength="150" />
                    </FormItem>
                </div>

                <h2 class="form-section-title form-section-title--spaced">{{ $t('instruments.section_extra') }}</h2>
                <div class="form-grid">
                    <FormItem
                        :label="$t('instruments.location')"
                        :tooltip="$t('instruments.location_help')"
                        :validate-status="form.errors.location ? 'error' : ''"
                        :help="form.errors.location"
                    >
                        <Input v-model:value="form.location" :maxlength="150" />
                    </FormItem>
                    <FormItem
                        :label="$t('instruments.sort_order')"
                        :tooltip="$t('instruments.sort_order_help')"
                        :validate-status="form.errors.sort_order ? 'error' : ''"
                        :help="form.errors.sort_order"
                    >
                        <InputNumber v-model:value="form.sort_order" :min="0" :max="99999" style="width:100%" />
                    </FormItem>
                    <FormItem
                        class="form-grid__wide"
                        :label="$t('instruments.notes')"
                        :tooltip="$t('instruments.notes_help')"
                        :validate-status="form.errors.notes ? 'error' : ''"
                        :help="form.errors.notes"
                    >
                        <Textarea v-model:value="form.notes" :rows="3" :maxlength="2000" showCount />
                    </FormItem>
                    <FormItem
                        v-if="isEdit"
                        :label="$t('instruments.is_active')"
                        :tooltip="$t('instruments.is_active_help')"
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

                <FormFooter
                    :cancel-href="route('business_management.instruments.index')"
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
</style>
