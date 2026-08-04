<script setup>
/**
 * Acciones de fila del listado de hojas de trabajo: Ver · Editar · Bloquear /
 * Desbloquear · Eliminar.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL CANDADO SE PONE DESDE EL LISTADO, NO SOLO DESDE LA FICHA              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Congelar una hoja es lo que hace el supervisor al cerrar la jornada, y hasta
 * ahora obligaba a entrar hoja por hoja. Acá se ofrece en la fila, con la misma
 * confirmación y las MISMAS reglas de nivel que la ficha: quien puso el candado
 * define quién lo saca (`lock_scope`: 'super' solo lo saca el super).
 *
 * Esconder el botón es cortesía: la ruta declara `role:super|admin` y el trait
 * vuelve a decidir en el servidor.
 */
import { computed } from 'vue';
import { Button, Dropdown, Menu, MenuItem, Popconfirm, Space, Tag, Tooltip } from 'ant-design-vue';
import { Link, router } from '@inertiajs/vue3';
import {
    DeleteOutlined, EditOutlined, EllipsisOutlined, EyeOutlined,
    LockOutlined, UnlockOutlined,
} from '@ant-design/icons-vue';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

const props = defineProps({
    record:    { type: Object,  required: true },
    isMobile:  { type: Boolean, default: false },
    // Compacto (tabla en pantalla chica): las acciones se colapsan en un kebab.
    compact:   { type: Boolean, default: false },
    isSuper:   { type: Boolean, default: false },
    isAdmin:   { type: Boolean, default: false },
    canEdit:   { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const isLocked = computed(() => !!(props.record.is_locked ?? props.record.locked_at));

// Las mismas dos reglas del trait Lockable, espejadas: poner el candado es de
// super o admin; sacarlo, del super siempre y del admin solo si el candado es
// de nivel 'tenant' (un candado del super el admin lo ve y no lo toca).
const canLock   = computed(() => !isLocked.value && (props.isSuper || props.isAdmin));
const canUnlock = computed(() => isLocked.value
    && (props.isSuper || (props.isAdmin && props.record.lock_scope === 'tenant')));

const lockedBySystem = computed(() => props.record.lock_scope === 'super');

/**
 * El popup de confirmación se monta en el BODY, no en la celda.
 *
 * La columna de acciones va fija a la derecha, y una celda fija de Ant Design
 * es su propio contexto de apilado con recorte. El popup nacía adentro: se
 * dibujaba a tamaño completo, la biblioteca lo volvía a medir contra el
 * recorte y lo reacomodaba, y eso se veía como un cartel que aparece grande y
 * se achica de golpe. Montado en el body se mide una sola vez.
 */
const popupToBody = () => document.body;

const showUrl   = computed(() => route('lab_management.worksheets.show', props.record.slug));
const editUrl   = computed(() => route('lab_management.worksheets.edit', props.record.slug));
const deleteUrl = computed(() => route('lab_management.worksheets.delete', props.record.slug));

const doLock = () => router.post(
    route('lab_management.worksheets.lock', props.record.slug), {}, { preserveScroll: true },
);
const doUnlock = () => router.post(
    route('lab_management.worksheets.unlock', props.record.slug), {}, { preserveScroll: true },
);

// Menú kebab (modo compacto). El candado va por confirmación aparte: un
// MenuItem no admite Popconfirm sin cerrar el menú antes de responder.
const onMenu = ({ key }) => {
    if (key === 'view')        router.visit(showUrl.value);
    else if (key === 'edit')   router.visit(editUrl.value);
    else if (key === 'delete') router.visit(deleteUrl.value);
    else if (key === 'lock')   doLock();
    else if (key === 'unlock') doUnlock();
};
</script>

<template>
    <!-- Compacto (pantalla chica con tabla): kebab ⋯ con todo adentro. -->
    <div v-if="compact" class="row-actions-compact" @click.stop>
        <Dropdown :trigger="['click']" placement="bottomRight">
            <Button type="text" class="row-icon-btn" :aria-label="t('global.actions')">
                <EllipsisOutlined />
            </Button>
            <template #overlay>
                <Menu @click="onMenu">
                    <MenuItem key="view"><EyeOutlined /> {{ t('global.view') }}</MenuItem>
                    <MenuItem v-if="canEdit && !isLocked" key="edit"><EditOutlined /> {{ t('global.edit') }}</MenuItem>
                    <MenuItem v-if="canLock" key="lock"><LockOutlined /> {{ t('locks.lock') }}</MenuItem>
                    <MenuItem v-if="canUnlock" key="unlock"><UnlockOutlined /> {{ t('locks.unlock') }}</MenuItem>
                    <MenuItem v-if="canDelete && !isLocked" key="delete" danger><DeleteOutlined /> {{ t('global.delete') }}</MenuItem>
                    <MenuItem v-if="isLocked && !canUnlock" key="locked" disabled>
                        <LockOutlined /> {{ lockedBySystem ? t('locks.locked_by_super') : t('locks.locked_tag') }}
                    </MenuItem>
                </Menu>
            </template>
        </Dropdown>
    </div>

    <!-- Móvil (tarjetas): iconos grandes, mismo orden. -->
    <div v-else-if="isMobile" class="row-actions-mobile" @click.stop>
        <Tooltip :title="t('global.view')">
            <Link :href="showUrl">
                <Button type="text" class="row-icon-btn" :aria-label="t('global.view')">
                    <EyeOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Tooltip v-if="canEdit && !isLocked" :title="t('global.edit')">
            <Link :href="editUrl">
                <Button type="text" class="row-icon-btn" :aria-label="t('global.edit')">
                    <EditOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Popconfirm v-if="canLock" :get-popup-container="popupToBody" :title="t('locks.lock_confirm')" :ok-text="t('locks.lock')" @confirm="doLock">
            <Tooltip :title="t('locks.lock')">
                <Button type="text" class="row-icon-btn" :aria-label="t('locks.lock')">
                    <LockOutlined />
                </Button>
            </Tooltip>
        </Popconfirm>
        <Popconfirm v-if="canUnlock" :get-popup-container="popupToBody" :title="t('locks.unlock_confirm')" :ok-text="t('locks.unlock')" @confirm="doUnlock">
            <Tooltip :title="t('locks.unlock')">
                <Button type="text" class="row-icon-btn" :aria-label="t('locks.unlock')">
                    <UnlockOutlined />
                </Button>
            </Tooltip>
        </Popconfirm>
        <Tooltip v-if="canDelete && !isLocked" :title="t('global.delete')">
            <Link :href="deleteUrl">
                <Button type="text" danger class="row-icon-btn" :aria-label="t('global.delete')">
                    <DeleteOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Tooltip v-if="isLocked && !canUnlock" :title="lockedBySystem ? t('locks.locked_by_super_hint') : t('locks.locked_hint')">
            <Tag color="gold" :bordered="false"><LockOutlined /></Tag>
        </Tooltip>
    </div>

    <!-- Escritorio: Ver · Editar · (Des)bloquear · Eliminar. -->
    <Space v-else :size="4" class="row-actions-desktop" @click.stop>
        <Tooltip :title="t('global.view')">
            <Link :href="showUrl">
                <Button size="small" type="text" :aria-label="t('global.view')">
                    <EyeOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Tooltip v-if="canEdit && !isLocked" :title="t('global.edit')">
            <Link :href="editUrl">
                <Button size="small" type="text" :aria-label="t('global.edit')">
                    <EditOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Popconfirm v-if="canLock" :get-popup-container="popupToBody" :title="t('locks.lock_confirm')" :ok-text="t('locks.lock')" @confirm="doLock">
            <Tooltip :title="t('locks.lock')">
                <Button size="small" type="text" :aria-label="t('locks.lock')">
                    <LockOutlined />
                </Button>
            </Tooltip>
        </Popconfirm>
        <Popconfirm v-if="canUnlock" :get-popup-container="popupToBody" :title="t('locks.unlock_confirm')" :ok-text="t('locks.unlock')" @confirm="doUnlock">
            <Tooltip :title="t('locks.unlock')">
                <Button size="small" type="text" :aria-label="t('locks.unlock')">
                    <UnlockOutlined />
                </Button>
            </Tooltip>
        </Popconfirm>
        <Tooltip v-if="canDelete && !isLocked" :title="t('global.delete')">
            <Link :href="deleteUrl">
                <Button size="small" type="text" danger :aria-label="t('global.delete')">
                    <DeleteOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Tooltip v-if="isLocked && !canUnlock" :title="lockedBySystem ? t('locks.locked_by_super_hint') : t('locks.locked_hint')">
            <Tag color="gold" :bordered="false"><LockOutlined /></Tag>
        </Tooltip>
    </Space>
</template>

<style scoped>
.row-actions-compact {
    display: flex;
    justify-content: center;
    width: 100%;
}
.row-actions-mobile {
    display: flex;
    justify-content: flex-end;
    gap: 4px;
    width: 100%;
}
.row-icon-btn {
    width: 40px !important;
    height: 40px !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 !important;
}
.row-icon-btn :deep(.anticon) { font-size: 18px; }
</style>
