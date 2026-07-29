<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Master seeder.
 *
 * Order matters because of foreign keys:
 *   1. Languages       → no FK
 *   2. Regions         → no FK to other seeded tables
 *   3. Locales         → needs language_id
 *   4. Countries       → needs region_id + default_locale_id
 *   5. SystemModules   → no FK
 *   6. RolesAndPermissions (1st pass) → needs system_modules. Creates roles
 *      (super, admin, user, api) + permissions. User assignments warn
 *      because users don't exist yet — that's expected on this pass.
 *   7. Tenants         → creates workspaces + auto-creates a "system user"
 *      per workspace (needs roles + countries + locales).
 *   8. Users           → creates the human users (super, joe, jose, etc.).
 *   9. RolesAndPermissions (2nd pass) → assigns role to each seeded user by email.
 *
 * Para data fake de prueba (benchmarking), ver el bloque comentado al final de
 * RegionsSeeder.php — 1000 regiones generadas con nombres realistas.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // ── Master / catalog data ───────────────────────────────────
            LanguagesSeeder::class,
            RegionsSeeder::class,
            LocalesSeeder::class,
            CountriesSeeder::class,
            SystemModulesSeeder::class,

            // ── Pricing tiers (free/basic/pro/enterprise) ───────────────
            PlansSeeder::class,

            // ── Settings globales (25 keys: app, features, downloads,
            //    notifications, security, exports, uploads, audit, bulk).
            //    Critical: muchos servicios leen Setting::get(...) y sin
            //    estos defaults el comportamiento cae a hardcode o falla.
            SettingsSeeder::class,

            // ── Roles + permissions (1st pass — definitions only) ──────
            RolesAndPermissionsSeeder::class,

            // ── Tenants + per-tenant system user ────────────────────────
            TenantsSeeder::class,

            // ── Human users ─────────────────────────────────────────────
            UsersSeeder::class,

            // ── Roles + permissions (2nd pass — assigns roles to users) ─
            RolesAndPermissionsSeeder::class,

            // ── Custom permissions: los que el super creó desde la UI
            //    (SystemModules → agregar acción). Repuebla desde snapshot
            //    JSON. Idempotente: si están todos, no hace nada.
            CustomPermissionsSeeder::class,

            // ── Roles custom de demostracion en Empresa 1 y 2 + asignacion a
            //    sus workers. Demuestra el patron real de delegacion en un
            //    workspace con team_management. Idempotente.
            ExampleTenantRolesSeeder::class,

            // ── Workspace "Estudio Perez" — admin solo, plan free (sin
            //    suscripcion). Cubre el tier free del demo base.
            ExamplePersonalWorkspaceSeeder::class,

            // ── Suscripciones del demo base: Empresa 1 → enterprise,
            //    Empresa 2 → pro, Independiente → basic. El plan se deriva
            //    de la suscripcion vigente.
            ExampleSubscriptionsSeeder::class,

            // ── TR APP — catálogo inicial de marcas/fabricantes (editable). ─
            BrandsSeeder::class,
            TapChangerTypesSeeder::class,
            // Catálogos per-tenant nuevos (seeders vacíos a propósito: cada
            // workspace carga los suyos; el super crea los globales desde la UI).
            // Se registran igual por consistencia/futuro. La registración de los
            // módulos en system_modules + permisos ya la hace SystemModulesSeeder.
            LaboratoriesSeeder::class,
            TapChangerBrandsSeeder::class,
            TapChangerModelsSeeder::class,
            TapChangerTechnologiesSeeder::class,
            ConnectionTypesSeeder::class,
            TransformerPreservationsSeeder::class,
            LabCatalogsSeeder::class,

            // Los parámetros medibles: la pieza que el sistema viejo no tenía y
            // sin la cual el informe no puede consultar por parámetro. La lista
            // sale de las columnas de resultado de las 29 pruebas reales.
            LabAnalytesSeeder::class,

            // ── El laboratorio, cargado ─────────────────────────────────
            // Estos cuatro dejan el sistema USABLE apenas termina el seed. El
            // orden no es negociable, cada uno necesita al anterior:
            //
            //   1. las 29 pruebas reales, con sus columnas y sus opciones,
            //      importadas del volcado de definiciones del sistema viejo;
            //   2. las fórmulas de esas pruebas, traducidas del JavaScript que
            //      el viejo guardaba en la base y direccionaba por posición;
            //   3. los instrumentos de bancada, derivados de las opciones que
            //      el viejo guardaba como texto suelto ("Bureta PP-LA-01C");
            //   4. el enlace de cada columna de resultado con su parámetro, sin
            //      el cual validar una hoja no materializa ningún resultado y
            //      el informe se queda sin nada que leer.
            //
            // Antes esto eran tres comandos que había que acordarse de correr a
            // mano después de cada migrate:fresh, y sin ellos el sistema se veía
            // roto: Pruebas vacío y ninguna hoja de trabajo que se pudiera crear.
            LabTestTemplatesSeeder::class,
            // Qué MIDE cada columna. El importador copiaba el tipo del sistema
            // anterior, que guardaba todo en una sola columna de texto y por eso
            // declaraba "texto" hasta para los números. Va antes de las fórmulas
            // porque una fórmula no puede leer como número una columna de texto.
            LabTestFieldTypesSeeder::class,
            // Cuántas LECTURAS admite cada columna: Grado de Polimerización mide
            // la masa dos veces y los tiempos de flujo del viscosímetro cuatro.
            // En el sistema anterior ese número estaba clavado en el HTML del
            // formulario (`2.times` / `4.times` por rango de índice), no en la
            // base, así que la importación no tenía de dónde sacarlo.
            LabTestReplicatesSeeder::class,
            LabTestFormulasSeeder::class,
            LabInstrumentsSeeder::class,
            SamplersSeeder::class,
            LabAnalyteMapSeeder::class,

            // ── Contra qué se compara el resultado ──────────────────────
            // Las normas y los métodos primero; después los cuadros de límites,
            // que los referencian. Es lo que convierte un número en un
            // veredicto: sin esto, `results.spec_status` queda en nulo — que
            // significa "sin criterio", NO "cumple".
            LabStandardsSeeder::class,
            LabSpecSetsSeeder::class,

            // ── Clientes reales (los activos del sistema viejo) en Empresa 1. ─
            CustomersSeeder::class,

            // ── Demostración: equipos, hojas de trabajo cargadas y validadas,
            //    resultados y cartas de control. Va DESPUÉS de los clientes
            //    porque los equipos cuelgan de ellos. Es lo único de todo el
            //    seed que son datos inventados, y está marcado como tal.
            LabDemoWorksheetsSeeder::class,

            // Fase 12: acá entra la migración de los datos históricos del
            // laboratorio (equipos, muestras, resultados e informes emitidos),
            // con seeders idempotentes sobre volcados versionados.
        ]);

        // Los dumps legacy se insertan con IDs explícitos (SQL crudo), lo que NO
        // avanza las secuencias de Postgres → el primer create/duplicate del
        // sistema chocaría con un id ya usado. Resincronizamos al final. Solo
        // pgsql; en sqlite (tests) el comando no hace nada.
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\Artisan::call('db:fix-sequences');
        }
    }
}
