<script setup>
/**
 * Las acciones del flujo de la hoja, en la franja pegada al pie.
 *
 * Esconder un botón no autoriza nada: el permiso lo verifica la ruta y el
 * estado lo verifica el servicio. Acá se esconde por cortesía, para no ofrecer
 * lo que va a rebotar. En el sistema viejo era al revés: la pantalla de validar
 * escondía su enlace a los que no eran supervisores, pero la acción verificaba
 * el permiso de EDITAR, así que alcanzaba con escribir la dirección a mano.
 */
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button, Modal, Textarea, Tooltip } from 'ant-design-vue';
import { CheckOutlined, LockOutlined, StopOutlined } from '@ant-design/icons-vue';
import { useI18n } from '@/Plugins/i18n';

const props = defineProps({
    worksheet: { type: Object, required: true },
    can:       { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const submitting = ref(false);

const post = (routeName) => {
    submitting.value = true;
    router.post(route(routeName, props.worksheet.slug), {}, {
        preserveScroll: true,
        onFinish: () => { submitting.value = false; },
    });
};

const confirmClose = () => Modal.confirm({
    title:      t('worksheets.actions.close'),
    content:    t('worksheets.confirm.close'),
    okText:     t('worksheets.actions.close'),
    cancelText: t('global.cancel'),
    onOk: () => post('lab_management.worksheets.close'),
});

const confirmValidate = () => Modal.confirm({
    title:      t('worksheets.actions.validate'),
    content:    t('worksheets.confirm.validate'),
    okText:     t('worksheets.actions.validate'),
    cancelText: t('global.cancel'),
    onOk: () => post('lab_management.worksheets.validate'),
});

// ── Anulación ────────────────────────────────────────────────────────────
// El motivo es obligatorio y va en un modal propio: la hoja anulada no se
// borra, y sin el motivo la constancia no sirve para nada ante una auditoría.
const voidOpen   = ref(false);
const voidReason = ref('');
const voidError  = ref('');

const openVoid = () => {
    voidReason.value = '';
    voidError.value = '';
    voidOpen.value = true;
};

const submitVoid = () => {
    if (voidReason.value.trim().length < 3) {
        voidError.value = t('worksheets.errors.void_reason_required');

        return;
    }

    submitting.value = true;
    router.post(
        route('lab_management.worksheets.void', props.worksheet.slug),
        { void_reason: voidReason.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => { voidOpen.value = false; },
            onFinish:  () => { submitting.value = false; },
        },
    );
};
</script>

<template>
    <div class="bulk-bar">
        <span class="bulk-bar__label">
            {{ $t(`worksheets.state_help.${worksheet.status}`) }}
        </span>

        <div class="ws-actions">
            <Tooltip v-if="can.close" :title="$t('worksheets.confirm.close')">
                <Button type="primary" :loading="submitting" @click="confirmClose">
                    <LockOutlined /> {{ $t('worksheets.actions.close') }}
                </Button>
            </Tooltip>

            <Tooltip v-if="can.validate" :title="$t('worksheets.confirm.validate')">
                <Button type="primary" :loading="submitting" @click="confirmValidate">
                    <CheckOutlined /> {{ $t('worksheets.actions.validate') }}
                </Button>
            </Tooltip>

            <Tooltip v-if="can.void" :title="$t('worksheets.confirm.void')">
                <Button danger :loading="submitting" @click="openVoid">
                    <StopOutlined /> {{ $t('worksheets.actions.void') }}
                </Button>
            </Tooltip>
        </div>
    </div>

    <Modal
        v-model:open="voidOpen"
        :title="$t('worksheets.actions.void')"
        :ok-text="$t('worksheets.actions.void')"
        :cancel-text="$t('global.cancel')"
        :ok-button-props="{ danger: true, loading: submitting }"
        @ok="submitVoid"
    >
        <p class="ws-void__intro">{{ $t('worksheets.confirm.void') }}</p>

        <label class="ws-void__label">{{ $t('worksheets.void_reason') }}</label>
        <Textarea v-model:value="voidReason" :rows="3" :maxlength="500" show-count />

        <p v-if="voidError" class="ws-void__error">{{ voidError }}</p>
    </Modal>
</template>

<style scoped>
.ws-actions { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ws-void__intro { color: var(--color-text-muted); margin-bottom: 12px; }
.ws-void__label { display: block; font-weight: 600; margin-bottom: 6px; color: var(--color-text); }
.ws-void__error { color: var(--color-danger-bright); margin-top: 8px; }
</style>
