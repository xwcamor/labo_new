<script setup>
/**
 * Dibujar la firma a mano, con el mouse o el dedo.
 *
 * Es la alternativa a subir el escaneo: el firmante que está presente dibuja
 * acá y el trazo se convierte en un PNG transparente idéntico al que subiría.
 * A partir de ahí el circuito es el mismo — se guarda como imagen de la firma
 * y el informe la estampa sobre la línea.
 *
 * El lienzo NO pinta fondo a propósito: el PNG sale transparente y la firma se
 * apoya limpia sobre el papel, sin un rectángulo blanco que tape la línea.
 */
import { onMounted, ref } from 'vue';
import { Button, Space } from 'ant-design-vue';
import { ClearOutlined, CheckOutlined } from '@ant-design/icons-vue';

const emit = defineEmits(['done']);

const canvas = ref(null);
const drawing = ref(false);
const hasInk = ref(false);

let ctx = null;

// El lienzo se dibuja al doble de resolución y se muestra a la mitad: el trazo
// sale suave en vez de pixelado, que en una firma se nota.
const W = 560;
const H = 180;

onMounted(() => {
    const el = canvas.value;
    el.width = W * 2;
    el.height = H * 2;
    ctx = el.getContext('2d');
    ctx.scale(2, 2);
    ctx.lineWidth = 2.4;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    // Tinta azul de lapicera: es como se ven las firmas reales escaneadas.
    ctx.strokeStyle = '#1e3a8a';
});

const punto = (e) => {
    const r = canvas.value.getBoundingClientRect();
    const src = e.touches?.[0] ?? e;

    return { x: src.clientX - r.left, y: src.clientY - r.top };
};

const empezar = (e) => {
    e.preventDefault();
    drawing.value = true;
    const { x, y } = punto(e);
    ctx.beginPath();
    ctx.moveTo(x, y);
};

const trazar = (e) => {
    if (!drawing.value) return;
    e.preventDefault();
    const { x, y } = punto(e);
    ctx.lineTo(x, y);
    ctx.stroke();
    hasInk.value = true;
};

const soltar = () => { drawing.value = false; };

const limpiar = () => {
    ctx.clearRect(0, 0, W, H);
    hasInk.value = false;
};

/** Convierte el trazo en el mismo PNG que se subiría escaneado. */
const usar = () => {
    canvas.value.toBlob((blob) => {
        if (!blob) return;
        emit('done', new File([blob], 'firma-dibujada.png', { type: 'image/png' }));
    }, 'image/png');
};
</script>

<template>
    <div class="sdp">
        <canvas
            ref="canvas"
            class="sdp__canvas"
            :style="{ width: W + 'px', height: H + 'px' }"
            @mousedown="empezar"
            @mousemove="trazar"
            @mouseup="soltar"
            @mouseleave="soltar"
            @touchstart="empezar"
            @touchmove="trazar"
            @touchend="soltar"
        />
        <div class="sdp__hint">{{ $t('signatures.draw_hint') }}</div>
        <Space :size="8">
            <Button size="small" :disabled="!hasInk" @click="limpiar">
                <ClearOutlined /> {{ $t('signatures.draw_clear') }}
            </Button>
            <Button size="small" type="primary" :disabled="!hasInk" @click="usar">
                <CheckOutlined /> {{ $t('signatures.draw_use') }}
            </Button>
        </Space>
    </div>
</template>

<style scoped>
.sdp { display: flex; flex-direction: column; gap: 8px; }
.sdp__canvas {
    max-width: 100%;
    border: 1px dashed var(--color-border, #d9d9d9);
    border-radius: 8px;
    background:
        /* La línea de referencia sobre la que se firma, como en el papel. */
        linear-gradient(to top, transparent 34px, var(--color-border, #d9d9d9) 34px,
            var(--color-border, #d9d9d9) 35px, transparent 35px);
    cursor: crosshair;
    touch-action: none;
}
.sdp__hint { font-size: 0.75rem; color: var(--color-text-muted); }
</style>
