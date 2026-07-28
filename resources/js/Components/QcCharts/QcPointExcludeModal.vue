<script setup>
/**
 * Excluir (o volver a incluir) una medición del patrón.
 *
 * El motivo es obligatorio al excluir y opcional al reincorporar: lo que hay
 * que justificar es sacar un punto de la evidencia, no devolverlo.
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Modal, Form, FormItem, Textarea } from 'ant-design-vue';

import { useI18n } from '@/Plugins/i18n';

const props = defineProps({
    open:  { type: Boolean, default: false },
    /** Slug de la carta (la ruta usa el slug como clave). */
    chartSlug: { type: String, required: true },
    /** El punto sobre el que se actúa, o null cuando el modal está cerrado. */
    point: { type: Object, default: null },
});

const emit = defineEmits(['update:open']);

const { t } = useI18n();

const reason = ref('');
const error = ref('');
const submitting = ref(false);

/** Excluir si el punto todavía cuenta; reincorporar si ya estaba excluido. */
const excluding = computed(() => !props.point?.is_excluded);

watch(() => props.open, (isOpen) => {
    if (!isOpen) return;
    reason.value = props.point?.exclusion_reason ?? '';
    error.value = '';
});

const close = () => emit('update:open', false);

const submit = () => {
    if (!props.point) return;

    const trimmed = reason.value.trim();

    // Se valida antes de enviar para que el analista no pierda el texto que
    // escribió en un viaje al servidor; el backend exige lo mismo.
    if (excluding.value && trimmed.length < 3) {
        error.value = t('global.delete_reason_min_3');
        return;
    }

    // Al reincorporar el punto se CONSERVA el motivo por el que se lo había
    // sacado. El punto no lleva historial propio, así que borrarlo perdería sin
    // rastro la constancia de que el laboratorio detectó algo y lo revisó.
    const previous = (props.point.exclusion_reason ?? '').trim();

    submitting.value = true;
    router.patch(
        route('lab_management.qc_charts.points.update', [props.chartSlug, props.point.id]),
        {
            is_excluded: excluding.value,
            exclusion_reason: excluding.value
                ? trimmed
                : (previous.length >= 3 ? previous : null),
        },
        {
            preserveScroll: true,
            onSuccess: close,
            onFinish: () => { submitting.value = false; },
        },
    );
};
</script>

<template>
    <Modal
        :open="open"
        :title="excluding ? $t('qc_charts.exclude') : $t('global.restore')"
        :confirm-loading="submitting"
        :ok-text="excluding ? $t('qc_charts.exclude') : $t('global.restore')"
        :cancel-text="$t('global.cancel')"
        @ok="submit"
        @cancel="close"
        @update:open="(v) => emit('update:open', v)"
    >
        <Form layout="vertical">
            <FormItem
                :label="$t('qc_charts.exclusion_reason')"
                :required="excluding"
                :validate-status="error ? 'error' : ''"
                :help="error || $t('global.delete_reason_hint')"
            >
                <Textarea
                    v-model:value="reason"
                    :rows="3"
                    :maxlength="500"
                    show-count
                    :disabled="!excluding"
                    :placeholder="$t('global.delete_reason_placeholder')"
                />
            </FormItem>
        </Form>
    </Modal>
</template>
