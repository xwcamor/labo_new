<script setup>
/**
 * La hoja de trabajo abierta: la pantalla de bancada.
 *
 * Todo lo que gobierna esta pantalla lo decide el servidor y llega como dato:
 * si se puede escribir (`can.edit`), qué le falta a la hoja para admitir
 * muestras (`missing`) y qué control dibuja cada columna (`fieldTypes`). Acá no
 * se decide nada de eso, y es a propósito: en el sistema viejo las cuatro
 * reglas de la bancada —el cálculo, los obligatorios, el bloqueo y el orden de
 * patrón y duplicado— vivían en el HTML, y un envío directo las salteaba a las
 * cuatro.
 */
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Alert, Button, Card, Space, Tooltip } from 'ant-design-vue';
import { ControlOutlined, ProfileOutlined, TableOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import WorksheetStatusTag from '@/Components/Worksheets/WorksheetStatusTag.vue';
import WorksheetHeader from '@/Components/Worksheets/WorksheetHeader.vue';
import WorksheetGrid from '@/Components/Worksheets/WorksheetGrid.vue';
import WorksheetConstantsModal from '@/Components/Worksheets/WorksheetConstantsModal.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import RecordHistory from '@/Components/Common/RecordHistory.vue';
import { useAuth } from '@/Composables/useAuth';
import { useI18n } from '@/Plugins/i18n';
import { plainDate } from './config/format';

defineOptions({ layout: AppLayout });

const props = defineProps({
    worksheet:   { type: Object, required: true },
    fields:      { type: Array,  default: () => [] },
    fieldTypes:  { type: Object, default: () => ({}) },
    instruments: { type: Array,  default: () => [] },
    // Qué instrumentos ofrece CADA columna. El catálogo del laboratorio ya
    // decía qué equipo va en qué columna (la bureta en la bureta, el
    // colorímetro en el color); sin esto la grilla ofrecía los 24 en todas.
    instrumentsByField: { type: Object, default: () => ({}) },
    // Las pruebas pedidas que esta hoja todavía espera, para el selector de
    // muestra de la grilla.
    pendingTests: { type: Array, default: () => [] },
    // Quién registró cada fila, indexado por id de fila: `{ name, at }`.
    enteredBy:   { type: Object, default: () => ({}) },
    // Los tipos de fila que la prueba exige (patrón, duplicado).
    requiredKinds: { type: Array, default: () => [] },
    can:         { type: Object, default: () => ({}) },
    // El candado del registro (trait Lockable): quién puede ponerlo y sacarlo.
    lock:        { type: Object, default: null },
    missing:     { type: Array,  default: () => [] },
    // El historial del registro, como en el resto de las fichas. En un
    // laboratorio acreditado es lo primero que pide una auditoría: quién cargó
    // la bancada, quién la cerró y qué se le cambió.
    activity:    { type: Array,  default: () => [] },
    recordAudit: { type: Object, default: null },
});

const { t, tc } = useI18n();
const { can, canSeeAudit, isSuper } = useAuth();

/**
 * Los VALORES CONSTANTES de la prueba, a un clic de la grilla.
 *
 * El sistema anterior le colgaba a cada prueba su pantalla de «Valores
 * Constantes», al lado de «Muestras»: el analista la abría sin salir de lo que
 * estaba haciendo. Acá esos valores viven dentro del editor de columnas, entre
 * el tipo, la unidad, los decimales y la fórmula, así que cambiar el factor de
 * KOH de un lote nuevo obligaba a salir de la hoja y buscarlo. El botón solo
 * aparece si la prueba TIENE constantes: en las veintitrés que no las usan
 * sería un botón que abre una tabla vacía.
 */
const constantes = computed(() => props.fields.filter((f) => f.is_reusable));
const constantsOpen = ref(false);
const page = usePage();

const readonly = computed(() => !props.can.edit);

/**
 * Por qué la grilla está cerrada. Se dice, no se deduce: una pantalla
 * deshabilitada sin explicación es la que hace que el analista llame por
 * teléfono a preguntar si el sistema se rompió.
 */
const readonlyReason = computed(() => {
    if (props.can.edit) return '';
    if (props.worksheet.locked_at) return t('worksheets.errors.locked');
    if (props.worksheet.status !== 'draft') return t('worksheets.errors.not_draft');

    return '';
});

/**
 * Qué le falta a la hoja para admitir muestras. El servidor manda los tipos que
 * faltan como dato y el mensaje se arma acá con sus nombres traducidos: el
 * sistema viejo se limitaba a sacar la opción "Muestra" del select, sin decir
 * por qué faltaba.
 */
const missingMessage = computed(() => (props.missing.length === 0 ? '' : t(
    'worksheets.errors.missing_prerequisites',
    { kinds: props.missing.map((kind) => t(`worksheets.kind.${kind}`)).join(', ') },
)));

/**
 * Cuántas muestras GUARDADAS todavía no dicen de qué equipo son.
 *
 * Se cuenta sobre lo que hay en la base y no sobre lo que está tipeado en la
 * grilla, porque es lo guardado —y solo eso— lo que `ResultMaterializer` va a
 * leer al validar la hoja: una selección sin guardar no evita que el ensayo
 * quede fuera del informe, así que tampoco debe bajar el contador.
 */
const samplesWithoutEquipment = computed(
    () => (props.worksheet.rows ?? []).filter(
        // El equipo VIVO de la muestra manda sobre la foto de la fila: la
        // recepción lo asigna DESPUÉS de cargar la bancada, y el que usa el
        // materializador es el de la muestra. Contar la foto hacía avisar
        // "sin equipo" sobre muestras que ya tenían su transformador.
        (row) => row.kind === 'sample' && !(row.sample?.equipment_id ?? row.equipment_id),
    ).length,
);

/**
 * El aviso dice la CONSECUENCIA, no el estado. En el sistema viejo el enlace
 * entre la muestra y el equipo se resolvía por texto y, cuando fallaba, el
 * resultado desaparecía del informe del cliente sin que nadie se enterara. No
 * se bloquea la hoja —el analista a veces carga la bancada antes de que el
 * ingreso de la muestra esté registrado—, se avisa.
 */
const equipmentWarning = computed(() => (samplesWithoutEquipment.value === 0 ? '' : tc(
    'worksheets.equipment_missing_count',
    samplesWithoutEquipment.value,
)));

/** Lo que rebotó del servidor (obligatorios que faltan, hoja no editable…). */
const serverErrors = computed(() => Object.values(page.props.errors ?? {}).filter(Boolean));
</script>

<template>
    <Head :title="`${worksheet.definition?.name ?? $t('worksheets.show')} — ${plainDate(worksheet.run_date)}`" />

    <div class="show-page sap-show">
        <SectionHeader
            :back-href="route('lab_management.worksheets.index')"
            :title="worksheet.definition?.name ?? $t('worksheets.show')"
        >
            <template #icon><ProfileOutlined /></template>
            <template #subtitle>
                <Space :size="8" wrap>
                    <WorksheetStatusTag :status="worksheet.status" />
                    <span class="ws-sub">{{ plainDate(worksheet.run_date) }}</span>
                </Space>
            </template>
            <!-- Editar (la cabecera) / Eliminar / Bloquear: el estándar de los
                 módulos, arriba a la derecha. Los VALORES se editan en la
                 grilla; el candado congela grilla y cabecera. -->
            <template #actions>
                <!-- De la bancada al editor de columnas de LA PRUEBA. Es desde
                     acá que se pregunta "¿y dónde cambio esta tabla?": quien
                     administra tiene la grilla delante. SOLO el super lo ve
                     (2026-07-30, pedido del usuario): configurar la plantilla
                     cambia la tabla de TODAS las hojas de esa prueba, y esta
                     pantalla la abre el analista todos los días — un botón que
                     reescribe la plantilla no va en su cabecera. En pantalla
                     chica queda solo el icono: el texto se lo come el ancho y
                     el tooltip ya dice qué hace. -->
                <Tooltip
                    v-if="constantes.length > 0 && worksheet.definition?.slug"
                    :title="$t('worksheets.constants_hint', { test: worksheet.definition.name })"
                >
                    <Button class="ws-cfg-btn" @click="constantsOpen = true">
                        <template #icon><ControlOutlined /></template>
                        <span class="ws-cfg-btn__label">{{ $t('worksheets.constants') }}</span>
                    </Button>
                </Tooltip>

                <Tooltip
                    v-if="isSuper && worksheet.definition?.slug"
                    :title="$t('worksheets.configure_columns_hint', { test: worksheet.definition.name })"
                >
                    <Link :href="route('lab_management.test_definitions.fields.index', worksheet.definition.slug)">
                        <Button class="ws-cfg-btn">
                            <template #icon><TableOutlined /></template>
                            <span class="ws-cfg-btn__label">{{ $t('test_definitions.fields_edit') }}</span>
                        </Button>
                    </Link>
                </Tooltip>
                <EntityShowActions
                    module="worksheets"
                    route-prefix="lab_management"
                    :slug="worksheet.slug"
                    :id="worksheet.id"
                    :can-edit="!!can.edit_header"
                    :can-delete="!!can.delete"
                    :lock="lock"
                />
            </template>
        </SectionHeader>

        <Alert
            v-for="(error, index) in serverErrors"
            :key="index"
            type="error"
            show-icon
            class="ws-alert"
            :message="error"
        />

        <!-- Prerrequisitos de control de calidad: se explica, no se esconde. -->
        <Alert
            v-if="missingMessage"
            type="warning"
            show-icon
            class="ws-alert"
            :message="missingMessage"
        />

        <Alert
            v-if="readonlyReason"
            type="info"
            show-icon
            class="ws-alert"
            :message="readonlyReason"
        />

        <!-- Los avisos quedan ARRIBA de las pestañas a propósito: que la hoja
             esté cerrada o le falte un patrón no deja de ser cierto porque uno
             esté mirando el historial. -->
        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length">
            <template #general>
                <WorksheetHeader :worksheet="worksheet" />

                <Card :body-style="{ padding: '14px 16px' }">
                    <WorksheetGrid
                        :worksheet="worksheet"
                        :fields="fields"
                        :field-types="fieldTypes"
                        :instruments="instruments"
                        :instruments-by-field="instrumentsByField"
                        :pending-tests="pendingTests"
                        :entered-by="enteredBy"
                        :missing="missing"
                        :required-kinds="requiredKinds"
                        :readonly="readonly"
                    />
                </Card>

                <!-- Pegado a la barra de acciones: el recuento tiene que leerse
                     justo antes de cerrar, no arriba de todo donde ya nadie
                     mira. Es ámbar y no rojo porque no impide validar. -->
                <Alert
                    v-if="equipmentWarning"
                    type="warning"
                    show-icon
                    class="ws-alert ws-alert--equipment"
                    :message="equipmentWarning"
                />
            </template>

            <template #history>
                <RecordHistory :record-audit="recordAudit" :activity="activity" :can-see-activity="canSeeAudit" />
            </template>
        </EntityShowTabs>

        <WorksheetConstantsModal
            v-if="worksheet.definition"
            v-model:open="constantsOpen"
            :definition="worksheet.definition"
            :fields="fields"
            :can-edit="can('test_definitions.edit')"
        />
    </div>
</template>

<style scoped>
.ws-alert { margin-bottom: 12px; }
/* Sin separación con la franja de acciones: el aviso y la franja
   se leen como una sola cosa. */
.ws-alert--equipment { margin: 12px 0 0; }
.ws-sub { color: var(--color-text-muted); font-size: 0.8125rem; }

/* En pantalla chica el botón de configurar columnas queda solo con su icono:
   el rótulo se come el ancho de la cabecera y el tooltip ya dice qué hace. */
@media (max-width: 768px) {
    .ws-cfg-btn__label { display: none; }
}
</style>
