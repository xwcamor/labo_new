<script setup>
import { computed, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Form, FormItem, Input, InputNumber, Switch, Space, Alert,
} from 'ant-design-vue';
import { FolderOpenOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    testGroup: { type: Object, default: null },
});

const isEdit = computed(() => !!props.testGroup);

const form = useForm({
    name:       props.testGroup?.name ?? '',
    code:       props.testGroup?.code ?? '',
    sort_order: props.testGroup?.sort_order ?? null,
    is_active:  props.testGroup?.is_active ?? true,
});

/**
 * El código se DERIVA del nombre: "Fisico Quimico" → `fisico_quimico`.
 *
 * Es el identificador técnico con el que las pruebas, el informe y los archivos
 * de idioma referencian al grupo, y dejarlo escribir a mano produjo un grupo
 * llamado "67". La misma regla corre en el servidor, así que lo que se ve
 * mientras se escribe es exactamente lo que se guarda.
 *
 * Al EDITAR no se recalcula: si algo ya referencia ese código, cambiarlo por
 * debajo rompe el enlace en silencio. Por eso el campo queda de solo lectura.
 */
const derivarCodigo = (nombre) => (nombre ?? '')
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')   // fuera las tildes
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 40);

watch(() => form.name, (nombre) => {
    if (!isEdit.value) form.code = derivarCodigo(nombre);
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('lab_management.test_groups.update', props.testGroup.slug));
    } else {
        form.post(route('lab_management.test_groups.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('global.edit') + ' — ' + $t('test_groups.singular') : $t('test_groups.new')" />

    <div class="form-page sap-form">
        <SectionHeader
            :back-href="route('lab_management.test_groups.index')"
            :title="isEdit ? $t('global.edit') + ' ' + $t('test_groups.record') : $t('test_groups.new')"
            :subtitle="isEdit ? testGroup.name : $t('test_groups.create_subtitle')"
        >
            <template #icon><FolderOpenOutlined /></template>
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
                    :label="$t('test_groups.name')"
                    :tooltip="$t('test_groups.name_help')"
                    required
                    :validate-status="form.errors.name ? 'error' : ''"
                    :help="form.errors.name"
                >
                    <Input
                        v-model:value="form.name"
                        size="large"
                        :maxlength="100"
                        showCount
                        autofocus
                        :placeholder="$t('test_groups.name_placeholder')"
                    />
                </FormItem>

                <FormItem
                    :label="$t('test_groups.code')"
                    :tooltip="$t('test_groups.code_help')"
                    :validate-status="form.errors.code ? 'error' : ''"
                    :help="form.errors.code"
                >
                    <Input
                        :value="form.code"
                        size="large"
                        disabled
                        :placeholder="$t('test_groups.code_placeholder')"
                    />
                </FormItem>

                <FormItem
                    :label="$t('test_groups.sort_order')"
                    :tooltip="$t('test_groups.sort_order_help')"
                    :validate-status="form.errors.sort_order ? 'error' : ''"
                    :help="form.errors.sort_order"
                >
                    <InputNumber v-model:value="form.sort_order" size="large" :min="0" :max="99999" style="width:160px" />
                </FormItem>

                <FormItem
                    v-if="isEdit"
                    :label="$t('test_groups.is_active')"
                    :tooltip="$t('test_groups.is_active_help')"
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

                <FormFooter
                    :cancel-href="route('lab_management.test_groups.index')"
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
