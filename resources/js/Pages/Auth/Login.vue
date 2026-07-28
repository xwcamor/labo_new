<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { Input, Checkbox, Button, Alert, Select, SelectOption } from 'ant-design-vue';
import {
    MailOutlined, LockOutlined, EyeOutlined, EyeInvisibleOutlined,
    SafetyOutlined, GoogleOutlined,
    ExperimentOutlined, LineChartOutlined, FunctionOutlined, SafetyCertificateOutlined,
} from '@ant-design/icons-vue';

import AuthLayout from '@/Layouts/AuthLayout.vue';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

defineOptions({ layout: AuthLayout });

const props = defineProps({
    appName: { type: String, default: '' },
    locale:  { type: String, default: 'es' },
    locales: { type: Object, default: () => ({}) },
});

// Shared props del middleware:
//   appName / appLogoUrl   → branding global (setting `app.name`, `app.logo_url`)
//   googleLoginEnabled     → feature flag para el boton Google
const page = usePage();
const googleLoginEnabled = computed(() => !!page.props.googleLoginEnabled);
const effectiveAppName   = computed(() => props.appName || page.props.appName || 'TR Health');
const effectiveAppLogo   = computed(() => page.props.appLogoUrl || null);

// ─── Form ──────────────────────────────────────────────────────────────────
const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const togglePassword = () => { showPassword.value = !showPassword.value; };

const submit = () => {
    form.post(route('login.post'), {
        onFinish: () => form.reset('password'),
    });
};

// Sin parallax: el hero es la bancada del laboratorio, animada en CSS/SVG.

// ─── La lectura de la bureta ──────────────────────────────────────────────
// Es el único número del hero y no es decorativo: es el volumen gastado de una
// titulación de Número Ácido. Cuenta hasta 1.15 mL, que con el factor del KOH y
// la masa de aceite del ensayo da 0.309 mgKOH/g — la misma cuenta que hace el
// servidor cuando el analista carga la hoja.
const BURETA_ML = 1.15;
const buretaMl = ref(0);

onMounted(() => {
    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) { buretaMl.value = BURETA_ML; return; }

    const t0 = performance.now(), dur = 2200;
    const step = (now) => {
        const p = Math.min(1, Math.max(0, (now - t0) / dur));
        buretaMl.value = BURETA_ML * (1 - Math.pow(1 - p, 3)); // ease-out cúbico
        if (p < 1) requestAnimationFrame(step);
        else buretaMl.value = BURETA_ML;
    };
    requestAnimationFrame(step);
});

// ─── Locale switch ─────────────────────────────────────────────────────────
const onLocaleChange = (newLocale) => {
    if (props.locales?.[newLocale]) {
        window.location.href = props.locales[newLocale];
    }
};

// ─── Disclosure with embedded links ────────────────────────────────────────
// Construimos el HTML reemplazando :terms y :privacy en la traducción por
// anchors al estilo Laravel. Vue lo renderiza con v-html para que los links
// no se escapen.
const disclosureHtml = computed(() => {
    const tpl = t('auth.disclosure');
    // Las rutas legal_management.terms y .privacy son blade-rendered (no
    // Inertia) — usamos los URLs directos para que abran en pestaña nueva.
    const termsUrl   = route('legal_management.terms');
    const privacyUrl = route('legal_management.privacy');
    return tpl
        .replace(':terms',   `<a href="${termsUrl}" target="_blank" rel="noopener" class="underline">${t('auth.terms_short')}</a>`)
        .replace(':privacy', `<a href="${privacyUrl}" target="_blank" rel="noopener" class="underline">${t('auth.privacy_short')}</a>`);
});
</script>

