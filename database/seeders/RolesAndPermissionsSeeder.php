<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Common actions per module — fuente única en el Observer.
        $actions = \App\Observers\SystemModuleObserver::CANONICAL_ACTIONS;

        // Read modules from system_modules table
        $modules = DB::table('system_modules')->whereNull('deleted_at')->get();

        // Generate permissions dynamically
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name'       => "{$module->permission_key}.{$action}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // Permisos transversales que NO salen de un módulo de system_modules.
        // Comentarios de usuario sobre transformadores y muestras: el admin decide
        // qué perfiles pueden ver/crear/borrar comentarios.
        // transformers.samples: cargar/editar MUESTRAS de ensayos sin poder tocar
        // la ficha del trafo (perfiles tipo "Cliente Editor"). transformers.edit
        // lo incluye vía OR en las rutas.
        // diagnosis_notes.create: ESCRIBIR la "Nota del diagnosticador" (hilo a
        // nivel del transformer). Separado de comments.create — que ahora solo
        // habilita los comentarios POR MUESTRA — para que un cargador de muestras
        // pueda comentar SU muestra sin firmar la nota del especialista. Ver/borrar
        // siguen compartiendo comments.view/comments.delete (no se separaron).
        // `worksheets.validate` es un permiso APARTE de `worksheets.edit`, y no
        // está en las acciones canónicas porque no es un CRUD: es la firma del
        // supervisor sobre el ensayo. Tenerlo separado corrige un agujero real
        // del sistema Rails viejo, donde la pantalla de validar escondía su
        // enlace a los no supervisores pero la acción verificaba el permiso de
        // EDITAR, así que cualquiera que pudiera editar podía validar
        // escribiendo la dirección a mano.
        foreach ([
            'comments.view', 'comments.create', 'comments.delete',
            'transformers.samples', 'diagnosis_notes.create',
            'worksheets.validate',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // ─── Roles ────────────────────────────────────────────────────────
        $superAdmin = Role::updateOrCreate(
            ['name' => 'super', 'guard_name' => 'web'],
            ['description' => 'Acceso total al sistema (bypass via Gate::before)']
        );

        $admin = Role::updateOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['description' => 'Administrador de cliente']
        );

        // 'api' role — assigned to the invisible system user that holds API tokens.
        // Permissions are NOT attached at the role level here because each token
        // carries its own ability list (Sanctum abilities). The role just lets us
        // identify and hide these users in lists.
        Role::updateOrCreate(
            ['name' => 'api', 'guard_name' => 'web'],
            ['description' => 'Usuario interno para tokens de API (no logueable)']
        );

        // super: Gate::before bypass + sync all (consistency con policy checks).
        $superAdmin->syncPermissions(Permission::all());

        // admin: TODOS los permisos del sistema. Los módulos core (tenants, regions,
        // languages, etc.) no generan permissions a propósito → admin nunca puede
        // siquiera intentar asignarlos a sus roles. Ver SystemModulesSeeder.
        $admin->syncPermissions(Permission::all());

        // ─── 4 PERFILES GLOBALES (plantillas que publica el super) ──────────
        // tenant_id null = globales → TODOS los workspaces los ven y los asignan
        // a sus usuarios; solo-lectura para los admin, solo el super los edita.
        // Borra los perfiles globales de versiones previas (se reemplazan).
        Role::whereNull('tenant_id')
            ->whereIn('name', [
                'cliente_lectura', 'cliente_editor',
                'Gestor de clientes', 'Visualizador de transformadores',
                'Administrador de transformadores', 'Técnico de laboratorio',
                'Gestor de catálogos',
            ])
            ->get()
            ->each(function ($r) {
                DB::table('role_has_permissions')->where('role_id', $r->id)->delete();
                DB::table('model_has_roles')->where('role_id', $r->id)->delete();
                $r->delete();
            });

        // Helpers para armar sets de permisos por módulo.
        $canon = \App\Observers\SystemModuleObserver::CANONICAL_ACTIONS; // view,show,create,edit,delete,export,import
        $all   = fn (string $k) => array_map(fn ($a) => "{$k}.{$a}", $canon);
        $pick  = fn (string $k, array $acts) => array_map(fn ($a) => "{$k}.{$a}", $acts);
        $grant = function (Role $role, array $names) {
            $role->syncPermissions(
                Permission::whereIn('name', $names)->where('guard_name', 'web')->get()
            );
        };

        // Módulos de DATOS de negocio (excluye oil_types/equipment_types/
        // tap_changer_types: son globales super-only y no se ofrecen a perfiles).
        $bizModules = ['customers', 'equipment', 'brands', 'laboratories',
            'tap_changer_brands', 'tap_changer_models', 'tap_changer_technologies'];
        $noDelete   = ['view', 'show', 'create', 'edit', 'export', 'import']; // todo salvo delete

        // Plantillas de ensayo: lo que define CÓMO se mide. Cambiar una fórmula
        // o un límite acá cambia todos los ensayos que se corran después, así
        // que es del supervisor, no del analista.
        $labTemplateModules = ['test_groups', 'test_definitions', 'analytes', 'instruments'];

        // Bancada y control de calidad: lo que se hace todos los días.
        $labBenchModules = ['worksheets', 'qc_charts'];

        // Soporte (editor): crea/edita cualquier dato de negocio, NO elimina.
        // Incluye diagnosis_notes.create: es un rol de especialista, sí firma la
        // "Nota del diagnosticador" (además de comentar muestras).
        $editorParts = array_map(fn ($m) => $pick($m, $noDelete), $bizModules);
        $editorParts[] = ['transformers.samples', 'comments.view', 'comments.create', 'diagnosis_notes.create'];
        $editorPerms = array_merge(...$editorParts);

        // Soporte (editor full): todo sobre los datos de negocio (incl. eliminar).
        // NO incluye users/roles: el super y el admin ya tienen acceso a esos
        // módulos por su rol; no se delegan vía un perfil custom.
        $fullParts   = array_map(fn ($m) => $all($m), $bizModules);
        $fullParts[] = ['transformers.samples', 'comments.view', 'comments.create', 'comments.delete', 'diagnosis_notes.create'];
        $fullPerms   = array_merge(...$fullParts);

        // ── Perfiles del laboratorio ─────────────────────────────────────
        // El sistema Rails viejo tenía los cuatro CRUD de plantillas detrás de
        // UN SOLO permiso indistinto (el 14): quien podía ver la configuración
        // de una prueba podía también borrarla. Y la pantalla de validar la
        // hoja verificaba el permiso de EDITAR en vez del de validar, así que
        // el botón estaba escondido para el analista pero la dirección seguía
        // siendo accesible. Acá cada acción es su permiso y el reparto entre
        // analista y supervisor es explícito.

        // El analista corre los ensayos: carga hojas de trabajo y ve las
        // plantillas para saber qué columnas llenar. NO las edita, y NO valida
        // (validar la hoja es del supervisor).
        $analystPerms = array_merge(
            $pick('worksheets', ['view', 'show', 'create', 'edit', 'export']),
            $pick('qc_charts', ['view', 'show']),
            $pick('equipment', ['view', 'show']),
            $pick('customers', ['view', 'show']),
            ...array_map(fn ($m) => $pick($m, ['view', 'show']), $labTemplateModules),
        );

        // El supervisor define cómo se mide y responde por la calidad: mantiene
        // las plantillas, fija los límites de las cartas de control y valida.
        $supervisorPerms = array_merge(
            $pick('equipment', ['view', 'show', 'export']),
            $pick('customers', ['view', 'show', 'export']),
            ['worksheets.validate'],
            // Las listas del formulario del informe: son datos del laboratorio y
            // quien responde por que digan lo mismo en todas las muestras es el
            // supervisor. Sin `import`/`export`: seis filas no se exportan.
            $pick('report_catalogs', ['view', 'show', 'create', 'edit', 'delete']),
            ...array_map(fn ($m) => $all($m), array_merge($labTemplateModules, $labBenchModules)),
        );

        // Reglas de diagnóstico y logs del sistema NO hace falta excluirlos: sus
        // menús son hasRole(super|admin), así que un perfil CUSTOM nunca los ve.
        $profiles = [
            'Empresa (solo lectura)' => [
                'desc'  => 'Solo lectura: ve el tablero y los equipos con sus diagnósticos. No crea, edita ni elimina nada.',
                'perms' => array_merge($pick('equipment', ['view', 'show', 'export']), ['comments.view']),
            ],
            'Empresa (carga de muestras)' => [
                'desc'  => 'Ve el tablero y los equipos; no edita ni elimina equipos, pero carga muestras de ensayo y comenta SUS muestras. Lee la nota del diagnosticador pero NO la escribe (eso es del especialista).',
                // comments.view + comments.create (comentar SUS muestras), pero SIN
                // diagnosis_notes.create: la "Nota del diagnosticador" la firma el
                // especialista, no quien carga muestras.
                'perms' => array_merge($pick('equipment', ['view', 'show', 'export']), ['transformers.samples', 'comments.view', 'comments.create']),
            ],
            'Analista de laboratorio' => [
                'desc'  => 'Corre los ensayos: crea y carga hojas de trabajo y consulta las plantillas y las cartas de control. No modifica las plantillas ni valida las hojas: eso es del supervisor.',
                'perms' => $analystPerms,
            ],
            'Supervisor de laboratorio' => [
                'desc'  => 'Define cómo se mide y responde por la calidad analítica: mantiene las pruebas, sus columnas y fórmulas, los instrumentos y los límites de las cartas de control, y valida las hojas de trabajo.',
                'perms' => $supervisorPerms,
            ],
            'Soporte (editor)' => [
                'desc'  => 'Crea y edita cualquier dato (clientes, equipos, catálogos) pero NO elimina. Sin reglas de diagnóstico ni logs.',
                'perms' => $editorPerms,
            ],
            'Soporte (editor full)' => [
                'desc'  => 'Gestión completa de los datos de negocio: crea, edita y elimina clientes, equipos y catálogos. No gestiona accesos (usuarios/perfiles) ni ve reglas de diagnóstico o logs.',
                'perms' => $fullPerms,
            ],
        ];

        $globalRoles = [];
        foreach ($profiles as $name => $cfg) {
            $role = Role::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web', 'tenant_id' => null],
                ['description' => $cfg['desc'], 'is_active' => true]
            );
            $grant($role, $cfg['perms']);
            $globalRoles[$name] = $role;
        }

        // ─── Assign roles to seeded users by email ────────────────────────
        // Workers (jose/pedro/luis/ana) quedan SIN rol por diseño. El admin de
        // cada tenant les asigna un perfil custom (Soporte/Editor/Visitante,
        // ver ExampleTenantRolesSeeder) cuando arme su equipo.
        $assignments = [
            'super@example.com' => $superAdmin,  // platform owner
            'joe@example.com'             => $admin,        // Empresa 1 admin
            'yugi@example.com'             => $admin,        // Empresa 2 admin
            'independiente@example.com'     => $admin,        // Independiente (admin de su propio workspace)
        ];

        foreach ($assignments as $email => $role) {
            $userModel = User::withoutGlobalScopes()->where('email', $email)->first();
            if ($userModel) {
                $userModel->syncRoles([$role]);
                $this->command?->info("  · {$email}  →  {$role->name}");
            } else {
                $this->command?->warn("  · {$email}  NOT FOUND (run UsersSeeder first)");
            }
        }

        // Workers (no-admin) de Empresa 1 y 2: cada uno con un perfil GLOBAL
        // distinto para probar visualmente los permisos. En la 1ª pasada (antes
        // de UsersSeeder) no existen y se omiten; en la 2ª se asignan.
        $workerAssignments = [
            'jose@example.com'  => 'Empresa (solo lectura)',       // Empresa 1
            'pedro@example.com' => 'Empresa (carga de muestras)',  // Empresa 1
            'luis@example.com'  => 'Soporte (editor)',             // Empresa 2
            'ana@example.com'   => 'Soporte (editor full)',        // Empresa 2
        ];
        foreach ($workerAssignments as $email => $roleName) {
            $userModel = User::withoutGlobalScopes()->where('email', $email)->first();
            if ($userModel && isset($globalRoles[$roleName])) {
                $userModel->syncRoles([$globalRoles[$roleName]]);
                $this->command?->info("  · {$email}  →  {$roleName}");
            }
        }

        $this->command?->info('Permissions: ' . Permission::count() . '. Roles: ' . Role::count() . '.');
    }
}
