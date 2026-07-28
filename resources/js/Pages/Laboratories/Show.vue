<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Alert,
} from 'ant-design-vue';
import { TeamOutlined, ControlOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ViewDeletedButton from '@/Components/Common/ViewDeletedButton.vue';
import RecordHistory from '@/Components/Common/RecordHistory.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AppLayout });

const props = defineProps({
    laboratory: { type: Object, required: true },
    activity:   { type: Array,  default: () => [] },
    recordAudit: { type: Object, default: null },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.laboratory.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);
</script>

<template>
    <Head :title="laboratory.name" />

    <div class="show-page sap-show">
        <SectionHeader
            :back-href="route('business_management.laboratories.index')"
            :title="laboratory.name"
            :icon-bg="iconBg"
        >
            <template #icon><ControlOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="laboratory.is_active ? 'success' : 'default'" :bordered="false">
                        {{ laboratory.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="laboratories"
                    route-prefix="business_management"
                    :slug="laboratory.slug"
                    :id="laboratory.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('laboratories.edit')"
                    :can-delete="can('laboratories.delete')"
                    :can-see-audit="canSeeAudit"
                :is-super="isSuper"
                :is-global="laboratory.tenant_id === null"
                :lock="laboratory.lock"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(laboratory.deleted_at) }}</div>
                <div v-if="laboratory.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ laboratory.deleter.name }}
                </div>
                <div v-if="laboratory.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ laboratory.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="laboratories" route-prefix="business_management" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length">
            <template #general>
                <Card :bodyStyle="{ padding: 14 }" class="info-card">
                    <template #title><ControlOutlined /> {{ $t('global.general_info') }}</template>
                    <div class="spec-grid">
                        <!-- El id de la base y el slug NO son información del
                             laboratorio: son para dar soporte. Iban en dos cajas del
                             mismo tamaño que el nombre, compitiendo con lo que sí
                             importa. `order` los manda al pie de la grilla sin
                             depender de dónde estén escritos. -->
                        <p v-if="isSuper" class="spec-ids">
                            <span><b>ID</b> {{ laboratory.id }}</span>
                            <span><b>Slug</b> {{ laboratory.slug }}</span>
                        </p>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('laboratories.name') }}</span>
                            <span class="spec-cell__value">{{ laboratory.name }}</span>
                        </div>
                        <div v-if="laboratory.code" class="spec-cell">
                            <span class="spec-cell__label">{{ $t('laboratories.code') }}</span>
                            <span class="spec-cell__value"><code>{{ laboratory.code }}</code></span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('laboratories.is_active') }}</span>
                            <span class="spec-cell__value">
                                <Tag :color="laboratory.is_active ? 'success' : 'default'" :bordered="false">
                                    {{ laboratory.is_active ? $t('global.active') : $t('global.inactive') }}
                                </Tag>
                            </span>
                        </div>
                    </div>
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
.muted { color: var(--color-text-muted); font-size: 0.8125rem; }
.deleted-alert { margin-bottom: 16px; }
.info-card { margin-bottom: 12px; border-radius: 6px; }

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
