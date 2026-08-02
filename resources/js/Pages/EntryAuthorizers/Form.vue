<script setup>
/**
 * Alta y edición del personal que autoriza el ingreso de muestras.
 *
 * Es el «Personal de Laboratorio» del sistema anterior: nombre completo y
 * firma escaneada. El elegido es obligatorio al registrar una recepción, y su
 * firma es la que se imprime en el acta de recepción. NO es el catálogo de
 * firmantes de informes — son dos listas distintas, como en el viejo.
 *
 * La firma se toma por los mismos dos caminos que en Firmas: subir el escaneo
 * o dibujarla en pantalla (mismo `SignatureDrawPad`).
 */
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Form, FormItem, Input, Switch, Space, Alert, RadioGroup, RadioButton,
} from 'ant-design-vue';
import { IdcardOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import SignatureDrawPad from '@/Components/Signatures/SignatureDrawPad.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    entryAuthorizer:       { type: Object, default: null },
});

const isEdit = computed(() => !!props.entryAuthorizer);

// Sin campo `code`: el módulo es deliberadamente simple —nombre y firma, como
// el catálogo del sistema anterior— y el código técnico se deriva del nombre
// en el servidor.
const form = useForm({
    name:       props.entryAuthorizer?.name ?? '',
    image:      null,
    is_active:  props.entryAuthorizer?.is_active ?? true,
});

// La imagen: subirla o dibujarla. `imagenLista` confirma en pantalla que el
// archivo quedó tomado antes de guardar.
const modoFirma = ref('upload');
const imagenLista = ref(null);

const tomarDibujo = (file) => {
    form.image = file;
    imagenLista.value = URL.createObjectURL(file);
};

const tomarArchivo = (e) => {
    form.image = e.target.files?.[0] ?? null;
    imagenLista.value = form.image ? URL.createObjectURL(form.image) : null;
};

const submit = () => {
    if (isEdit.value) {
        // `forceFormData` + method spoofing: un PUT con archivo no viaja como
        // multipart y la imagen se perdería en silencio.
        form.transform((d) => ({ ...d, _method: 'put' }))
            .post(route('business_management.entry_authorizers.update', props.entryAuthorizer.slug), {
                forceFormData: true,
            });
    } else {
        form.post(route('business_management.entry_authorizers.store'), { forceFormData: true });
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('global.edit') + ' — ' + $t('entry_authorizers.singular') : $t('entry_authorizers.new')" />

    <div class="form-page sap-form">
        <SectionHeader
            :back-href="route('business_management.entry_authorizers.index')"
            :title="isEdit ? $t('global.edit') + ' ' + $t('entry_authorizers.record') : $t('entry_authorizers.new')"
            :subtitle="isEdit ? entryAuthorizer.name : $t('entry_authorizers.create_subtitle')"
        >
            <template #icon><IdcardOutlined /></template>
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
                    :label="$t('entry_authorizers.name')"
                    :tooltip="$t('entry_authorizers.name_help')"
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
                        :placeholder="$t('entry_authorizers.name_placeholder')"
                    />
                </FormItem>

                <FormItem
                    :label="$t('entry_authorizers.image')"
                    :tooltip="$t('entry_authorizers.image_help')"
                    :validate-status="form.errors.image ? 'error' : ''"
                    :help="form.errors.image"
                >
                    <!-- La firma vigente, si ya hay una guardada. -->
                    <img
                        v-if="entryAuthorizer?.image_url && !imagenLista"
                        :src="entryAuthorizer.image_url"
                        alt=""
                        class="sig-preview"
                    >
                    <!-- La nueva, apenas se sube o se dibuja. -->
                    <img v-if="imagenLista" :src="imagenLista" alt="" class="sig-preview">

                    <RadioGroup v-model:value="modoFirma" size="small" class="sig-mode">
                        <RadioButton value="upload">{{ $t('signatures.mode_upload') }}</RadioButton>
                        <RadioButton value="draw">{{ $t('signatures.mode_draw') }}</RadioButton>
                    </RadioGroup>

                    <input
                        v-if="modoFirma === 'upload'"
                        type="file"
                        accept="image/png,image/jpeg"
                        @change="tomarArchivo"
                    >
                    <SignatureDrawPad v-else @done="tomarDibujo" />
                </FormItem>

                <FormItem
                    v-if="isEdit"
                    :label="$t('entry_authorizers.is_active')"
                    :tooltip="$t('entry_authorizers.is_active_help')"
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
                    :cancel-href="route('business_management.entry_authorizers.index')"
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
.sig-preview {
    display: block;
    max-width: 220px;
    max-height: 90px;
    margin-bottom: 10px;
    border: 1px solid var(--color-border, #e5e7eb);
    border-radius: 6px;
    padding: 6px;
    background: #fff;
}
.sig-mode { display: block; margin-bottom: 8px; }
</style>
