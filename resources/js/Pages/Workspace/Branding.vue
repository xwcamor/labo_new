<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { Card, Input, Textarea, Button, Form, FormItem, Alert, Tag, Switch, Checkbox, CheckboxGroup,
} from 'ant-design-vue';
import { BankOutlined, CameraOutlined, HighlightOutlined, ShopOutlined, SafetyCertificateOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

defineOptions({ layout: AppLayout });

const props = defineProps({
    workspace: { type: Object, required: true },
    signers:   { type: Array, default: () => [] },
    users:     { type: Array, default: () => [] },
    // Las hojas del informe clásico, con cuáles llevan el sello de fábrica.
    reportSheets: { type: Array, default: () => [] },
});

const form = useForm({
    address:           props.workspace.address ?? '',
    report_disclaimer: props.workspace.report_disclaimer ?? '',
    sample_description_default: props.workspace.sample_description_default ?? '',
    require_report_approval: props.workspace.require_report_approval ?? false,
    notify_approval_by_email: props.workspace.notify_approval_by_email ?? false,
});

/**
 * El estado de la firma de cada firmante, calculado en el servidor.
 *
 * Es lo único que el admin viene a mirar acá: si la firma va a salir ESTAMPADA
 * en el papel o si va a quedar una línea para firmar a mano. Depende de dos
 * cosas del usuario —tener imagen de firma cargada y haber activado la
 * auto-firma— y ninguna de las dos se administra desde el módulo Firmas, así que
 * verlo junto a la lista es lo que evita descubrirlo en el PDF.
 */
const STATUS_TAG = {
    ready:        { color: 'success' },
    no_autosign:  { color: 'warning' },
    no_signature: { color: 'warning' },
    external:     { color: 'default' },
};

const submit = () => form.put(route('workspace.update'), { preserveScroll: true });

// ── Sello de acreditación ────────────────────────────────────────────────
// Va aparte del logo de la empresa porque son dos cosas distintas: el logo
// identifica al laboratorio y el sello dice quién lo acredita. El número de
// certificado vence, así que el laboratorio tiene que poder cambiarlo —o
// sacarlo— el mismo día, sin esperar a un programador.
const accInput = ref(null);
const accUrl = ref(props.workspace.accreditation_logo_url);
const accNote = ref(props.workspace.accreditation_note ?? '');

const onAccPicked = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    router.post(route('workspace.accreditation.update'), {
        accreditation_logo: file,
        accreditation_note: accNote.value,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (page) => { accUrl.value = page.props.workspace?.accreditation_logo_url ?? accUrl.value; },
    });
    e.target.value = '';
};

const saveAccNote = () => router.post(route('workspace.accreditation.update'), {
    accreditation_note: accNote.value,
}, { preserveScroll: true });

const removeAcc = () => router.post(route('workspace.accreditation.update'), {
    remove_logo: true,
    accreditation_note: accNote.value,
}, { preserveScroll: true, onSuccess: () => { accUrl.value = null; } });

// ── Qué hojas llevan el sello ────────────────────────────────────────────
// El ALCANCE de la acreditación es del laboratorio: si el certificado suma o
// pierde una prueba, se marca acá y el sello cambia de hojas ese mismo día.
// Sin lista guardada rigen las hojas de fábrica (las del papel viejo).
const accSheets = ref(
    props.workspace.accredited_sheets
    ?? props.reportSheets.filter((h) => h.default).map((h) => h.key),
);

const esFabrica = computed(() => props.workspace.accredited_sheets === null);

const saveAccSheets = () => router.post(route('workspace.accreditation.update'), {
    accreditation_note: accNote.value,
    accredited_sheets: accSheets.value,
}, { preserveScroll: true });

const restoreAccSheets = () => router.post(route('workspace.accreditation.update'), {
    accreditation_note: accNote.value,
    accredited_sheets: null,
}, {
    preserveScroll: true,
    onSuccess: () => {
        accSheets.value = props.reportSheets.filter((h) => h.default).map((h) => h.key);
    },
});

