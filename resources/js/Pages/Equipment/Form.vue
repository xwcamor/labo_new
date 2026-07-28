<script setup>
import {
    computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, Switch, Space, Alert, Row, Col, Select, InputNumber,
} from 'ant-design-vue';
import { TagsOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    equipment:       { type: Object, default: null },
});

const isEdit = computed(() => !!props.equipment);

const form = useForm({
    name:       props.equipment?.name ?? '',
    serial: props.equipment?.serial ?? '',
    tag: props.equipment?.tag ?? '',
    voltage_kv_hv: props.equipment?.voltage_kv_hv ?? null,
    voltage_kv_lv: props.equipment?.voltage_kv_lv ?? null,
    power_mva: props.equipment?.power_mva ?? null,
    phases: props.equipment?.phases ?? null,
    manufacture_year: props.equipment?.manufacture_year ?? null,
    oil_volume: props.equipment?.oil_volume ?? null,
    external_ref: props.equipment?.external_ref ?? '',
    code:       props.equipment?.code ?? '',
    is_active:  props.equipment?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('business_management.equipment.update', props.equipment.slug));
    } else {
        form.post(route('business_management.equipment.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('global.edit') + ' — ' + $t('equipment.singular') : $t('equipment.new')" />

    <div class="form-page sap-form">
        <SectionHeader
            :back-href="route('business_management.equipment.index')"
            :title="isEdit ? $t('global.edit') + ' ' + $t('equipment.record') : $t('equipment.new')"
            :subtitle="isEdit ? equipment.name : $t('equipment.create_subtitle')"
        >
            <template #icon><TagsOutlined /></template>
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
                    v-if="form.hasErrors && Object.keys(form.errors).length > 0"
                    type="error"
                    show-icon
                    :message="$t('global.fix_marked_fields')"
                    class="mb-4"
                />

                <h2 class="form-section-title">{{ $t('global.general_data') }}</h2>

                <FormItem
                    :label="$t('equipment.name')"
                    :tooltip="$t('equipment.name_help')"
                    required
                    :validate-status="form.errors.name ? 'error' : ''"
                    :help="form.errors.name"
                >
                    <Input
                        v-model:value="form.name"
                        size="large"
                        :maxlength="255"
                        showCount
                        autofocus
                        :placeholder="$t('equipment.name_placeholder')"
                    />
                </FormItem>

                <FormItem
                    :label="$t('equipment.code')"
                    :tooltip="$t('equipment.code_help')"
                    :validate-status="form.errors.code ? 'error' : ''"
                    :help="form.errors.code"
                >
                    <Input
                        v-model:value="form.code"
                        size="large"
                        :maxlength="40"
                        :placeholder="$t('equipment.code')"
                    />
                </FormItem>

                <FormItem
                    v-if="isEdit"
                    :label="$t('equipment.is_active')"
                    :tooltip="$t('equipment.is_active_help')"
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

                                    <Col :xs="24" :md="8">
                        <FormItem
                            :label="$t('equipment.serial')"
                            :validate-status="form.errors.serial ? 'error' : ''"
                            :help="form.errors.serial"
                        >
                            <Input v-model:value="form.serial" size="large" :maxlength="255" />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            :label="$t('equipment.tag')"
                            :validate-status="form.errors.tag ? 'error' : ''"
                            :help="form.errors.tag"
                        >
                            <Input v-model:value="form.tag" size="large" :maxlength="255" />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            :label="$t('equipment.voltage_kv_hv')"
                            :validate-status="form.errors.voltage_kv_hv ? 'error' : ''"
                            :help="form.errors.voltage_kv_hv"
                        >
                            <InputNumber v-model:value="form.voltage_kv_hv" size="large" style="width: 100%" />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            :label="$t('equipment.voltage_kv_lv')"
                            :validate-status="form.errors.voltage_kv_lv ? 'error' : ''"
                            :help="form.errors.voltage_kv_lv"
                        >
                            <InputNumber v-model:value="form.voltage_kv_lv" size="large" style="width: 100%" />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            :label="$t('equipment.power_mva')"
                            :validate-status="form.errors.power_mva ? 'error' : ''"
                            :help="form.errors.power_mva"
                        >
                            <InputNumber v-model:value="form.power_mva" size="large" style="width: 100%" />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            :label="$t('equipment.phases')"
                            :validate-status="form.errors.phases ? 'error' : ''"
                            :help="form.errors.phases"
                        >
                            <InputNumber v-model:value="form.phases" size="large" style="width: 100%" />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            :label="$t('equipment.manufacture_year')"
                            :validate-status="form.errors.manufacture_year ? 'error' : ''"
                            :help="form.errors.manufacture_year"
                        >
                            <InputNumber v-model:value="form.manufacture_year" size="large" style="width: 100%" />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            :label="$t('equipment.oil_volume')"
                            :validate-status="form.errors.oil_volume ? 'error' : ''"
                            :help="form.errors.oil_volume"
                        >
                            <InputNumber v-model:value="form.oil_volume" size="large" style="width: 100%" />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            :label="$t('equipment.external_ref')"
                            :validate-status="form.errors.external_ref ? 'error' : ''"
                            :help="form.errors.external_ref"
                        >
                            <Input v-model:value="form.external_ref" size="large" :maxlength="255" />
                        </FormItem>
                    </Col>

                <FormFooter
                    :cancel-href="route('business_management.equipment.index')"
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
