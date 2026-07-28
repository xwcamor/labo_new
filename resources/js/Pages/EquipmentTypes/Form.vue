<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, Switch, Space, Alert, Row, Col, Select,
} from 'ant-design-vue';
import { UserOutlined, AppstoreOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import EquipmentGlyph from '@/Components/Equipment/EquipmentGlyph.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    equipmentType:       { type: Object, default: null },
});

const isEdit = computed(() => !!props.equipmentType);

const form = useForm({
    name:       props.equipmentType?.name ?? '',
    code:       props.equipmentType?.code ?? '',
    shape:      props.equipmentType?.shape ?? 'tank',
    is_active:  props.equipmentType?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('business_management.equipment_types.update', props.equipmentType.slug));
    } else {
        form.post(route('business_management.equipment_types.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('global.edit') + ' — ' + $t('equipment_types.singular') : $t('equipment_types.new')" />

    <div class="form-page sap-form">
        <SectionHeader
            :back-href="route('business_management.equipment_types.index')"
            :title="isEdit ? $t('global.edit') + ' ' + $t('equipment_types.record') : $t('equipment_types.new')"
            :subtitle="isEdit ? equipmentType.name : $t('equipment_types.create_subtitle')"
        >
            <template #icon><AppstoreOutlined /></template>
        </SectionHeader>

        <Card class="form-card" :bodyStyle="{ padding: '24px 28px' }">
            <Form layout="horizontal" :label-col="{ xs: 24, sm: 8, md: 6 }" :wrapper-col="{ xs: 24, sm: 16, md: 13 }" label-align="right" :colon="true" @submit.prevent="submit">

                <Alert
                    v-if="form.hasErrors && Object.keys(form.errors).length > 0"
                    type="error"
                    show-icon
                    :message="$t('global.fix_marked_fields')"
                    class="mb-4"
                />

                <h2 class="form-section-title">{{ $t('global.general_data') }}</h2>


                <Row :gutter="[20, 0]">
                    <Col :xs="24" :md="16">
                        <FormItem
                            :label="$t('equipment_types.name')"
                            :tooltip="$t('equipment_types.name_help')"
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
                                :placeholder="$t('equipment_types.name_placeholder')"
                            />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            :label="$t('equipment_types.code')"
                            :tooltip="$t('equipment_types.code_help')"
                            :validate-status="form.errors.code ? 'error' : ''"
                            :help="form.errors.code"
                        >
                            <Input
                                v-model:value="form.code"
                                size="large"
                                :maxlength="40"
                                :placeholder="$t('equipment_types.code')"
                            />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            :label="$t('equipment_types.shape')"
                            :tooltip="$t('equipment_types.shape_help')"
                            :validate-status="form.errors.shape ? 'error' : ''"
                            :help="form.errors.shape"
                        >
                            <Select
                                v-model:value="form.shape"
                                size="large"
                                :options="[
                                    { value: 'tank', label: $t('equipment_types.shape_tank') },
                                    { value: 'pole', label: $t('equipment_types.shape_pole') },
                                    { value: 'dry',  label: $t('equipment_types.shape_dry') },
                                ]"
                            />
                        </FormItem>
                        <div class="shape-preview">
                            <EquipmentGlyph :shape="form.shape" :phases="'three'" />
                            <span class="shape-preview__cap">{{ $t('equipment_types.shape_preview') }}</span>
                        </div>
                    </Col>
                    <Col v-if="isEdit" :xs="24" :md="8">
                        <FormItem
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
                    </Col>
                </Row>

                <FormFooter
                    :cancel-href="route('business_management.equipment_types.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    floating
                />
            </Form>
        </Card>
    </div>
</template>

<style scoped>
.form-card { border-radius: 6px; }
.state-label {
    font-size: 0.875rem;
    color: var(--color-text);
    font-weight: 500;
}
.mb-4 { margin-bottom: 16px; }
.shape-preview {
    margin-top: -6px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}
.shape-preview > :first-child { width: 104px; height: 104px; }
.shape-preview__cap { font-size: 0.72rem; color: var(--color-text-muted, #6A6D70); }
</style>
