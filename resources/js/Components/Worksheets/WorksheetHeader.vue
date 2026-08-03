<script setup>
/**
 * La cabecera de la hoja: qué prueba, qué día, quién y en qué condiciones.
 *
 * Las condiciones ambientales están acá y no en cada fila. En el sistema viejo
 * eran columnas de la plantilla y el analista las recargaba en cada muestra:
 * son de la corrida, no de la muestra.
 */
import { computed } from 'vue';
import { Card, Descriptions, DescriptionsItem } from 'ant-design-vue';
import WorksheetStatusTag from '@/Components/Worksheets/WorksheetStatusTag.vue';
import { useDateFormat } from '@/Composables/useDateFormat';
import { plainDate } from '@/Pages/Worksheets/config/format';

const props = defineProps({
    worksheet: { type: Object, required: true },
});

const { formatDateTimeFull } = useDateFormat();

const definition = computed(() => props.worksheet.definition ?? null);
const dash = '—';
</script>

<template>
    <Card class="ws-head" :body-style="{ padding: '16px 18px' }">
        <Descriptions :column="{ xs: 1, sm: 2, lg: 3 }" size="small" bordered>
            <DescriptionsItem :label="$t('worksheets.test_definition')">
                {{ definition?.name ?? dash }}
            </DescriptionsItem>

            <DescriptionsItem :label="$t('worksheets.run_date')">
                {{ plainDate(worksheet.run_date) || dash }}
            </DescriptionsItem>

            <DescriptionsItem :label="$t('worksheets.analyst')">
                {{ worksheet.analyst?.name ?? dash }}
            </DescriptionsItem>

            <DescriptionsItem :label="$t('worksheets.status')">
                <WorksheetStatusTag :status="worksheet.status" with-help />
                <div class="ws-head__help">{{ $t(`worksheets.state_help.${worksheet.status}`) }}</div>
            </DescriptionsItem>

            <DescriptionsItem :label="$t('worksheets.ambient_temp_c')">
                {{ worksheet.ambient_temp_c !== null && worksheet.ambient_temp_c !== undefined
                    ? `${worksheet.ambient_temp_c} °C` : dash }}
            </DescriptionsItem>

            <DescriptionsItem :label="$t('worksheets.ambient_humidity')">
                {{ worksheet.ambient_humidity !== null && worksheet.ambient_humidity !== undefined
                    ? `${worksheet.ambient_humidity} %` : dash }}
            </DescriptionsItem>

            <DescriptionsItem v-if="worksheet.validated_by" :label="$t('worksheets.validated_by')">
                {{ worksheet.validator?.name ?? dash }}
            </DescriptionsItem>

            <DescriptionsItem v-if="worksheet.validated_at" :label="$t('worksheets.validated_at')">
                {{ formatDateTimeFull(worksheet.validated_at) }}
            </DescriptionsItem>

            <DescriptionsItem v-if="worksheet.void_reason" :label="$t('worksheets.void_reason')">
                {{ worksheet.void_reason }}
            </DescriptionsItem>

            <DescriptionsItem v-if="worksheet.notes" :label="$t('worksheets.notes')" :span="3">
                {{ worksheet.notes }}
            </DescriptionsItem>
        </Descriptions>
    </Card>
</template>

<style scoped>
.ws-head { margin-bottom: 16px; }
.ws-head__help { font-size: 0.75rem; color: var(--color-text-muted); margin-top: 4px; }
</style>
