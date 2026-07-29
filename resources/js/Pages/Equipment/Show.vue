<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Alert,
} from 'ant-design-vue';
import { ApartmentOutlined } from '@ant-design/icons-vue';

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
    equipment: { type: Object, required: true },
    activity:   { type: Array,  default: () => [] },
    recordAudit: { type: Object, default: null },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.equipment.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);
</script>

<template>
    <Head :title="equipment.name" />

    <div class="show-page sap-show">
        <SectionHeader
            :back-href="route('business_management.equipment.index')"
            :title="equipment.name"
            :icon-bg="iconBg"
        >
            <template #icon><ApartmentOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="equipment.is_active ? 'success' : 'default'" :bordered="false">
                        {{ equipment.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="equipment"
                    route-prefix="business_management"
                    :slug="equipment.slug"
                    :id="equipment.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('equipment.edit')"
                    :can-delete="can('equipment.delete')"
                    :can-see-audit="canSeeAudit"
                    :is-super="isSuper"
                    :is-global="equipment.tenant_id === null"
                    :lock="equipment.lock"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(equipment.deleted_at) }}</div>
                <div v-if="equipment.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ equipment.deleter.name }}
                </div>
                <div v-if="equipment.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ equipment.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="equipment" route-prefix="business_management" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length">
            <template #general>
                <Card :bodyStyle="{ padding: 14 }" class="info-card">
                    <template #title><ApartmentOutlined /> {{ $t('global.general_info') }}</template>
                    <div class="spec-grid">
                        <!-- El id de la base y el slug son para dar soporte, no información
                             del laboratorio: van en caja como el resto —la ficha se ve
                             pareja— pero AL FINAL, y solo los ve el super. El `order` de
                             `.spec-cell--id` los manda al final de la grilla, así que la
                             caja no tiene que moverse de lugar en el archivo. -->
                        <div v-if="isSuper" class="spec-cell spec-cell--id">
                            <span class="spec-cell__label">ID</span>
                            <span class="spec-cell__value">{{ equipment.id }}</span>
                        </div>
                        <div v-if="isSuper" class="spec-cell spec-cell--id">
                            <span class="spec-cell__label">Slug</span>
                            <span class="spec-cell__value">{{ equipment.slug }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('equipment.name') }}</span>
                            <span class="spec-cell__value">{{ equipment.name }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('equipment.serial') }}</span>
                            <span class="spec-cell__value">{{ equipment.serial }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('equipment.tag') }}</span>
                            <span class="spec-cell__value">{{ equipment.tag }}</span>
                        </div>
<!-- La placa entera en una sola celda, como está impresa en el equipo
                             ("500 / 220 / 33"), en vez de tres casillas sueltas que
                             obligan a recomponerla de memoria. -->
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('equipment.voltage_label') }} (kV)</span>
                            <span class="spec-cell__value">{{ equipment.voltage_label }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('equipment.power_label') }} (MVA)</span>
                            <span class="spec-cell__value">{{ equipment.power_label }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('equipment.phases') }}</span>
                            <span class="spec-cell__value">{{ equipment.phases }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('equipment.manufacture_year') }}</span>
                            <span class="spec-cell__value">{{ equipment.manufacture_year }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('equipment.oil_volume') }}</span>
                            <span class="spec-cell__value">{{ equipment.oil_volume }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('equipment.external_ref') }}</span>
                            <span class="spec-cell__value">{{ equipment.external_ref }}</span>
                        </div>
                        <!-- Estado: siempre al final. -->
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('equipment.is_active') }}</span>
                            <span class="spec-cell__value">
                                <Tag :color="equipment.is_active ? 'success' : 'default'" :bordered="false">
                                    {{ equipment.is_active ? $t('global.active') : $t('global.inactive') }}
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
.info-card { margin-bottom: 12px; border-radius: 8px; }

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