<template>
    <Head :title="$t('auth.login')" />

    <div class="login-grid">
        <!-- LEFT: brand panel (desktop only) -->
        <aside class="login-brand">
            <div class="login-brand__bg" />
            <div class="login-brand__inner">
                <div class="login-brand__logo">
                    <img v-if="effectiveAppLogo" :src="effectiveAppLogo" :alt="effectiveAppName" class="login-brand__logo-img" />
                    <SafetyOutlined v-else />
                </div>
                <h2 class="login-brand__title">{{ effectiveAppName }}</h2>
                <p class="login-brand__tagline">{{ $t('auth.tagline') }}</p>

                <!-- Hero: la bancada del laboratorio. Tres cosas, que son las
                     tres que hace el sistema: la titulacion (Numero Acido), el
                     cromatograma (Analisis Cromatografico) y la carta de
                     control que vigila que el metodo este midiendo bien. -->
                <div class="login-brand__hero">
                    <svg viewBox="0 0 280 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <defs>
                            <linearGradient id="lab-glass" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="rgba(255,255,255,0.17)" />
                                <stop offset="100%" stop-color="rgba(255,255,255,0.05)" />
                            </linearGradient>
                            <linearGradient id="lab-oil" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="rgba(240,162,60,0.55)" />
                                <stop offset="100%" stop-color="rgba(226,120,30,0.65)" />
                            </linearGradient>
                            <linearGradient id="lab-trace" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0%" stop-color="#7BD389" />
                                <stop offset="100%" stop-color="#4DB6E8" />
                            </linearGradient>
                            <filter id="lab-glow" x="-60%" y="-60%" width="220%" height="220%">
                                <feGaussianBlur stdDeviation="2.2" result="b" />
                                <feMerge><feMergeNode in="b" /><feMergeNode in="SourceGraphic" /></feMerge>
                            </filter>
                        </defs>

                        <!-- ── Titulacion: bureta, gota y matraz ───────────────── -->
                        <!-- Soporte universal -->
                        <g fill="rgba(255,255,255,0.16)">
                            <rect x="18" y="14" width="4" height="170" rx="2" />
                            <rect x="8" y="182" width="42" height="5" rx="2.5" />
                            <rect x="22" y="34" width="26" height="4" rx="2" />
                        </g>

                        <!-- Bureta -->
                        <rect x="44" y="16" width="11" height="98" rx="4"
                              fill="url(#lab-glass)" stroke="rgba(255,255,255,0.22)" stroke-width="1.1" />
                        <rect x="46" y="38" width="7" height="74" rx="2" fill="rgba(159,225,255,0.35)" />
                        <g stroke="rgba(255,255,255,0.30)" stroke-width="1" stroke-linecap="round">
                            <line x1="44" y1="28" x2="50" y2="28" /><line x1="44" y1="40" x2="50" y2="40" />
                            <line x1="44" y1="52" x2="50" y2="52" /><line x1="44" y1="64" x2="50" y2="64" />
                            <line x1="44" y1="76" x2="50" y2="76" /><line x1="44" y1="88" x2="50" y2="88" />
                            <line x1="44" y1="100" x2="50" y2="100" />
                        </g>
                        <!-- Llave de paso -->
                        <rect x="45" y="114" width="9" height="7" rx="2" fill="rgba(255,255,255,0.24)" />
                        <line x1="54" y1="117" x2="61" y2="117" stroke="rgba(255,255,255,0.30)" stroke-width="2.5" stroke-linecap="round" />
                        <path d="M49.5,121 L49.5,127" stroke="rgba(255,255,255,0.22)" stroke-width="2" stroke-linecap="round" />

                        <!-- La gota que cae -->
                        <circle class="drop" cx="49.5" cy="130" r="2.6" fill="#9fe1ff" filter="url(#lab-glow)" />

                        <!-- Matraz Erlenmeyer -->
                        <rect x="45" y="140" width="9" height="9" rx="2" fill="url(#lab-glass)" stroke="rgba(255,255,255,0.22)" />
                        <path d="M45,149 L54,149 L68,180 Q68,184 64,184 L35,184 Q31,184 31,180 Z"
                              fill="url(#lab-glass)" stroke="rgba(255,255,255,0.22)" stroke-width="1.1" stroke-linejoin="round" />
                        <path d="M40,168 L59,168 L66,181 Q66,183.5 63,183.5 L36,183.5 Q33,183.5 33,181 Z"
                              fill="url(#lab-oil)" />
                        <!-- Lectura de la bureta: el volumen gastado -->
                        <text x="49" y="197" text-anchor="middle" font-size="9" font-weight="600"
                              fill="rgba(255,255,255,0.78)"
                              font-family="-apple-system,Segoe UI,Roboto,sans-serif">{{ buretaMl.toFixed(2) }} mL</text>

                        <!-- ── Carta de control (arriba a la derecha) ──────────── -->
                        <rect x="88" y="16" width="184" height="74" rx="8"
                              fill="rgba(255,255,255,0.05)" stroke="rgba(255,255,255,0.14)" />
                        <!-- Limites de control (3s) y de alerta (2s) -->
                        <g stroke-dasharray="3 3" stroke-width="1">
                            <line x1="96" y1="28" x2="264" y2="28" stroke="rgba(242,85,74,0.55)" />
                            <line x1="96" y1="78" x2="264" y2="78" stroke="rgba(242,85,74,0.55)" />
                            <line x1="96" y1="38" x2="264" y2="38" stroke="rgba(240,162,60,0.55)" />
                            <line x1="96" y1="68" x2="264" y2="68" stroke="rgba(240,162,60,0.55)" />
                        </g>
                        <!-- Linea central -->
                        <line x1="96" y1="53" x2="264" y2="53" stroke="rgba(255,255,255,0.38)" stroke-width="1.2" />
                        <!-- La serie del patron -->
                        <polyline class="qcline" points="104,57 128,49 152,55 176,34 200,56 224,50 248,58"
                                  fill="none" stroke="rgba(159,225,255,0.55)" stroke-width="1.4" stroke-linejoin="round" />
                        <g filter="url(#lab-glow)">
                            <circle class="qcp" cx="104" cy="57" r="3" fill="#7BD389" />
                            <circle class="qcp qcp-2" cx="128" cy="49" r="3" fill="#7BD389" />
                            <circle class="qcp qcp-3" cx="152" cy="55" r="3" fill="#7BD389" />
                            <!-- El que se pasa de la linea de alerta: ambar, no verde -->
                            <circle class="qcp qcp-4" cx="176" cy="34" r="3.4" fill="#F0A23C" />
                            <circle class="qcp qcp-5" cx="200" cy="56" r="3" fill="#7BD389" />
                            <circle class="qcp qcp-6" cx="224" cy="50" r="3" fill="#7BD389" />
                            <circle class="qcp qcp-7" cx="248" cy="58" r="3" fill="#7BD389" />
                        </g>

                        <!-- ── Cromatograma (abajo a la derecha) ───────────────── -->
                        <rect x="88" y="104" width="184" height="72" rx="8"
                              fill="rgba(255,255,255,0.05)" stroke="rgba(255,255,255,0.14)" />
                        <line x1="96" y1="166" x2="264" y2="166" stroke="rgba(255,255,255,0.28)" stroke-width="1" />
                        <g stroke="rgba(255,255,255,0.18)" stroke-width="1">
                            <line x1="118" y1="166" x2="118" y2="169" /><line x1="148" y1="166" x2="148" y2="169" />
                            <line x1="178" y1="166" x2="178" y2="169" /><line x1="208" y1="166" x2="208" y2="169" />
                            <line x1="238" y1="166" x2="238" y2="169" />
                        </g>
                        <!-- Los picos: H2, CH4, CO, C2H4, C2H6, C2H2 -->
                        <path class="chroma"
                              d="M96,166 L110,166 L114,146 L118,166 L134,166 L139,124 L144,166 L162,166
                                 L166,152 L170,166 L186,166 L191,132 L196,166 L212,166 L216,150 L220,166
                                 L234,166 L239,140 L244,166 L264,166"
                              fill="none" stroke="url(#lab-trace)" stroke-width="1.8"
                              stroke-linejoin="round" stroke-linecap="round" filter="url(#lab-glow)" />
                        <g font-size="6" fill="rgba(255,255,255,0.45)"
                           font-family="-apple-system,Segoe UI,Roboto,sans-serif" text-anchor="middle">
                            <text x="139" y="119">CH4</text>
                            <text x="191" y="127">C2H4</text>
                        </g>

                        <!-- ── Viales de muestra ───────────────────────────────── -->
                        <g>
                            <rect x="96" y="182" width="11" height="6" rx="2" fill="rgba(255,255,255,0.22)" />
                            <rect x="96" y="186" width="11" height="14" rx="2" fill="url(#lab-glass)" stroke="rgba(255,255,255,0.18)" />
                            <rect x="97.5" y="192" width="8" height="8" rx="1.5" fill="url(#lab-oil)" />

                            <rect x="114" y="182" width="11" height="6" rx="2" fill="rgba(255,255,255,0.22)" />
                            <rect x="114" y="186" width="11" height="14" rx="2" fill="url(#lab-glass)" stroke="rgba(255,255,255,0.18)" />
                            <rect x="115.5" y="190" width="8" height="10" rx="1.5" fill="url(#lab-oil)" />

                            <rect x="132" y="182" width="11" height="6" rx="2" fill="rgba(255,255,255,0.22)" />
                            <rect x="132" y="186" width="11" height="14" rx="2" fill="url(#lab-glass)" stroke="rgba(255,255,255,0.18)" />
                            <rect x="133.5" y="194" width="8" height="6" rx="1.5" fill="url(#lab-oil)" />
                        </g>
                    </svg>
                </div>

                <ul class="login-brand__features">
                    <li><ExperimentOutlined /><span>{{ $t('auth.feature_tests') }}</span></li>
                    <li><LineChartOutlined /><span>{{ $t('auth.feature_health') }}</span></li>
                    <li><FunctionOutlined /><span>{{ $t('auth.feature_methods') }}</span></li>
                    <li><SafetyCertificateOutlined /><span>{{ $t('auth.feature_reports') }}</span></li>
                </ul>
            </div>
        </aside>

        <!-- RIGHT: form panel (full screen on mobile) -->
        <main class="login-main">
            <!-- Mobile header (hidden on desktop) -->
            <header class="login-mobile-header">
                <div class="login-mobile-header__logo">
                    <img v-if="effectiveAppLogo" :src="effectiveAppLogo" :alt="effectiveAppName" class="login-mobile-header__logo-img" />
                    <SafetyOutlined v-else />
                </div>
                <h2>{{ effectiveAppName }}</h2>
                <p>{{ $t('auth.tagline') }}</p>
            </header>

            <div class="login-form-wrap">
                <div class="login-form">
                    <div class="login-form__header">
                        <h1>{{ $t('auth.login') }}</h1>
                        <p>{{ $t('auth.signin_subtitle') }}</p>
                    </div>

                    <Alert
                        v-if="form.errors.email && !form.errors.password"
                        type="error"
                        :message="form.errors.email"
                        show-icon
                        class="mb-3"
                    />

                    <form @submit.prevent="submit" autocomplete="off">
                        <!-- Email -->
                        <label for="auth-email" class="field-label">{{ $t('auth.email') }}</label>
                        <Input
                            id="auth-email"
                            v-model:value="form.email"
                            size="large"
                            :placeholder="$t('auth.email_placeholder')"
                            type="email"
                            autocomplete="username"
                            :status="form.errors.email ? 'error' : ''"
                        >
                            <template #prefix><MailOutlined /></template>
                        </Input>
                        <div v-if="form.errors.email" class="field-error">{{ form.errors.email }}</div>

                        <!-- Password -->
                        <label for="auth-password" class="field-label" style="margin-top: 14px">{{ $t('auth.password') }}</label>
                        <Input
                            id="auth-password"
                            v-model:value="form.password"
                            size="large"
                            placeholder="••••••••"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            :status="form.errors.password ? 'error' : ''"
                        >
                            <template #prefix><LockOutlined /></template>
                            <template #suffix>
                                <button
                                    type="button"
                                    class="pass-toggle"
                                    @click="togglePassword"
                                    :aria-label="showPassword ? $t('auth.hide_password') : $t('auth.show_password')"
                                >
                                    <EyeOutlined v-if="!showPassword" />
                                    <EyeInvisibleOutlined v-else />
                                </button>
                            </template>
                        </Input>
                        <div v-if="form.errors.password" class="field-error">{{ form.errors.password }}</div>

                        <!-- Remember + Forgot -->
                        <div class="row-between" style="margin-top: 14px">
                            <Checkbox v-model:checked="form.remember">{{ $t('auth.rememberme') }}</Checkbox>
                            <Link :href="route('password.request')" class="link-sm">
                                {{ $t('auth.forgot_password') }}
                            </Link>
                        </div>

                        <!-- Submit -->
                        <Button
                            type="primary"
                            html-type="submit"
                            size="large"
                            block
                            :loading="form.processing"
                            class="submit-btn"
                        >
                            {{ $t('auth.login') }}
                        </Button>
                    </form>

                    <!-- Divider + Google login. Gateado por el setting
                         `features.google_login_enabled` (shared prop). Si
                         esta off no se muestra ni el divider. -->
                    <template v-if="googleLoginEnabled">
                        <div class="divider"><span>{{ $t('auth.or_continue_with') }}</span></div>
                        <a :href="route('auth_management.google.redirect')" class="google-btn">
                            <GoogleOutlined /> <span>{{ $t('auth.continue_with_google') }}</span>
                        </a>
                    </template>

                    <!-- Locale -->
                    <div class="locale-row">
                        <Select
                            :value="locale"
                            size="small"
                            style="min-width: 120px"
                            @change="onLocaleChange"
                        >
                            <SelectOption v-for="(url, code) in locales" :key="code" :value="code">
                                {{ code === 'es' ? 'Español' : code === 'en' ? 'English' : code }}
                            </SelectOption>
                        </Select>
                    </div>

                    <!-- Disclosure -->
                    <p class="disclosure">
                        <span v-html="disclosureHtml" />
                    </p>
                </div>

                <footer class="login-footer">
                    <p>© {{ new Date().getFullYear() }} {{ effectiveAppName }} · {{ $t('auth.all_rights_reserved') }}</p>
                    <p class="login-footer__links">
                        <a :href="route('report.verify')" target="_blank" rel="noopener" class="link-sm">{{ $t('auth.verify_report') }}</a>
                        <span class="login-footer__sep">·</span>
                        <a :href="route('manual')" target="_blank" rel="noopener" class="link-sm">{{ $t('global.user_manual') }}</a>
                    </p>
                </footer>
            </div>
        </main>
    </div>
