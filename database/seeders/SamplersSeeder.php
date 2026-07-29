<?php

namespace Database\Seeders;

use App\Models\Sampler;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Los muestreadores reales del laboratorio.
 *
 * Son los doce del catálogo del sistema anterior, en su orden. Ninguno es una
 * persona: son áreas propias (LABORATORIO, SERVICE CAMPO, REPARACIONES), el
 * cliente (CLIENTE, CLIENTE INTERNO) y terceros (ABB, SUBCONTRATISTA), más las
 * siglas de las líneas de servicio. Es lo que imprime el informe acreditado en
 * "Muestra extraída por".
 *
 * Por eso el muestreador no puede ser un usuario del sistema, que es como había
 * quedado modelado acá: dar de alta a "CLIENTE" como usuario con su contraseña
 * no tiene sentido, y el escape era un texto libre donde "Cliente" y "CLIENTE "
 * son dos muestreadores distintos.
 *
 * Idempotente por código: volver a correr el seed no duplica ni pisa lo que el
 * laboratorio haya editado del nombre.
 */
class SamplersSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = (int) config('lab.seed_tenant_id', 1);

        $muestreadores = [
            'LABORATORIO', 'SERVICE CAMPO', 'REPARACIONES', 'CLIENTE INTERNO',
            'CLIENTE', 'PPMV', 'PPHV', 'PA', 'PS', 'DM', 'ABB', 'SUBCONTRATISTA',
        ];

        foreach ($muestreadores as $indice => $nombre) {
            Sampler::firstOrCreate(
                ['code' => (string) ($indice + 1), 'tenant_id' => $tenantId],
                [
                    'slug'       => Str::random(22),
                    'name'       => $nombre,
                    'sort_order' => $indice + 1,
                    'is_active'  => true,
                ],
            );
        }

        $this->command?->info('Muestreadores: ' . count($muestreadores) . ' del catálogo del laboratorio.');
    }
}