// ── Logo del workspace: clic en el logo/título → file picker → sube. ──
const logoInput = ref(null);
const logoVersion = ref(0);
const logoUrl = ref(props.workspace.logo_url);
const onLogoPicked = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    router.post(route('workspace.logo.update'), { logo: file }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            logoVersion.value = Date.now();
            if (logoUrl.value) logoUrl.value = logoUrl.value.split('?')[0] + '?v=' + logoVersion.value;
            else router.reload({ only: ['workspace'] });
        },
        onFinish: () => { if (e.target) e.target.value = ''; },
    });
};
</script>

<template>
    <Head :title="t('tenants.workspace_title')" />

    <div class="form-page sap-form">
        <SectionHeader
            :title="workspace.name"
            :subtitle="t('tenants.workspace_subtitle')"
        >
            <template #icon><ShopOutlined /></template>
        </SectionHeader>

        <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp" style="display:none" @change="onLogoPicked">
        <input ref="accInput" type="file" accept="image/png,image/jpeg,image/webp" style="display:none" @change="onAccPicked">

        <Card class="form-card" :bodyStyle="{ padding: '24px 28px' }">
            <Alert
                v-if="form.recentlySuccessful"
                type="success"
                show-icon
                class="ws-saved"
                :message="t('global.updated_success')"
            />

            <Form layout="vertical" @submit.prevent="submit">
                <h2 class="form-section-title">{{ t('global.general_data') }}</h2>
                <!-- ── Logo de la empresa: sección explícita (no solo el ícono) ── -->
                <FormItem :label="t('tenants.logo_label')">
                    <div class="ws-logo-row">
                        <div class="ws-logo-box" @click="logoInput?.click()">
                            <img v-if="logoUrl" :src="logoUrl" class="ws-logo-box__img" alt="">
                            <BankOutlined v-else class="ws-logo-box__ph" />
                        </div>
                        <div class="ws-logo-meta">
                            <Button @click="logoInput?.click()">
                                <CameraOutlined /> {{ logoUrl ? t('tenants.logo_change') : t('tenants.logo_upload') }}
                            </Button>
                            <p class="ws-hint">{{ t('tenants.logo_help') }}</p>
                        </div>
                    </div>
                </FormItem>

                <!-- ── Sello de acreditación del informe ────────────────── -->
                <FormItem :label="t('tenants.accreditation_label')" :tooltip="t('tenants.accreditation_help')">
                    <div class="ws-logo-row">
                        <div class="ws-logo-box" @click="accInput?.click()">
                            <img v-if="accUrl" :src="accUrl" class="ws-logo-box__img" alt="">
                            <SafetyCertificateOutlined v-else class="ws-logo-box__ph" />
                        </div>
                        <div class="ws-logo-meta">
                            <Button @click="accInput?.click()">
                                <CameraOutlined /> {{ accUrl ? t('tenants.accreditation_change') : t('tenants.accreditation_upload') }}
                            </Button>
                            <Button v-if="accUrl" danger type="text" @click="removeAcc">
                                {{ t('tenants.accreditation_remove') }}
                            </Button>
                            <p class="ws-hint">{{ t('tenants.accreditation_logo_help') }}</p>
                        </div>
                    </div>
                    <Textarea
                        v-model:value="accNote"
                        :rows="3"
                        :maxlength="2000"
                        showCount
                        class="ws-acc-note"
                        :placeholder="t('tenants.accreditation_note_placeholder')"
                        @blur="saveAccNote"
                    />
                    <p class="ws-hint">{{ t('tenants.accreditation_note_help') }}</p>

                    <!-- Qué hojas llevan el sello. Es el alcance del
                         certificado, así que se edita acá y no en código. -->
                    <div class="ws-acc-sheets">
                        <p class="ws-acc-sheets__label">{{ t('tenants.accredited_sheets_label') }}</p>
                        <CheckboxGroup v-model:value="accSheets" @change="saveAccSheets">
                            <Checkbox
                                v-for="hoja in reportSheets"
                                :key="hoja.key"
                                :value="hoja.key"
                                class="ws-acc-sheets__item"
                            >
                                {{ hoja.label }}
                            </Checkbox>
                        </CheckboxGroup>
                        <p class="ws-hint">
                            {{ t('tenants.accredited_sheets_help') }}
                            <Button v-if="!esFabrica" type="link" size="small" @click="restoreAccSheets">
                                {{ t('tenants.accredited_sheets_restore') }}
                            </Button>
                        </p>
                    </div>
                </FormItem>

                <FormItem
                    :label="t('tenants.form_address_label')"
                    :tooltip="t('tenants.form_address_help')"
                    :validate-status="form.errors.address ? 'error' : ''"
                    :help="form.errors.address"
                >
                    <Input v-model:value="form.address" :maxlength="255" showCount />
                </FormItem>

                <FormItem
                    :label="t('tenants.form_disclaimer_label')"
                    :tooltip="t('tenants.form_disclaimer_help')"
                    :validate-status="form.errors.report_disclaimer ? 'error' : ''"
                    :help="form.errors.report_disclaimer"
                >
                    <Textarea v-model:value="form.report_disclaimer" :rows="4" :maxlength="2000" showCount />
                </FormItem>

                <!-- El texto con el que arranca la descripción de una muestra
                     nueva. Vive acá y no en el código porque cita un
                     PROCEDIMIENTO CON VERSIÓN («P-PG-TR-LA-18-20»), y los
                     procedimientos se revisan: clavado, cada informe seguiría
                     afirmando la versión vieja hasta que alguien haga un
                     deploy. Mismo caso que el descargo de arriba. -->
                <FormItem
                    :label="t('tenants.form_sample_description_label')"
                    :tooltip="t('tenants.form_sample_description_help')"
                    :validate-status="form.errors.sample_description_default ? 'error' : ''"
                    :help="form.errors.sample_description_default"
                >
                    <Textarea
                        v-model:value="form.sample_description_default"
                        :rows="2"
                        :maxlength="1000"
                        showCount
                        :placeholder="t('tenants.form_sample_description_placeholder')"
                    />
                </FormItem>

                <!-- ── Exigir aprobación de informes (etapa 2 de firmas) ──── -->
                <FormItem :label="t('tenants.require_approval_label')" :tooltip="t('tenants.require_approval_help')">
                    <div class="ws-toggle">
                        <Switch v-model:checked="form.require_report_approval" />
                        <span class="ws-hint">{{ t('tenants.require_approval_help') }}</span>
                    </div>
                    <!-- Canal del aviso: solo cuando el flujo está activo -->
                    <div v-if="form.require_report_approval" class="ws-toggle ws-subtoggle">
                        <Switch v-model:checked="form.notify_approval_by_email" />
                        <span class="ws-hint">{{ t('tenants.notify_email_help') }}</span>
                    </div>
                </FormItem>

                <!-- ── Quiénes firman los informes ────────────────────────
                     SOLO LECTURA. Acá había un editor con su propia lista de
                     firmantes, mientras el informe imprimía la del módulo
                     FIRMAS: dos listas para lo mismo, y el flujo de aprobación
                     gateaba con una y el papel se firmaba con la otra. Un
                     laboratorio podía cargar sus firmas en el módulo y no ver
                     nunca la bandeja de Aprobaciones.

                     Se muestra igual, porque es parte de cómo sale el papel del
                     workspace y porque cada fila dice si la firma va a salir
                     ESTAMPADA o solo como línea para firmar a mano — que es lo
                     que el admin viene a mirar acá. Editarla es un clic al
                     módulo. -->
                <FormItem :label="t('tenants.signers_label')">
                    <p class="ws-hint">{{ t('tenants.signers_managed_in_module') }}</p>

                    <div v-if="!signers.length" class="ws-signers__empty">
                        {{ t('tenants.signers_empty') }}
                    </div>

                    <div v-for="(s, i) in signers" :key="s.id" class="signer-row signer-row--ro">
                        <span class="signer-row__n">{{ i + 1 }}</span>
                        <span class="signer-row__relation-ro">{{ t('approvals.relation.' + s.relation) }}</span>
                        <span class="signer-row__who">
                            <b>{{ s.name }}</b>
                            <span class="signer-row__title-ro">{{ s.title }}</span>
                        </span>
                        <Tag :color="STATUS_TAG[s.status].color" class="signer-row__status">
                            {{ t('tenants.signer_status_' + s.status) }}
                        </Tag>
                    </div>

                    <Link :href="route('business_management.signatures.index')">
                        <Button type="dashed" block>
                            <HighlightOutlined /> {{ t('tenants.signers_go_to_module') }}
                        </Button>
                    </Link>
                </FormItem>

                <FormFooter
                    :cancel-href="route('workspace.edit')"
                    :is-edit="true"
                    :processing="form.processing"
                    floating
                />
            </Form>
        </Card>
    </div>