</template>

<style scoped>
/* ─── Layout grid ──────────────────────────────────────────────────────── */
.login-grid {
    display: grid;
    grid-template-columns: 1fr;
    min-height: 100vh;
    min-height: 100dvh;
    background: #fff;
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
}
@media (min-width: 768px) {
    .login-grid { grid-template-columns: 1fr 1fr; }
}

/* ─── LEFT: brand panel (desktop only) ─────────────────────────────────── */
.login-brand {
    display: none;
    position: relative;
    overflow: hidden;
    color: #fff;
    background: linear-gradient(160deg, #354A5F 0%, #2C3E51 100%);
    padding: clamp(2.5rem, 5vw, 5rem) clamp(2rem, 4vw, 4rem);
}
@media (min-width: 768px) {
    .login-brand { display: flex; align-items: center; }
}
.login-brand__bg::before,
.login-brand__bg::after {
    content: "";
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.04);
    pointer-events: none;
}
.login-brand__bg::before { width: 320px; height: 320px; top: -100px; right: -100px; }
.login-brand__bg::after  { width: 220px; height: 220px; bottom: -80px; left: -80px; }

.login-brand__inner {
    position: relative;
    z-index: 1;
    max-width: 480px;
    margin: 0 auto;
    text-align: center;
    width: 100%;
}
.login-brand__logo {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    margin-bottom: 1.25rem;
    color: #cbd5e1;
    overflow: hidden;
}
.login-brand__logo-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 6px;
}
.login-brand__title {
    font-weight: 700;
    font-size: 1.6rem;
    margin: 0 0 0.25rem 0;
    letter-spacing: -0.01em;
}
.login-brand__tagline {
    font-size: 0.9rem;
    opacity: 0.85;
    margin-bottom: 1.5rem;
}
.login-brand__hero {
    width: 100%;
    max-width: 320px;
    margin: 0 auto 0.5rem;
}
.login-brand__hero svg {
    display: block;
    width: 100%;
    height: auto;
    filter: drop-shadow(0 12px 30px rgba(0, 0, 0, 0.35));
}

