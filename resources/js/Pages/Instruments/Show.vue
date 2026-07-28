<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Card, Tag, Space, Alert } from 'ant-design-vue';
import { ToolOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ViewDeletedButton from '@/Components/Common/ViewDeletedButton.vue';
import RecordHistory from '@/Components/Common/RecordHistory.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';
import { calibrationTagColor } from './config/columns';

defineOptions({ layout: AppLayout });

const props = defineProps({
    instrument: { type: Object, required: true },
    activity:   { type: Array,  default: () => [] },
    recordAudit: { type: Object, default: null },
});

const { t, tc } = useI18n();
const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.instrument.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);

const calStatus = computed(() => props.instrument.calibration_status ?? 'unknown');
const calColor  = computed(() => calibrationTagColor(calStatus.value));

/**
 * Frase de cuántos días faltan. Se muestra junto al semáforo porque "vence el
 * 12/09" no dice nada sin la referencia de hoy, y esa cuenta es justo la que
 * el analista hace de cabeza antes de usar el equipo.
 */
const calDetail = computed(() => {
    const d = props.instrument.calibration_days_left;
    if (d === null || d === undefined) return null;
    if (d === 0) return t('instruments.cal_due_today');
    if (d < 0)   return tc('instruments.cal_days_overdue', Math.abs(d));
    return tc('instruments.cal_days_left', d);
});
</script>

<template>
    <Head :title="instrument.name" />

    <div class="show-page sap-show">
        <SectionHeader
            :back-href="route('business_management.instruments.index')"
            :title="instrument.name"
            :icon-bg="iconBg"
        >
            <template #icon><ToolOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="instrument.code" :bordered="false">{{ instrument.code }}</Tag>
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="instrument.is_active ? 'success' : 'default'" :bordered="false">
                        {{ instrument.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <Tag :color="calColor" :bordered="false">
                        {{ $t('instruments.cal_status_' + calStatus) }}
                    </Tag>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="instruments"
                    route-prefix="business_management"
                    :slug="instrument.slug"
                    :id="instrument.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('instruments.edit')"
                    :can-delete="can('instruments.delete')"
                    :can-see-audit="canSeeAudit"
                    :is-super="isSuper"
                    :is-global="instrument.tenant_id === null"
                    :lock="instrument.lock"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(instrument.deleted_at) }}</div>
                <div v-if="instrument.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ instrument.deleter.name }}
                </div>
                <div v-if="instrument.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ instrument.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="instruments" route-prefix="business_management" />
            </template>
        </Alert>

        <!-- Aviso de calibración: es la información por la que existe el
             módulo, así que va ARRIBA de los datos, no escondida en una fila. -->
        <Alert
            v-if="!isDeleted && calStatus === 'expired'"
            type="error"
            show-icon
            class="deleted-alert"
            :message="$t('instruments.cal_expired_warning')"
        />
        <Alert
            v-else-if="!isDeleted && calStatus === 'unknown'"
            type="warning"
            show-icon
            class="deleted-alert"
            :message="$t('instruments.cal_unknown_warning')"
        />

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length">
            <template #general>
                <Card :bodyStyle="{ padding: 18 }" class="info-card">
                    <template #title><ToolOutlined /> {{ $t('instruments.section_identification') }}</template>
                    <div class="spec-grid">
                        <!-- ID y slug: solo el super (datos técnicos), y van primero. -->
                        <div v-if="isSuper" class="spec-cell">
                            <span class="spec-cell__label">ID</span>
                            <span class="spec-cell__value">{{ instrument.id }}</span>
                        </div>
                        <div v-if="isSuper" class="spec-cell">
                            <span class="spec-cell__label">Slug</span>
                            <span class="spec-cell__value"><code class="muted">{{ instrument.slug }}</code></span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('instruments.code') }}</span>
                            <span class="spec-cell__value"><code>{{ instrument.code || '—' }}</code></span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('instruments.name') }}</span>
                            <span class="spec-cell__value">{{ instrument.name }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('instruments.brand') }}</span>
                            <span class="spec-cell__value">{{ instrument.brand || '—' }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('instruments.model') }}</span>
                            <span class="spec-cell__value">{{ instrument.model || '—' }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('instruments.serial') }}</span>
                            <span class="spec-cell__value">{{ instrument.serial || '—' }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('instruments.location') }}</span>
                            <span class="spec-cell__value">{{ instrument.location || '—' }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('instruments.is_active') }}</span>
                            <span class="spec-cell__value">
                                <Tag :color="instrument.is_active ? 'success' : 'default'" :bordered="false">
                                    {{ instrument.is_active ? $t('global.active') : $t('global.inactive') }}
                                </Tag>
                            </span>
                        </div>
                    </div>
                </Card>

                <Card :bodyStyle="{ padding: 18 }" class="info-card">
                    <template #title><ToolOutlined /> {{ $t('instruments.section_calibration') }}</template>
                    <div class="spec-grid">
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('instruments.calibration_status') }}</span>
                            <span class="spec-cell__value">
                                <Tag :color="calColor" :bordered="false">
                                    {{ $t('instruments.cal_status_' + calStatus) }}
                                </Tag>
                                <span v-if="calDetail" class="muted">{{ calDetail }}</span>
                            </span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('instruments.calibrated_at') }}</span>
                            <span class="spec-cell__value">{{ instrument.calibrated_at || $t('instruments.cal_never') }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('instruments.calibration_due_at') }}</span>
                            <span class="spec-cell__value">{{ instrument.calibration_due_at || '—' }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('instruments.calibration_certificate') }}</span>
                            <span class="spec-cell__value">{{ instrument.calibration_certificate || '—' }}</span>
                        </div>
                    </div>
                </Card>

                <Card v-if="instrument.notes" :bodyStyle="{ padding: 18 }" class="info-card">
                    <template #title><ToolOutlined /> {{ $t('instruments.notes') }}</template>
                    <p class="notes">{{ instrument.notes }}</p>
                </Card>
            </template>

            <template #history>
                <RecordHistory :record-audit="recordAudit" :activity="activity" :can-see-activity="canSeeAudit" />
            </template>
        </EntityShowTabs>
    </div>
</template>

<style scoped>
.show-page { /* fullscreen — sin max-width, ocupa todo el ancho del content */ }
.muted { color: var(--color-text-muted); font-size: 0.8125rem; margin-left: 8px; }
.deleted-alert { margin-bottom: 16px; }
.info-card { margin-bottom: 16px; border-radius: 8px; }
.notes { margin: 0; white-space: pre-wrap; color: var(--color-text); }

@media (max-width: 767px) {
    :deep(.ant-descriptions-item-label) {
        width: auto !important;
        min-width: 0 !important;
        white-space: normal !important;
        font-weight: 500;
    }
    :deep(.ant-descriptions-item-content) { word-break: break-word; }
}
</style>
