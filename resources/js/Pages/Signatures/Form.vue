<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, Switch, Space, Alert, Row, Col, Select, SelectOption, RadioGroup, RadioButton,
} from 'ant-design-vue';
import { TagsOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import SignatureDrawPad from '@/Components/Signatures/SignatureDrawPad.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    signature: { type: Object, default: null },
    /** Usuarios del workspace, con el estado de su firma. */
    users:     { type: Array,  default: () => [] },
    /** Relaciones admitidas: lista cerrada del modelo. */
    relations: { type: Array,  default: () => [] },
});

const isEdit = computed(() => !!props.signature);

const form = useForm({
    name:       props.signature?.name ?? '',
    code:       props.signature?.code ?? '',
    title:      props.signature?.title ?? '',
    relation:   props.signature?.relation ?? 'approved',
    user_id:    props.signature?.user_id ?? null,
    image:      null,
    is_active:  props.signature?.is_active ?? true,
});

/**
 * Con usuario enlazado, la imagen la pone ÉL desde su perfil: es la única que
 * existe con su consentimiento, y ese consentimiento queda auditado. El campo
 * de subida se esconde para no ofrecer un camino que el informe va a ignorar.
 */
const esExterno = computed(() => !form.user_id);

// ── La imagen: subirla o dibujarla ───────────────────────────────────────
// Dos caminos al MISMO archivo: el escaneo se sube, o el firmante presente la
// dibuja y el trazo se convierte en el mismo PNG. `imagenLista` confirma en
// pantalla que el archivo quedó tomado antes de guardar.
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

const usuarioElegido = computed(
    () => (props.users ?? []).find((u) => u.id === form.user_id) ?? null,
);

const submit = () => {
    if (isEdit.value) {
        // `forceFormData` + method spoofing: un PUT con archivo no viaja como
        // multipart y la imagen se perdía en silencio.
        form.transform((d) => ({ ...d, _method: 'put' }))
            .post(route('business_management.signatures.update', props.signature.slug), {
                forceFormData: true,
            });
    } else {
        form.post(route('business_management.signatures.store'), { forceFormData: true });
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('global.edit') + ' — ' + $t('signatures.singular') : $t('signatures.new')" />

    <div class="form-page sap-form">
        <SectionHeader
            :back-href="route('business_management.signatures.index')"
            :title="isEdit ? $t('global.edit') + ' ' + $t('signatures.record') : $t('signatures.new')"
            :subtitle="isEdit ? signature.name : $t('signatures.create_subtitle')"
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
                    :label="$t('signatures.name')"
                    :tooltip="$t('signatures.name_help')"
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
                        :placeholder="$t('signatures.name_placeholder')"
                    />
                </FormItem>

                <FormItem
                    :label="$t('signatures.code')"
                    :tooltip="$t('signatures.code_help')"
                    :validate-status="form.errors.code ? 'error' : ''"
                    :help="form.errors.code"
                >
                    <Input
                        v-model:value="form.code"
                        size="large"
                        :maxlength="40"
                        :placeholder="$t('signatures.code')"
                    />
                </FormItem>

                <FormItem
                    :label="$t('signatures.title')"
                    :validate-status="form.errors.title ? 'error' : ''"
                    :help="form.errors.title"
                >
                    <Input v-model:value="form.title" size="large" :maxlength="160" />
                </FormItem>

                <FormItem
                    :label="$t('signatures.relation')"
                    :validate-status="form.errors.relation ? 'error' : ''"
                    :help="form.errors.relation"
                >
                    <Select v-model:value="form.relation" size="large" style="max-width:280px">
                        <SelectOption v-for="r in relations" :key="r" :value="r">
                            {{ $t('signatures.relation_' + r) }}
                        </SelectOption>
                    </Select>
                </FormItem>

                <FormItem
                    :label="$t('signatures.user')"
                    :tooltip="$t('signatures.user_help')"
                    :validate-status="form.errors.user_id ? 'error' : ''"
                    :help="form.errors.user_id"
                >
                    <Select
                        v-model:value="form.user_id"
                        size="large"
                        allow-clear
                        show-search
                        option-filter-prop="label"
                        style="max-width:360px"
                    >
                        <SelectOption v-for="u in users" :key="u.id" :value="u.id" :label="u.name">
                            {{ u.name }}
                        </SelectOption>
                    </Select>
                    <!-- Enlazar a alguien que todavía no cargó su firma es
                         válido, pero hay que saberlo ANTES: el informe va a
                         dejar la línea en blanco. -->
                    <Alert
                        v-if="usuarioElegido && !usuarioElegido.ready"
                        type="warning"
                        show-icon
                        class="sig-note"
                        :message="$t('signatures.no_image')"
                    />
                </FormItem>

                <FormItem
                    v-if="esExterno"
                    :label="$t('signatures.image')"
                    :tooltip="$t('signatures.image_help')"
                    :validate-status="form.errors.image ? 'error' : ''"
                    :help="form.errors.image"
                >
                    <!-- La firma vigente, si ya hay una guardada. -->
                    <img
                        v-if="signature?.image_url && !imagenLista"
                        :src="signature.image_url"
                        alt=""
                        class="sig-preview"
                    >
                    <!-- La nueva, apenas se sube o se dibuja: confirmación de
                         que el archivo quedó tomado antes de guardar. -->
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
                    :label="$t('signatures.is_active')"
                    :tooltip="$t('signatures.is_active_help')"
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
                    :cancel-href="route('business_management.signatures.index')"
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
    max-height: 90px;
    margin-bottom: 10px;
    border: 1px solid var(--color-border, #d9d9d9);
    border-radius: 6px;
    padding: 6px 10px;
    background: #fff;
}
.sig-mode { display: block; margin-bottom: 10px; }
.sig-note { margin-top: 8px; max-width: 480px; }
</style>
