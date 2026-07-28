import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';
import Antd from 'ant-design-vue';
import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
import { autoAnimatePlugin } from '@formkit/auto-animate/vue';
import I18nPlugin from '@/Plugins/i18n';

// Register AG Grid Community modules once for the whole app
ModuleRegistry.registerModules([AllCommunityModule]);

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        import.meta.glob('./Pages/**/*.vue', { eager: false })[`./Pages/${name}.vue`](),
    setup({ el, App, props, plugin }) {
        // Colores del diagnóstico (editables por super/tenant): aplica el override
        // antes de montar y en cada navegación (la prop compartida se revalúa).
        // Fase 2: acá se cargan los colores editables de los veredictos del laboratorio.
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(Antd)
            .use(autoAnimatePlugin)
            .use(I18nPlugin)
            .mount(el);
    },
    progress: {
        color: '#0A6ED1',
        showSpinner: true,
        // Sin delay: la barra aparece al instante para saber que el click registró.
        delay: 0,
    },
});

// ─── Feedback de navegación ─────────────────────────────────────────────────
// Mientras dura un visit de Inertia: el <body> entra en estado "navegando"
// (cursor de carga) y el elemento que se clickeó (link/botón) se atenúa y se
// bloquea para que NO se pueda re-clickear. Esto da feedback inmediato y evita
// el doble-click. (Inertia ya cancela el visit anterior si haces otro, así que
// clickear varias veces no encola ni hace más lento.)
let navEl = null;
document.addEventListener('mousedown', (e) => {
    navEl = (e.target instanceof HTMLElement) ? e.target.closest('a, button') : null;
}, true);
router.on('start', () => {
    document.body.classList.add('is-navigating');
    if (navEl) navEl.classList.add('is-navigating');
});
const endNavigating = () => {
    document.body.classList.remove('is-navigating');
    if (navEl) { navEl.classList.remove('is-navigating'); navEl = null; }
};
router.on('finish', endNavigating);

// ─── Que el navegador deje de adivinar qué es cada campo ────────────────────
// Chrome no lee nuestras etiquetas como las lee una persona: las clasifica con
// su propio adivinador. En el formulario de Instrumento conviven "Código",
// "Número de serie" y "Vence el", y esas tres juntas tienen la forma de una
// tarjeta —número, código, vencimiento—, así que ofrece autocompletar con los
// MÉTODOS DE PAGO guardados. El sistema no tiene ni un campo de pago ahí; la
// lista es del navegador y los datos nunca salen de él.
//
// Se arregla declarando lo que el campo ES en vez de dejar que lo adivine.
// Como el problema lo tiene cualquier formulario con etiquetas parecidas
// (Equipo también lleva "Número de serie"), se hace UNA vez para toda la app
// en lugar de repetirlo en los veinticinco formularios.
//
// Lo que YA declara su autocomplete no se toca: el ingreso quiere que el
// navegador ofrezca el usuario y la contraseña guardados, y ahí sí corresponde.
const apagarAdivinanzaDelNavegador = () => {
    document.querySelectorAll('form:not([autocomplete])')
        .forEach((f) => f.setAttribute('autocomplete', 'off'));
    document.querySelectorAll('input:not([autocomplete]), textarea:not([autocomplete])')
        .forEach((i) => i.setAttribute('autocomplete', 'off'));
};

// Se corre en cada navegación y también cuando aparece contenido nuevo sin
// navegar: los formularios que viven dentro de un modal o un panel lateral se
// montan después, y sin el observador quedarían afuera.
// Con retardo y no por cuadro: la grilla de la bancada re-renderiza mientras
// el analista escribe, y recorrer el documento en cada tecla se nota.
let pendiente = null;
const programar = () => {
    if (pendiente) return;
    pendiente = setTimeout(() => { pendiente = null; apagarAdivinanzaDelNavegador(); }, 250);
};

router.on('finish', programar);
document.addEventListener('DOMContentLoaded', programar);
new MutationObserver(programar).observe(document.documentElement, { childList: true, subtree: true });

// Bloqueo global del menú contextual (click derecho).
// El usuario pidió desactivar el right-click en toda la app. Los inputs
// editables (Input/Textarea) NO se bloquean: el usuario debe poder usar
// "pegar/cortar/seleccionar todo" del menú nativo dentro de campos de
// texto. Para todo lo demás (botones, links, action buttons, tablas)
// se previene el contextmenu.
window.addEventListener('contextmenu', (e) => {
    const t = e.target;
    if (!t || !(t instanceof HTMLElement)) return;
    const editable = t.closest('input, textarea, [contenteditable="true"], [contenteditable=""]');
    if (editable) return;
    e.preventDefault();
});
