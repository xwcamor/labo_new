<script setup>
/**
 * Confirmar la entrega: el acto que emite los correlativos.
 *
 * Se pide UN dato —cuántas muestras trae— y se dice, antes de tocar el botón,
 * QUÉ números se van a emitir. Es una operación que no se deshace: un número
 * emitido no vuelve a la bolsa aunque después se dé de baja la muestra, porque
 * reemitirlo significaría atribuirle a otra muestra un resultado ajeno.
 *
 * El rango que se muestra es una ESTIMACIÓN y se dice: entre que la pantalla se
 * dibuja y alguien confirma pueden entrar otras recepciones. El número real lo
 * emite el servidor, dentro de la misma transacción que crea las muestras.
 */
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Alert, Button, Card, InputNumber, Popconfirm } from 'ant-design-vue';
import { NumberOutlined } from '@ant-design/icons-vue';

import { codeRange } from '@/Pages/Receptions/config/format';

const props = defineProps({
    reception:  { type: Object, required: true },
    nextNumber: { type: String, default: null },
    disabled:   { type: Boolean, default: false },
});

const count = ref(1);
const processing = ref(false);

const range = computed(() => codeRange(props.nextNumber, count.value));

const confirm = () => {
    if (!count.value || count.value < 1) return;

    router.post(
        route('lab_management.receptions.confirm', props.reception.slug),
        { samples: count.value },
        {
            preserveScroll: true,
            onStart:  () => { processing.value = true; },
            onFinish: () => { processing.value = false; },
        },
    );
};
</script>

<template>
    <Card class="rc-confirm" :body-style="{ padding: '16px 18px' }">
        <h2 class="rc-confirm__title">{{ $t('receptions.confirm') }}</h2>
        <p class="rc-confirm__help">{{ $t('receptions.confirm_help') }}</p>

        <div class="rc-confirm__row">
            <label class="rc-confirm__label" for="rc-how-many">
                {{ $t('receptions.how_many') }}
            </label>
            <InputNumber
                id="rc-how-many"
                v-model:value="count"
                :min="1"
                :max="500"
                size="large"
                class="rc-confirm__input"
            />

            <Popconfirm
                :title="$t('global.are_you_sure')"
                :ok-text="$t('global.confirm')"
                :cancel-text="$t('global.cancel')"
                :disabled="disabled || !count"
                @confirm="confirm"
            >
                <Button
                    type="primary"
                    size="large"
                    :loading="processing"
                    :disabled="disabled || !count"
                >
                    <NumberOutlined />
                    {{ $t('receptions.confirm') }}
                </Button>
            </Popconfirm>
        </div>

        <!-- Qué números se emitirían. Se dice ANTES, no después. -->
        <Alert
            v-if="range"
            type="info"
            show-icon
            class="rc-confirm__preview"
            :message="$t('receptions.will_issue', { count, from: range.from, to: range.to })"
            :description="$t('receptions.next_number', { code: nextNumber })"
        />
    </Card>
</template>

<style scoped>
.rc-confirm { margin-bottom: 16px; }
.rc-confirm__title { font-size: 1rem; font-weight: 600; margin: 0 0 4px; color: var(--color-text); }
.rc-confirm__help { font-size: 0.8125rem; color: var(--color-text-muted); margin: 0 0 14px; max-width: 720px; }
.rc-confirm__row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.rc-confirm__label { font-size: 0.875rem; color: var(--color-text); }
.rc-confirm__input { width: 120px; }
.rc-confirm__preview { margin-top: 14px; }
@media (max-width: 768px) {
    .rc-confirm__row { align-items: stretch; flex-direction: column; }
    .rc-confirm__input { width: 100%; }
}
</style>