</template>

<style scoped>
.form-card { border-radius: 6px; }
.ws-saved { margin-bottom: 16px; }
.ws-acc-note { margin-top: 10px; }
.ws-hint { color: var(--color-text-muted, #6A6D70); font-size: 0.8rem; margin: 0 0 10px; }
.ws-toggle { display: flex; align-items: flex-start; gap: 12px; }
.ws-toggle .ws-hint { margin: 0; flex: 1; }
.ws-subtoggle { margin-top: 10px; padding-left: 16px; border-left: 2px solid var(--color-border-soft, #eceff2); }
.ws-logo-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.ws-logo-box {
    width: 92px; height: 92px; flex-shrink: 0;
    border: 1px dashed var(--color-border, #d4d8dd); border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; background: var(--color-surface-alt, #f6f8fa); overflow: hidden;
    transition: border-color .15s ease;
}
.ws-logo-box:hover { border-color: #0A6ED1; }
.ws-logo-box__img { max-width: 84px; max-height: 84px; object-fit: contain; }
.ws-logo-box__ph { font-size: 30px; color: var(--color-text-muted, #9aa0a6); }
.ws-logo-meta { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.ws-logo-meta .ws-hint { margin: 0; max-width: 420px; }
.signer-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
/* La fila de solo lectura: sin controles, y el nombre es lo que pesa. */
.signer-row--ro {
    padding: 8px 10px; border: 1px solid var(--color-border-soft, #eceff2); border-radius: 6px;
}
.signer-row__relation-ro { color: var(--color-text-muted, #6A6D70); font-size: 0.8rem; min-width: 110px; }
.signer-row__who { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.signer-row__title-ro { color: var(--color-text-muted, #6A6D70); font-size: 0.8rem; }
.ws-signers__empty {
    padding: 14px; margin-bottom: 8px; text-align: center;
    color: var(--color-text-muted, #6A6D70);
    border: 1px dashed var(--color-border, #d4d8dd); border-radius: 6px;
}
.signer-row__n { width: 18px; text-align: right; color: var(--color-text-muted, #9aa0a6); font-size: 0.82rem; }
.signer-row__relation { flex: 0 0 150px; }
.signer-row__title { flex: 0 0 190px; }
.signer-row__user { flex: 0 0 220px; }
.signer-row__name { flex: 1; }
.signer-row__status { margin: 0; }
.signer-row__actions { margin-left: auto; white-space: nowrap; }
@media (max-width: 768px) {
    .signer-row { flex-wrap: wrap; }
    .signer-row__relation, .signer-row__title, .signer-row__user, .signer-row__name { flex: 1 1 100%; }
}
.ws-acc-sheets { margin-top: 14px; }
.ws-acc-sheets__label { font-size: 0.875rem; font-weight: 600; margin: 0 0 8px; color: var(--color-text); }
/* En columna: son títulos largos y en fila se leerían pegados. */
.ws-acc-sheets :deep(.ant-checkbox-group) { display: flex; flex-direction: column; gap: 6px; }
.ws-acc-sheets__item { margin-left: 0 !important; }
</style>