/* ── Efectos eléctricos del hero (transformador animado) ── */
/* La gota cae de la bureta al matraz y desaparece al tocar el liquido. */
@keyframes lab-drop {
    0%       { transform: translateY(0);    opacity: 0; }
    8%       { opacity: .95; }
    70%      { transform: translateY(34px); opacity: .95; }
    78%,100% { transform: translateY(36px); opacity: 0; }
}
/* El cromatograma se dibuja de izquierda a derecha, como sale del equipo. */
@keyframes lab-trace { from { stroke-dashoffset: 620; } to { stroke-dashoffset: 0; } }
/* Cada punto del patron aparece cuando le toca su corrida. */
@keyframes lab-point { from { opacity: 0; transform: scale(.2); } to { opacity: 1; transform: scale(1); } }
@keyframes lab-line  { from { stroke-dashoffset: 520; } to { stroke-dashoffset: 0; } }
/* El punto que se paso de la linea de alerta late: es el que hay que mirar. */
@keyframes lab-alert { 0%,100% { opacity: .75; } 50% { opacity: 1; } }

.login-brand__hero :deep(.drop)   { animation: lab-drop 2.4s cubic-bezier(.55,0,.85,.4) infinite; }
.login-brand__hero :deep(.chroma) { stroke-dasharray: 620; animation: lab-trace 2.8s ease-out .3s both; }
.login-brand__hero :deep(.qcline) { stroke-dasharray: 520; animation: lab-line 2.2s ease-out .4s both; }
.login-brand__hero :deep(.qcp)    { transform-box: fill-box; transform-origin: center; animation: lab-point .5s ease-out .5s both; }
.login-brand__hero :deep(.qcp-2)  { animation-delay: .72s; }
.login-brand__hero :deep(.qcp-3)  { animation-delay: .94s; }
.login-brand__hero :deep(.qcp-4)  { animation: lab-point .5s ease-out 1.16s both, lab-alert 1.8s ease-in-out 1.9s infinite; }
.login-brand__hero :deep(.qcp-5)  { animation-delay: 1.38s; }
.login-brand__hero :deep(.qcp-6)  { animation-delay: 1.60s; }
.login-brand__hero :deep(.qcp-7)  { animation-delay: 1.82s; }
@media (prefers-reduced-motion: reduce) {
    .login-brand__hero :deep(*) { animation: none !important; }
}
.login-brand__features {
    list-style: none;
    padding: 0;
    margin: 1.75rem 0 0 0;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    text-align: left;
}
.login-brand__features li {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    font-size: 0.9rem;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.92);
}
.login-brand__features li :deep(.anticon) {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.15);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    margin-top: 2px;
}

