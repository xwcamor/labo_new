<script setup>
/**
 * Una lista de etiquetas que se despliega.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ EXISTE                                                           │
 * └──────────────────────────────────────────────────────────────────────────┘
 * La celda de "pruebas pedidas" dibujaba una etiqueta por prueba, todas. Con
 * las cuatro del ensayo normal se lee bien; con las veintinueve del catálogo
 * —o con las cien que puede pedir una campaña— la fila crece hasta empujar la
 * tabla y la pantalla se vuelve una pared de etiquetas.
 *
 * El sistema anterior resolvía esto y vale copiarle la idea: mostraba
 * "N muestras registradas" con un botón de expandir, y el detalle salía solo
 * cuando se pedía (o solo, si la búsqueda había pegado adentro). Acá igual:
 * hasta `limit` se muestran todas —esconder tres etiquetas no ayuda a nadie—, y
 * pasado ese número la celda se resume en una línea con el total y se despliega
 * a pedido.
 *
 * Lo que NO hace: recortar en silencio. Un "+25" que no se puede pulsar es
 * peor que la pared de etiquetas, porque el dato deja de existir para quien
 * mira la pantalla. El resumen SIEMPRE dice cuántas hay y siempre se abre.
 */
import { computed, ref } from 'vue';
import { Button, Tag, Tooltip } from 'ant-design-vue';
import { DownOutlined, UpOutlined } from '@ant-design/icons-vue';
import { useI18n } from '@/Plugins/i18n';

// `tc`, no `t`: el resumen es una cuenta y la cadena viene pluralizada al
// estilo Laravel ("{1} 1 prueba pedida|[2,*] :count pruebas pedidas"). Con `t`
// el rótulo saldría con los separadores crudos.
const { tc } = useI18n();

const props = defineProps({
    /**
     * Las etiquetas: `[{ key, label, color?, title? }]`. El componente no sabe
     * de pruebas ni de estados; recibe etiquetas ya resueltas.
     */
    items: { type: Array, default: () => [] },
    /**
     * Cuántas se muestran sin desplegar. Cuatro es el pedido normal del
     * laboratorio (fisicoquímico + cromatografía), así que el caso frecuente
     * no se esconde.
     */
    limit: { type: Number, default: 4 },
    /**
     * El texto del resumen cuando está plegado. Es una clave PLURALIZADA
     * ("{1} …|[2,*] :count …") y recibe `:count`.
     */
    summaryKey: { type: String, default: 'global.n_items' },
    /** Qué mostrar cuando no hay ninguna. */
    emptyText: { type: String, default: '' },
});

const open = ref(false);

const collapsible = computed(() => props.items.length > props.limit);
const shown = computed(() => (collapsible.value && ! open.value ? [] : props.items));
</script>

<template>
    <span v-if="items.length === 0" class="ct-muted">{{ emptyText || '—' }}</span>

    <div v-else class="ct">
        <!-- Plegado: una sola línea con el total. No se muestran las primeras
             cuatro de las cien porque no son "las importantes": son las
             primeras. Con ese volumen lo único honesto es el número. -->
        <Button
            v-if="collapsible"
            type="text"
            size="small"
            class="ct__toggle"
            @click.stop="open = !open"
        >
            {{ tc(summaryKey, items.length) }}
            <UpOutlined v-if="open" />
            <DownOutlined v-else />
        </Button>

        <div v-if="shown.length" class="ct__tags">
            <template v-for="item in shown" :key="item.key">
                <Tooltip v-if="item.title" :title="item.title">
                    <Tag :color="item.color" :bordered="false">{{ item.label }}</Tag>
                </Tooltip>
                <Tag v-else :color="item.color" :bordered="false">{{ item.label }}</Tag>
            </template>
        </div>
    </div>
</template>

<style scoped>
.ct { display: flex; flex-direction: column; gap: 4px; align-items: flex-start; }
.ct__tags { display: flex; flex-wrap: wrap; gap: 4px; }
.ct__toggle {
    padding: 0 4px;
    height: 22px;
    font-size: 12px;
    color: var(--color-text-secondary);
}
.ct__toggle:hover { color: var(--color-primary); }
.ct-muted { color: var(--color-text-secondary); }
</style>
