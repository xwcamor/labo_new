<?php

namespace Database\Seeders;

use App\Models\TestDefinition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Las 29 pruebas reales del laboratorio, con sus columnas y sus opciones.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ESTO ES UN SEMBRADOR Y NO SOLO UN COMANDO                        │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El importador `import:legacy-tests` ya existía y hacía todo el trabajo, pero
 * había que acordarse de correrlo a mano después de cada `migrate:fresh`. El
 * resultado era que `setup:project` dejaba el sistema con las tablas de
 * laboratorio VACÍAS: se entraba a Pruebas y no había ninguna, se entraba a
 * Hojas de trabajo y no se podía crear ninguna porque no había de qué prueba.
 * Un sistema que hay que sembrar en cuatro pasos, y que sin esos pasos parece
 * roto, es un sistema que no está terminado.
 *
 * Acá el importador se corre DENTRO del seed. Un solo comando y el laboratorio
 * queda cargado.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ SE VERSIONA Y QUÉ NO                                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El archivo que lee está en `docs/migracion/esquema/catalogos-definiciones.sql`
 * y contiene SOLO las DEFINICIONES: los cuadros de ensayo, sus columnas y las
 * opciones de sus listas. No hay ni un dato de cliente, ni un equipo, ni una
 * muestra, ni un usuario. Eso importa porque este repositorio es público: el
 * volcado completo de la base vieja NO se versiona ni se va a versionar.
 *
 * Es idempotente: el importador se ancla al `legacy_id` de cada fila, así que
 * correrlo diez veces deja el mismo resultado.
 */
class LabTestTemplatesSeeder extends Seeder
{
    private const VOLCADO = 'docs/migracion/esquema/catalogos-definiciones.sql';

    public function run(): void
    {
        $archivo = base_path(self::VOLCADO);

        if (! is_file($archivo)) {
            $this->command?->warn(
                'No se encontró ' . self::VOLCADO . '. Las pruebas quedan sin sembrar.'
            );

            return;
        }

        // El importador ya informa su propio detalle. Acá se lo deja hablar
        // solo si algo sale mal: en el seed completo, 29 líneas de detalle de
        // una etapa entre veinte tapan lo que importa.
        $codigo = Artisan::call('import:legacy-tests', ['file' => $archivo]);

        if ($codigo !== 0) {
            $this->command?->error('El importador de pruebas falló:');
            $this->command?->line(Artisan::output());

            return;
        }

        $pruebas = TestDefinition::count();
        $this->command?->info("Pruebas del laboratorio: {$pruebas} cargadas desde el volcado.");
    }
}