/* ─── RIGHT: form panel ────────────────────────────────────────────────── */
.login-main {
    display: flex;
    flex-direction: column;
    background: #fff;
    min-height: 100vh;
    min-height: 100dvh;
}
@media (min-width: 768px) {
    .login-main {
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }
}

/* Mobile-only header (app-like sheet style) */
.login-mobile-header {
    background: linear-gradient(160deg, #354A5F 0%, #2C3E51 100%);
    color: #fff;
    text-align: center;
    padding: calc(env(safe-area-inset-top, 0px) + 2.25rem) 1.5rem 2.5rem;
    border-bottom-left-radius: 28px;
    border-bottom-right-radius: 28px;
    margin-bottom: -1.25rem;
    position: relative;
    overflow: hidden;
}
.login-mobile-header::before,
.login-mobile-header::after {
    content: "";
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
}
.login-mobile-header::before { width: 180px; height: 180px; top: -60px; right: -60px; }
.login-mobile-header::after  { width: 120px; height: 120px; bottom: -40px; left: -30px; }
.login-mobile-header__logo {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 0.85rem;
    color: #cbd5e1;
    position: relative;
    z-index: 1;
    overflow: hidden;
}
.login-mobile-header__logo-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 4px;
}
.login-mobile-header h2 {
    font-weight: 700;
    font-size: 1.35rem;
    margin: 0 0 0.15rem 0;
    color: #fff;
    letter-spacing: -0.01em;
    position: relative;
    z-index: 1;
}
.login-mobile-header p {
    font-size: 0.8rem;
    opacity: 0.85;
    margin: 0;
    color: #fff;
    position: relative;
    z-index: 1;
}
@media (min-width: 768px) {
    .login-mobile-header { display: none; }
}

