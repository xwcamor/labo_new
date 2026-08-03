<?php

namespace App\Http\Controllers\DashboardManagement;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Reception;
use App\Models\Sample;
use App\Models\SampleReport;
use App\Models\SampleTest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\EquipmentType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DashboardController — landing post-login con widgets reales.
 *
 * Los widgets que devuelve dependen del rol:
 *   - super: vista del sistema completo (tenants, suscripciones, automatizaciones globales).
 *   - admin del tenant: vista del workspace (sus users, su sub, sus automatizaciones).
 *   - user: vista personal (tareas, automatizaciones que le notificaron).
 *
 * Cada widget es un objeto plano para que el frontend lo renderice sin
 * lógica: { label, value, hint, color, icon, href? }.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isSuper = $user?->hasRole('super') ?? false;

        // Dashboard de flota para el tenant (admin/operativo). Sin "actividad
        // reciente": para eso está el módulo de Logs del sistema.
        if (!$isSuper) {
            return inertia('Dashboard/Index', array_merge([
                'isSuper'           => false,
                'widgets'           => $this->tenantWidgets($user),
                'recentAutomations' => [],
                'expiringSoon'      => [],
            ], $this->labDashboard($user, $request)));
        }

        // Super: flota CROSS-TENANT (el scope hace bypass) + desglose por
        // workspace + sus widgets de sistema (suscripciones, automatizaciones).
        return inertia('Dashboard/Index', array_merge([
            'isSuper'           => true,
            'widgets'           => $this->superAdminWidgets(),
            'recentAutomations' => $this->recentAutomations($user),
            'expiringSoon'      => $this->expiringSubscriptions($user),
        ], $this->labDashboard($user, $request)));
    }

    /**
     * Últimas 10 acciones del propio user (su audit_log). Para la vista
     * simple del dashboard non-super — "lo que hiciste recientemente".
     *
     * Shape minimal: event, módulo, fecha. No exponemos old/new values aquí
     * (eso vive en el Show de cada registro para el detalle completo).
     */
    protected function recentUserActivity(?User $user): array
    {
        if (!$user) return [];

        return AuditLog::query()
            ->where('user_id', $user->id)
            ->whereIn('event', ['created', 'updated', 'deleted', 'restored', 'exported', 'force_deleted'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'event', 'module', 'auditable_type', 'auditable_id', 'created_at'])
            ->map(fn ($log) => [
                'id'             => $log->id,
                'event'          => $log->event,
                'module'         => $log->module,
                'auditable_id'   => $log->auditable_id,
                'auditable_type' => class_basename($log->auditable_type ?? ''),
                'created_at'     => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** Widgets de super: estado del sistema completo. */
    protected function superAdminWidgets(): array
    {
        $activeTenants  = Tenant::where('is_active', true)->count();
        $totalTenants   = Tenant::count();
        $activeSubs     = Subscription::whereIn('status', ['trial', 'active'])
            ->where('ends_at', '>', now())
            ->count();
        $expiringIn7    = Subscription::whereIn('status', ['trial', 'active'])
            ->whereBetween('ends_at', [now(), now()->addDays(7)])
            ->count();
        $autoLast24h    = AutomationRun::where('started_at', '>=', now()->subDay())->count();
        $autoFailed24h  = AutomationRun::where('started_at', '>=', now()->subDay())
            ->where('status', 'failed')->count();

        return [
            ['key' => 'tenants_active',   'label' => 'tenants_active',   'value' => $activeTenants, 'hint' => "{$totalTenants} totales", 'color' => 'blue',   'icon' => 'BankOutlined',     'href' => route('system_management.tenants.index')],
            ['key' => 'subs_active',      'label' => 'subs_active',      'value' => $activeSubs,    'hint' => 'En curso',               'color' => 'green',  'icon' => 'CrownOutlined',    'href' => null],
            ['key' => 'subs_expiring',    'label' => 'subs_expiring',    'value' => $expiringIn7,   'hint' => 'En 7 días',              'color' => $expiringIn7 > 0 ? 'orange' : 'default', 'icon' => 'ClockCircleOutlined', 'href' => null],
            ['key' => 'autos_runs_24h',   'label' => 'autos_runs_24h',   'value' => $autoLast24h,   'hint' => "{$autoFailed24h} fallaron", 'color' => $autoFailed24h > 0 ? 'red' : 'cyan', 'icon' => 'ThunderboltOutlined', 'href' => null],
        ];
    }

    /** Widgets para admin/user del tenant: vista del workspace. */
    protected function tenantWidgets(?User $user): array
    {
        if (!$user || !$user->tenant_id) return [];

        $tenantId = $user->tenant_id;
        $usersCount    = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->count();
        $autoActive    = Automation::where('tenant_id', $tenantId)->where('is_active', true)->count();
        $autoFailed7d  = AutomationRun::where('tenant_id', $tenantId)
            ->where('started_at', '>=', now()->subDays(7))
            ->where('status', 'failed')
            ->count();
        $sub = Subscription::where('tenant_id', $tenantId)
            ->whereIn('status', ['trial', 'active'])
            ->orderByDesc('ends_at')
            ->first();
        $daysLeft = $sub?->daysRemaining() ?? 0;

        return [
            ['key' => 'users_count',     'label' => 'users_count',     'value' => $usersCount,    'hint' => 'En tu workspace',        'color' => 'blue',   'icon' => 'UserOutlined',     'href' => route('user_management.users.index')],
            ['key' => 'automations',     'label' => 'automations',     'value' => $autoActive,    'hint' => 'Activas',                'color' => 'cyan',   'icon' => 'ThunderboltOutlined', 'href' => null],
            ['key' => 'auto_failures',   'label' => 'auto_failures',   'value' => $autoFailed7d,  'hint' => 'En los últimos 7 días',  'color' => $autoFailed7d > 0 ? 'red' : 'default', 'icon' => 'WarningOutlined', 'href' => null],
            ['key' => 'plan_days_left',  'label' => 'plan_days_left',  'value' => $daysLeft,      'hint' => $sub ? $sub->plan : '—',  'color' => $daysLeft <= 7 ? 'orange' : 'green', 'icon' => 'CrownOutlined', 'href' => null],
        ];
    }

    /**
     * «Alerta de Pendientes»: qué está trabado y dónde.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ DE DÓNDE SALE                                                        │
     * └──────────────────────────────────────────────────────────────────────┘
     * Es la pantalla de inicio del sistema Rails viejo
     * (`UserManagement::AuthenticationsController#index`), que resolvía sus
     * contadores con `find_by_sql` y `COUNT(IF(...))` sobre TODA la base. Acá se
     * hace con Eloquent normal para que el scope de tenant —y la restricción por
     * clientes asignados— se apliquen solos: el viejo contaba las recepciones de
     * todos los clientes para cualquiera que abriera el sistema.
     *
     * Se cuenta por MUESTRA y no por prueba: "faltan 3 muestras" es accionable,
     * "faltan 17 pruebas" no le dice a nadie a qué frasco ir. Y cada tarjeta
     * lleva su enlace, porque un número que no dice dónde ir obliga a buscar a
     * mano lo que ya se sabe que falta.
     */
    protected function labDashboard(?User $user, Request $request): array
    {
        if (! $user) {
            return ['labAlerts' => []];
        }

        $sinEquipo = Sample::query()->whereNull('equipment_id')->count();

        $sinPruebas = Sample::query()
            ->whereDoesntHave('tests', fn ($q) => $q->where('status', '!=', SampleTest::STATUS_CANCELLED))
            ->count();

        $sinValores = Sample::query()
            ->whereHas('tests', fn ($q) => $q->whereIn('status', [
                SampleTest::STATUS_PENDING, SampleTest::STATUS_IN_PROGRESS,
            ]))
            ->count();

        // Listas para informar: todas sus pruebas validadas y sin informe
        // principal. Es el trabajo que ya está hecho y que nadie entregó.
        $sinInforme = Sample::query()
            ->whereHas('tests', fn ($q) => $q->where('status', '!=', SampleTest::STATUS_CANCELLED))
            ->whereDoesntHave('tests', fn ($q) => $q->whereIn('status', [
                SampleTest::STATUS_PENDING, SampleTest::STATUS_IN_PROGRESS,
            ]))
            ->whereDoesntHave('reports', fn ($q) => $q->where('kind', SampleReport::KIND_PRIMARY))
            ->count();

        $sinEntregar = SampleReport::query()
            ->where('status', SampleReport::STATUS_ISSUED)
            ->whereNull('delivered_at')
            ->count();

        $sinOrden = Reception::query()
            ->where('status', '!=', Reception::STATUS_CANCELLED)
            ->where(fn ($q) => $q->whereNull('service_order')->orWhere('service_order', ''))
            ->count();

        // Vencidas: pasó la fecha comprometida y la entrega sigue abierta.
        $vencidas = Reception::query()
            ->where('status', Reception::STATUS_CONFIRMED)
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', now())
            ->count();

        $tarjeta = fn (string $key, int $valor, string $icono, string $color, string $href) => [
            'key'   => $key,
            'label' => $key,
            'value' => $valor,
            'color' => $valor > 0 ? $color : 'default',
            'icon'  => $icono,
            'href'  => $valor > 0 ? $href : null,
        ];

        $receptions = route('lab_management.receptions.index');

        return [
            'labAlerts' => [
                $tarjeta('lab_overdue',      $vencidas,    'ClockCircleOutlined',  'red',    $receptions),
                $tarjeta('lab_no_order',     $sinOrden,    'FileTextOutlined',     'orange', $receptions),
                $tarjeta('lab_no_equipment', $sinEquipo,   'ApartmentOutlined',    'orange', $receptions),
                $tarjeta('lab_no_tests',     $sinPruebas,  'ExperimentOutlined',   'orange', $receptions),
                $tarjeta('lab_no_values',    $sinValores,  'ProfileOutlined',      'blue',   route('lab_management.worksheets.index')),
                $tarjeta('lab_no_report',    $sinInforme,  'FileProtectOutlined',  'green',  route('lab_management.sample_reports.index')),
                $tarjeta('lab_undelivered',  $sinEntregar, 'SendOutlined',         'cyan',   route('lab_management.sample_reports.index')),
            ],
        ];
    }

    protected function recentAutomations(?User $user): array
    {
        if (!$user) return [];

        $q = AutomationRun::query()
            ->with('automation:id,name,tenant_id')
            ->orderByDesc('started_at')
            ->limit(5);

        if (!$user->hasRole('super')) {
            if (!$user->tenant_id) return [];
            $q->where('tenant_id', $user->tenant_id);
        }

        return $q->get(['id', 'automation_id', 'tenant_id', 'started_at', 'status', 'records_matched', 'output_summary'])
            ->map(fn ($r) => [
                'id'              => $r->id,
                'automation_id'   => $r->automation_id,
                'automation_name' => $r->automation?->name ?? '—',
                'started_at'      => $r->started_at?->toIso8601String(),
                'status'          => $r->status,
                'records_matched' => $r->records_matched,
                'output_summary'  => $r->output_summary,
            ])
            ->all();
    }

    /**
     * Suscripciones por vencer en 7 días. Super ve todas. Admin del
     * tenant ve solo la suya. Útil para alertar antes de la pérdida de servicio.
     */
    protected function expiringSubscriptions(?User $user): array
    {
        if (!$user) return [];

        $q = Subscription::query()
            ->with('tenant:id,name')
            ->whereIn('status', ['trial', 'active'])
            ->whereBetween('ends_at', [now(), now()->addDays(7)])
            ->orderBy('ends_at');

        if (!$user->hasRole('super')) {
            if (!$user->tenant_id) return [];
            $q->where('tenant_id', $user->tenant_id);
        }

        return $q->limit(10)
            ->get(['id', 'tenant_id', 'plan', 'status', 'ends_at'])
            ->map(fn ($s) => [
                'id'             => $s->id,
                'tenant_name'    => $s->tenant?->name ?? '—',
                'plan'           => $s->plan,
                'status'         => $s->status,
                'ends_at'        => $s->ends_at?->toIso8601String(),
                'days_remaining' => $s->daysRemaining(),
            ])
            ->all();
    }
}
