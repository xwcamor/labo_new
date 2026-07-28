<script setup>
/**
 * La cabecera de la entrega: qué llegó, de quién, cuándo y en qué condiciones.
 *
 * La verificación del envase (envase / volumen / etiqueta) se muestra SIEMPRE,
 * también cuando está bien. Un control que solo se ve cuando falla no es un
 * control: es un aviso, y el laboratorio tiene que poder demostrar que lo miró.
 */
import { computed } from 'vue';
import { Card, Descriptions, DescriptionsItem, Tag } from 'ant-design-vue';
import { CheckCircleFilled, CloseCircleFilled, ThunderboltFilled } from '@ant-design/icons-vue';

import ReceptionStatusTag from '@/Components/Receptions/ReceptionStatusTag.vue';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useViewport } from '@/Composables/useViewport';
import { plainDate } from '@/Pages/Receptions/config/format';

const props = defineProps({
    reception: { type: Object, required: true },
});

const { formatDateTimeFull } = useDateFormat();
const { isMobile } = useViewport();

/**
 * En pantalla chica la etiqueta va ARRIBA del valor, no al costado.
 *
 * Con dos columnas, "Fecha de recepción" entra en un ancho de 90 px y se parte
 * a media palabra ("recepci / ón"): una etiqueta cortada así se lee mal y hace
 * dudar de qué dato es. Apiladas ocupan una línea más y se leen enteras.
 */
const layout = computed(() => (isMobile.value ? 'vertical' : 'horizontal'));

const dash = '—';

/** El muestreador puede ser un usuario del sistema o un nombre suelto. */
const sampler = computed(
    () => props.reception.sampler?.name || props.reception.sampler_name || null,
);

const checks = computed(() => [
    { key: 'container_ok', ok: !!props.reception.container_ok },
    { key: 'volume_ok',    ok: !!props.reception.volume_ok },
    { key: 'label_ok',     ok: !!props.reception.label_ok },
]);
</script>

<template>
    <Card class="rc-head" :body-style="{ padding: '16px 18px' }">
        <Descriptions :column="{ xs: 1, sm: 2, lg: 3 }" :layout="layout" size="small" bordered>
            <DescriptionsItem :label="$t('receptions.code')">
                {{ reception.code || dash }}
            </DescriptionsItem>

            <DescriptionsItem :label="$t('receptions.customer')">
                {{ reception.customer?.name ?? dash }}
            </DescriptionsItem>

            <DescriptionsItem :label="$t('receptions.status')">
                <ReceptionStatusTag :status="reception.status" />
                <Tag v-if="reception.is_urgent" color="red" :bordered="false">
                    <ThunderboltFilled /> {{ $t('receptions.is_urgent') }}
                </Tag>
            </DescriptionsItem>

            <DescriptionsItem :label="$t('receptions.received_at')">
                {{ plainDate(reception.received_at) || dash }}
            </DescriptionsItem>

            <DescriptionsItem :label="$t('receptions.due_at')">
                {{ plainDate(reception.due_at) || dash }}
            </DescriptionsItem>

            <DescriptionsItem :label="$t('receptions.sampler')">
                {{ sampler ?? dash }}
            </DescriptionsItem>

            <DescriptionsItem :label="$t('receptions.service_order')">
                {{ reception.service_order || dash }}
            </DescriptionsItem>

            <DescriptionsItem :label="$t('receptions.packages')">
                {{ reception.packages ?? dash }}
            </DescriptionsItem>

            <DescriptionsItem :label="$t('receptions.section_check')">
                <div class="rc-head__checks">
                    <span
                        v-for="check in checks"
                        :key="check.key"
                        class="rc-head__check"
                        :class="{ 'rc-head__check--ko': !check.ok }"
                    >
                        <CheckCircleFilled v-if="check.ok" />
                        <CloseCircleFilled v-else />
                        {{ $t(`receptions.${check.key}`) }}
                    </span>
                </div>
            </DescriptionsItem>

            <DescriptionsItem v-if="reception.confirmed_at" :label="$t('receptions.confirmed_at')">
                {{ formatDateTimeFull(reception.confirmed_at) }}
            </DescriptionsItem>

            <DescriptionsItem v-if="reception.confirmer" :label="$t('receptions.confirmed_by')">
                {{ reception.confirmer.name }}
            </DescriptionsItem>

            <DescriptionsItem v-if="reception.notes" :label="$t('receptions.notes')" :span="3">
                {{ reception.notes }}
            </DescriptionsItem>
        </Descriptions>
    </Card>
</template>

<style scoped>
.rc-head { margin-bottom: 16px; }
.rc-head__checks { display: flex; flex-wrap: wrap; gap: 4px 14px; }
.rc-head__check {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.78rem;
    color: #52c41a;
}
.rc-head__check--ko { color: var(--color-text-muted); }
</style>
