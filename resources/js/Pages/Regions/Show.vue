<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Alert,
} from 'ant-design-vue';
import {
    GlobalOutlined,
} from '@ant-design/icons-vue';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
dayjs.extend(relativeTime);

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ViewDeletedButton from '@/Components/Common/ViewDeletedButton.vue';
import RecordHistory from '@/Components/Common/RecordHistory.vue';
import RegionFormModal from '@/Pages/Regions/FormModal.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AppLayout });

const props = defineProps({
    region:   { type: Object, required: true },
    activity: { type: Array,  default: () => [] },
    recordAudit: { type: Object, default: null },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.region.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);
// Relative timestamp para el chip del header — context ligero visible a todos.
const lastUpdatedRel = computed(() => props.region.updated_at ? dayjs(props.region.updated_at).fromNow() : null);

// Editar abre el diálogo sobre la ficha (regla Fiori: menos de 7 campos).
const editOpen = ref(false);
</script>

<template>
    <Head :title="region.name" />

    <div class="show-page sap-show">
        <SectionHeader
            :back-href="route('system_management.regions.index')"
            :title="region.name"
            :icon-bg="iconBg"
        >
            <template #icon><GlobalOutlined /></template>
            <template #subtitle>
                <Space :size="6" class="show-page__meta">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="region.is_active ? 'success' : 'default'" :bordered="false">
                        {{ region.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <span v-if="lastUpdatedRel" class="page-header__rel">
                        · {{ $t('global.updated_at') }} {{ lastUpdatedRel }}
                    </span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="regions"
                    :slug="region.slug"
                    :id="region.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('regions.edit')"
                    :can-delete="can('regions.delete')"
                    :can-see-audit="canSeeAudit"
                    edit-as-modal
                    @edit="editOpen = true"
                />
            </template>
        </SectionHeader>

        <Alert
            v-if="isDeleted"
            type="error"
            show-icon
            class="deleted-alert"
        >
            <template #message>
                <span v-html="$t('global.record_is_deleted')" />
            </template>
            <template #description>
                <div class="deleted-info">
                    <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(region.deleted_at) }}</div>
                    <div v-if="region.deleter">
                        <strong>{{ $t('global.deleted_by') }}:</strong> {{ region.deleter.name }} ({{ region.deleter.email }})
                    </div>
                    <div v-if="region.deleted_description" class="deleted-reason">
                        <strong>{{ $t('global.delete_description') }}:</strong> {{ region.deleted_description }}
                    </div>
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="regions" />
            </template>
        </Alert>

        <EntityShowTabs
            :show-history="canSeeAudit"
            :history-count="activity.length"
        >
            <!-- Tab 1 — Detalles: SOLO campos del dominio. Visible para todos. -->
            <template #general>
                <Card :title="$t('global.general_info')" :bodyStyle="{ padding: 14 }" class="info-card">
                    <div class="spec-grid">
                        <!-- El id de la base y el slug son para dar soporte, no información
                             del laboratorio: van en caja como el resto —la ficha se ve
                             pareja— pero AL FINAL, y solo los ve el super. El `order` de
                             `.spec-cell--id` los manda al final de la grilla, así que la
                             caja no tiene que moverse de lugar en el archivo. -->
                        <div v-if="isSuper" class="spec-cell spec-cell--id">
                            <span class="spec-cell__label">ID</span>
                            <span class="spec-cell__value">{{ region.id }}</span>
                        </div>
                        <div v-if="isSuper" class="spec-cell spec-cell--id">
                            <span class="spec-cell__label">Slug</span>
                            <span class="spec-cell__value">{{ region.slug }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('regions.name') }}</span>
                            <span class="spec-cell__value">{{ region.name }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('regions.is_active') }}</span>
                            <span class="spec-cell__value">
                                <Tag :color="region.is_active ? 'success' : 'default'" :bordered="false">
                                    {{ region.is_active ? $t('global.active') : $t('global.inactive') }}
                                </Tag>
                            </span>
                        </div>
                    </div>
                </Card>
            </template>

            <!-- Tab 2 — Historial: metadata del registro + timeline. Gated por canSeeAudit. -->
            <template #history>
                <RecordHistory :record-audit="recordAudit" :activity="activity" :can-see-activity="canSeeAudit" />
            </template>
        </EntityShowTabs>

        <!-- Edición en diálogo, sobre la ficha (regla Fiori: menos de 7 campos). -->
        <RegionFormModal
            :open="editOpen"
            :record="region"
            @close="editOpen = false"
        />
    </div>
</template>

<style scoped>
.show-page__meta { margin-top: 4px; }
.page-header__id,
.page-header__rel {
    font-size: 0.8125rem;
    color: var(--color-text-muted);
}
.deleted-alert { margin-bottom: 16px; }
.deleted-info { display: flex; flex-direction: column; gap: 4px; font-size: 0.875rem; }
.deleted-reason { margin-top: 6px; padding-top: 6px; border-top: 1px dashed rgba(0,0,0,0.1); }
.info-card { margin-bottom: 12px; border-radius: 6px; }
.muted { color: var(--color-text-muted); font-size: 0.8125rem; margin-left: 4px; }
</style>