/* Form wrap — sheet on mobile, naturally centered on desktop (login-main centers it) */
.login-form-wrap {
    width: 100%;
    max-width: 460px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
}
@media (max-width: 767.98px) {
    .login-form-wrap {
        flex: 1;
        background: #fff;
        border-top-left-radius: 24px;
        border-top-right-radius: 24px;
        padding: 2rem 1.5rem 1.25rem;  /* más respiro lateral en mobile (1.5rem en lugar de 1.25rem) */
        position: relative;
        z-index: 2;
        box-shadow: 0 -8px 24px rgba(2, 32, 71, 0.06);
    }
}

.login-form {
    padding: 1.75rem 0.5rem 1rem;
}

@media (min-width: 768px) {
    .login-form { padding: 0.5rem 0.5rem; }
}

.login-form__header { margin-bottom: 1.75rem; }
.login-form__header h1 {
    font-weight: 700;
    font-size: 1.75rem;
    color: #1f2937;
    margin: 0 0 0.4rem 0;
    letter-spacing: -0.02em;
}
.login-form__header p {
    color: #6b7280;
    font-size: 0.95rem;
    margin: 0;
}

.field-label {
    display: block;
    font-size: 0.78rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 7px;
    letter-spacing: 0.01em;
}

.field-error {
    color: #dc2626;
    font-size: 0.8rem;
    font-weight: 500;
    margin: 6px 0 0 0;
}

