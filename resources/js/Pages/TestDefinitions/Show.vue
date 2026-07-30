<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button, Card, Tag, Space, Alert } from 'ant-design-vue';
import {
    FileDoneOutlined, FolderOpenOutlined, ExperimentOutlined, TableOutlined,
    SettingOutlined,
} from '@ant-design/icons-vue';

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
    testDefinition: { type: Object, required: true },
    activity:       { type: Array,  default: () => [] },
    recordAudit:    { type: Object, default: null },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.testDefinition.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);

const yesNo = (v) => (v ? 'global.yes' : 'global.no');
</script>

<template>
    <Head :title="testDefinition.name" />

    <div class="show-page sap-show">
        <SectionHeader
            :back-href="route('lab_management.test_definitions.index')"
            :title="testDefinition.name"
            :icon-bg="iconBg"
        >
            <template #icon><FileDoneOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="testDefinition.code" :bordered="false">{{ testDefinition.code }}</Tag>
                    <Tag v-if="testDefinition.group" color="blue" :bordered="false">
                        {{ testDefinition.group.name }}
                    </Tag>
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="testDefinition.is_active ? 'success' : 'default'" :bordered="false">
                        {{ testDefinition.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="test_definitions"
                    route-prefix="lab_management"
                    :slug="testDefinition.slug"
                    :id="testDefinition.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('test_definitions.edit')"
                    :can-delete="can('test_definitions.delete')"
                    :can-see-audit="canSeeAudit"
                    :is-super="isSuper"
                    :is-global="testDefinition.tenant_id === null"
                    :lock="testDefinition.lock"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(testDefinition.deleted_at) }}</div>
                <div v-if="testDefinition.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ testDefinition.deleter.name }}
                </div>
                <div v-if="testDefinition.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ testDefinition.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="test_definitions" route-prefix="lab_management" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length">
            <template #general>
                <Card :bodyStyle="{ padding: 14 }" class="info-card">
                    <template #title><FileDoneOutlined /> {{ $t('test_definitions.section_identification') }}</template>
                    <div class="spec-grid">
                        <!-- El id de la base y el slug son para dar soporte, no información
                             del laboratorio: van en caja como el resto —la ficha se ve
                             pareja— pero AL FINAL, y solo los ve el super. El `order` de
                             `.spec-cell--id` los manda al final de la grilla, así que la
                             caja no tiene que moverse de lugar en el archivo. -->
                        <div v-if="isSuper" class="spec-cell spec-cell--id">
                            <span class="spec-cell__label">ID</span>
                            <span class="spec-cell__value">{{ testDefinition.id }}</span>
                        </div>
                        <div v-if="isSuper" class="spec-cell spec-cell--id">
                            <span class="spec-cell__label">Slug</span>
                            <span class="spec-cell__value">{{ testDefinition.slug }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_definitions.code') }}</span>
                            <span class="spec-cell__value"><code>{{ testDefinition.code || '—' }}</code></span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_definitions.name') }}</span>
                            <span class="spec-cell__value">{{ testDefinition.name }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_definitions.group') }}</span>
                            <span class="spec-cell__value">
                                <Tag v-if="testDefinition.group" color="blue" :bordered="false">
                                    <FolderOpenOutlined /> {{ testDefinition.group.name }}
                                </Tag>
                                <span v-else class="muted">{{ $t('test_definitions.group_none') }}</span>
                            </span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_definitions.sort_order') }}</span>
                            <span class="spec-cell__value">{{ testDefinition.sort_order ?? '—' }}</span>
                        </div>
                        <div class="spec-cell spec-cell--wide">
                            <span class="spec-cell__label">{{ $t('test_definitions.description') }}</span>
                            <span class="spec-cell__value">{{ testDefinition.description || '—' }}</span>
                        </div>
                    </div>
                </Card>

                <Card :bodyStyle="{ padding: 14 }" class="info-card">
                    <template #title><ExperimentOutlined /> {{ $t('test_definitions.section_sampling') }}</template>
                    <div class="spec-grid">
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_definitions.container') }}</span>
                            <span class="spec-cell__value">{{ testDefinition.container || '—' }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_definitions.chart_unit') }}</span>
                            <span class="spec-cell__value">{{ testDefinition.chart_unit || '—' }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_definitions.replicates') }}</span>
                            <span class="spec-cell__value">{{ testDefinition.replicates ?? 1 }}</span>
                        </div>
                    </div>
                </Card>

                <Card :bodyStyle="{ padding: 14 }" class="info-card">
                    <template #title><ExperimentOutlined /> {{ $t('test_definitions.section_control') }}</template>
                    <p class="card-hint">{{ $t('test_definitions.control_intro') }}</p>
                    <div class="spec-grid">
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_definitions.has_control') }}</span>
                            <span class="spec-cell__value">{{ $t(yesNo(testDefinition.has_control)) }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_definitions.requires_control') }}</span>
                            <span class="spec-cell__value">{{ $t(yesNo(testDefinition.requires_control)) }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_definitions.requires_duplicate') }}</span>
                            <span class="spec-cell__value">{{ $t(yesNo(testDefinition.requires_duplicate)) }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_definitions.is_grouped') }}</span>
                            <span class="spec-cell__value">{{ $t(yesNo(testDefinition.is_grouped)) }}</span>
                        </div>
                    </div>
                </Card>

                <Card :bodyStyle="{ padding: 14 }" class="info-card">
                    <template #title><TableOutlined /> {{ $t('test_definitions.fields') }}</template>
                    <p class="card-hint">{{ $t('test_definitions.fields_hint') }}</p>

                    <div class="spec-grid">
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_definitions.fields_count') }}</span>
                            <span class="spec-cell__value">{{ testDefinition.fields_count ?? 0 }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_definitions.results_count') }}</span>
                            <span class="spec-cell__value">{{ testDefinition.results_count ?? 0 }}</span>
                        </div>
                    </div>

                    <!-- El editor EXISTÍA y no había cómo llegar a él: acá había
                         quedado un comentario en lugar del enlace, y la única
                         forma de abrirlo era escribir la URL a mano. Es la
                         pantalla donde se define la tabla de la prueba —qué
                         columnas tiene, cuál es un resultado, qué se calcula—,
                         o sea la configuración que el laboratorio más va a
                         tocar. Va con botón primario, no como un enlace más. -->
                    <Space :size="8" class="fields-actions">
                        <Link :href="route('lab_management.test_definitions.fields.index', testDefinition.slug)">
                            <Button type="primary">
                                <template #icon><TableOutlined /></template>
                                {{ $t('test_definitions.fields_edit') }}
                            </Button>
                        </Link>
                        <Link :href="route('lab_management.test_definitions.constants.index', testDefinition.slug)">
                            <Button>
                                <template #icon><SettingOutlined /></template>
                                {{ $t('test_fields.constants') }}
                            </Button>
                        </Link>
                    </Space>

                </Card>

                <Card v-if="testDefinition.legacy_id" :bodyStyle="{ padding: 14 }" class="info-card">
                    <template #title><FileDoneOutlined /> {{ $t('test_definitions.section_traceability') }}</template>
                    <div class="spec-grid">
                        <!-- Solo lectura, siempre: lo escribe el importador del
                             sistema Rails y editarlo rompería la idempotencia
                             de `php artisan import:legacy-tests`. -->
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_definitions.legacy_id') }}</span>
                            <span class="spec-cell__value"><code>{{ testDefinition.legacy_id }}</code></span>
                        </div>
                        <p class="spec-note">{{ $t('test_definitions.legacy_id_help') }}</p>
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
.card-hint {
    margin: 0 0 14px 0;
    color: var(--color-text-muted);
    font-size: 0.8125rem;
    line-height: 1.6;
    max-width: 80ch;
}
.spec-cell--wide { grid-column: 1 / -1; }

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