/* Inputs — definidos, no fantasmagóricos.
   Bordes claros + fondo blanco + focus ring fuerte. */
.login-form :deep(.ant-input-affix-wrapper),
.login-form :deep(.ant-input) {
    height: 50px;
    border-radius: 10px;
    background: #fff;
    border: 1.5px solid #d4d8dd;
    font-size: 0.95rem;
    color: #1f2937;
    padding: 0 14px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.login-form :deep(.ant-input-affix-wrapper) {
    padding: 0 14px;
}
.login-form :deep(.ant-input-affix-wrapper input.ant-input) {
    background: transparent;
    border: 0;
    box-shadow: none !important;
    height: 100%;
    padding: 0;
}
.login-form :deep(.ant-input-affix-wrapper:hover),
.login-form :deep(.ant-input:hover) {
    border-color: #94a3b8;
}
.login-form :deep(.ant-input-affix-wrapper-focused),
.login-form :deep(.ant-input-affix-wrapper:focus-within),
.login-form :deep(.ant-input:focus) {
    border-color: #0A6ED1 !important;
    box-shadow: 0 0 0 3px rgba(10, 110, 209, 0.18) !important;
}
.login-form :deep(.ant-input-affix-wrapper-status-error) {
    border-color: #ef4444 !important;
}
.login-form :deep(.ant-input-affix-wrapper-status-error:focus-within) {
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
}
.login-form :deep(.ant-input-prefix) {
    color: #64748b;
    margin-right: 10px;
    font-size: 1.05rem;
}
.login-form :deep(.ant-input::placeholder) {
    color: #94a3b8;
}

.row-between {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.link-sm {
    font-size: 0.875rem;
    color: #0A6ED1;
    font-weight: 500;
    text-decoration: none;
}
.link-sm:hover { color: #085CAF; text-decoration: underline; }

.submit-btn {
    margin-top: 20px;
    height: 52px !important;
    font-weight: 600 !important;
    font-size: 1rem !important;
    border-radius: 10px !important;
    background: linear-gradient(135deg, #0A6ED1 0%, #064C92 100%) !important;
    border: 0 !important;
    box-shadow: 0 6px 18px rgba(10, 110, 209, 0.28) !important;
    letter-spacing: 0.01em;
    transition: transform 0.12s ease, box-shadow 0.15s ease !important;
}
.submit-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 22px rgba(10, 110, 209, 0.35) !important;
}
.submit-btn:active { transform: translateY(0) !important; }

.pass-toggle {
    background: transparent;
    border: 0;
    cursor: pointer;
    padding: 6px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    border-radius: 6px;
    transition: background 0.12s ease, color 0.12s ease;
}
.pass-toggle:hover { background: #f1f5f9; color: #0A6ED1; }

/* Divider */
.divider {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    color: #6A6D70;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 500;
    margin: 1.75rem 0 1.25rem;
}
.divider::before, .divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background: #e5e7eb;
}

/* Google button — matches original Blade: white bg, soft border, hover lift */
.google-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    height: 50px;
    border-radius: 10px;
    background: #fff;
    color: #1f2937;
    border: 1px solid #e5e7eb;
    font-weight: 500;
    text-decoration: none;
    transition: background 0.15s ease, border-color 0.15s ease, transform 0.12s ease;
    width: 100%;
}
.google-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    transform: translateY(-1px);
}
.google-btn :deep(.anticon) { color: #ea4335; font-size: 1.15rem; }

/* Locale — chip pill style, like the original */
.locale-row {
    display: flex;
    justify-content: center;
    margin-top: 1.25rem;
}
.locale-row :deep(.ant-select) {
    min-width: 130px;
}
.locale-row :deep(.ant-select-selector) {
    background: #f1f5f9 !important;
    border: 0 !important;
    border-radius: 999px !important;
    padding: 4px 14px !important;
    height: 32px !important;
    font-size: 0.8rem !important;
    color: #475569 !important;
}

/* Disclosure */
.disclosure {
    font-size: 0.72rem;
    color: #94a3b8;
    margin-top: 1rem;
    line-height: 1.5;
    text-align: center;
}
.disclosure a { color: #0A6ED1; text-decoration: none; font-weight: 500; }
.disclosure a:hover { text-decoration: underline; }

/* Footer */
.login-footer {
    text-align: center;
    padding: 1rem 1rem calc(env(safe-area-inset-bottom, 0px) + 1.25rem);
    color: #9ca3af;
    font-size: 0.7rem;
}
.login-footer p { margin: 0; font-weight: 500; }
.login-footer__links { margin-top: 4px !important; display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap; }
.login-footer__sep { opacity: 0.5; }

/* ─── Mobile-specific tweaks — app-like polish ─────────────────────────── */
@media (max-width: 767.98px) {
    .login-form__header { margin-bottom: 1.5rem; }
    .login-form__header h1 { font-size: 1.5rem; letter-spacing: -0.01em; }
    .login-form__header p  { font-size: 0.875rem; }

    /* Inputs: thinner border + slightly bigger touch target */
    .login-form :deep(.ant-input-affix-wrapper),
    .login-form :deep(.ant-input) {
        height: 54px;
        font-size: 1rem;
        border-width: 1px;  /* más sutil en pantalla chica */
    }

    /* Buttons: matching heights for visual consistency */
    .submit-btn { height: 56px !important; font-size: 1rem !important; margin-top: 24px; }
    .google-btn { height: 56px; font-size: 1rem; }

    /* Vertical rhythm — gaps consistentes (8/16/24/32px scale) */
    .row-between { margin-top: 16px !important; }
    .divider     { margin: 24px 0 16px !important; }
    .locale-row  { margin-top: 24px !important; }
    .disclosure  { margin-top: 12px; font-size: 0.75rem; line-height: 1.55; }
}

.mb-3 { margin-bottom: 12px; }
</style>

<!-- Dark mode overrides (NOT scoped) -->
<style>
html[data-theme="dark"] .login-grid { background: #1a1f24; }
html[data-theme="dark"] .login-main { background: #1a1f24; }

/* Mobile sheet — needs darker bg + adjusted shadow */
@media (max-width: 767.98px) {
    html[data-theme="dark"] .login-form-wrap {
        background: #1a1f24 !important;
        box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.4) !important;
    }
}

html[data-theme="dark"] .login-form__header h1 { color: #e5e6e7; }
html[data-theme="dark"] .login-form__header p  { color: #a8aaae; }
html[data-theme="dark"] .field-label           { color: #cbd5e1; }

html[data-theme="dark"] .login-form .ant-input-affix-wrapper,
html[data-theme="dark"] .login-form .ant-input {
    background: #2c3034 !important;
    border-color: #3f4448 !important;
    color: #e5e6e7 !important;
}
html[data-theme="dark"] .login-form .ant-input-affix-wrapper:hover,
html[data-theme="dark"] .login-form .ant-input:hover {
    border-color: #4db6e8 !important;
}
html[data-theme="dark"] .login-form .ant-input-affix-wrapper-focused,
html[data-theme="dark"] .login-form .ant-input-affix-wrapper:focus-within {
    border-color: #4db6e8 !important;
    box-shadow: 0 0 0 3px rgba(77, 182, 232, 0.18) !important;
}
html[data-theme="dark"] .login-form .ant-input-prefix       { color: #7c8390; }
html[data-theme="dark"] .login-form .ant-input::placeholder { color: #6b7785; }

html[data-theme="dark"] .pass-toggle:hover { background: #313a44; color: #4db6e8; }

html[data-theme="dark"] .divider          { color: #6b7785; }
html[data-theme="dark"] .divider::before,
html[data-theme="dark"] .divider::after   { background: #3f4448; }

html[data-theme="dark"] .google-btn {
    background: #2c3034;
    color: #e5e6e7;
    border-color: #3f4448;
}
html[data-theme="dark"] .google-btn:hover {
    background: #313a44;
    border-color: #4db6e8;
}

html[data-theme="dark"] .locale-row .ant-select-selector {
    background: #313a44 !important;
    color: #cbd5e1 !important;
}

html[data-theme="dark"] .disclosure   { color: #7c8390; }
html[data-theme="dark"] .login-footer { color: #6b7785; }
</style>
