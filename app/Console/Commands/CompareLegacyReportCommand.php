<?php

namespace App\Console\Commands;

use App\Models\Result;
use App\Models\Sample;
use App\Services\Lab\TestReportPayload;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;

/**
 * Los dos informes de la MISMA muestra, uno al lado del otro.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ PARA QUÉ                                                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * La discusión sobre qué cambió del informe viejo al nuevo se venía teniendo
 * de memoria, y de memoria se pierde: el sistema Ruby ya no corre y su PDF hay
 * que buscarlo en un correo viejo. Este comando reconstruye el papel de antes
 * con LOS MISMOS DATOS que el de ahora, así la comparación es sobre dos hojas
 * que se pueden poner una junto a la otra.
 *
 * La reproducción sale de las plantillas ERB del repositorio `labo_old`, con
 * sus erratas incluidas a propósito ("Tension Interfacial" sin tilde, "omh*cm",
 * "VISCOCIDAD", la frase duplicada del descargo legal). Corregirlas haría que
 * la comparación mintiera.
 *
 * NO es un informe emitible: no lleva código de verificación, no se audita y no
 * se le puede pedir al sistema desde ninguna pantalla. Es documentación.
 */
class CompareLegacyReportCommand extends Command
{
    protected $signature = 'report:compare
                            {sample? : Código o slug de la muestra; si falta, la primera con resultados}
                            {--out= : Carpeta donde dejar los dos PDF}';

    protected $description = 'Genera el informe viejo y el nuevo de la misma muestra, para compararlos';

    /** Cómo rotulaba el informe viejo cada parámetro: nombre, unidad e ítem. */
    private const FIQUIS = [
        'acid'   => [1,  'Número Ácido',                          'mgKOH/g '],
        'fp25'   => [2,  'Factor de Potencia 25°C,60HZ',          '% '],
        'fp90'   => [3,  'Factor de Potencia 90°C,60HZ',          '% '],
        'fp100'  => [4,  'Factor de Potencia 100°C,60HZ',         '% '],
        'rig'    => [5,  'Rigidez Dieléctrica',                   'kV/2.0mm '],
        'rig877' => [6,  'Rigidez Dieléctrica Electrodos planos', 'kV/2.0mm '],
        'ten'    => [7,  'Tension Interfacial',                   'mN/m '],
        'wat'    => [8,  'Contenido de Agua',                     'ppm '],
        'col'    => [9,  'Color',                                 '- '],
        'con'    => [10, 'Condición Visual',                      '- '],
        'den'    => [11, 'Densidad Relativa (15 °C/15 °C)',       '- '],
        'r25'    => [12, 'Resistividad Volumétrica 25º',          'omh*cm'],
        'r100'   => [13, 'Resistividad Volumétrica 100º',         'omh*cm'],
    ];

    /** Los gases, con su límite de detección clavado en el HTML del viejo. */
    private const GASES = [
        'h2'   => [1, 'Hidrogeno (H2) ',              1.0,   true],
        'o2'   => [2, 'Oxígeno (O2) ',                105.4, false],
        'n2'   => [3, 'Nitrógeno (N2) ',              396.2, false],
        'ch4'  => [4, 'Metano (CH4) ',                0.3,   true],
        'co'   => [5, 'Monóxido de Carbono(CO) ',     0.3,   true],
        'co2'  => [6, 'Dióxido de Carbono (CO2) ',    4.0,   true],
        'c2h4' => [7, 'Etileno (C2H4) ',              0.3,   true],
        'c2h6' => [8, 'Etano (C2H6) ',                0.3,   true],
        'c2h2' => [9, 'Acetileno (C2H2) ',            0.4,   true],
    ];

    public function handle(TestReportPayload $payload): int
    {
        // Los dos papeles en el MISMO idioma: si uno sale en inglés y el otro
        // en español, la comparación se vuelve sobre la traducción y no sobre
        // lo que cambió.
        app()->setLocale(config('app.locale') === 'en' ? 'es' : config('app.locale'));

        $muestra = $this->resolverMuestra();

        if ($muestra === null) {
            $this->error('No encontré ninguna muestra con resultados. Corré `php artisan setup:project` primero.');
            return self::FAILURE;
        }

        $carpeta = rtrim((string) ($this->option('out') ?: storage_path('app/comparacion')), '/');
        if (! is_dir($carpeta)) {
            mkdir($carpeta, 0775, true);
        }

        $datos = $payload->forSample($muestra);

        $nuevo = $carpeta . '/informe-NUEVO-' . $muestra->code . '.pdf';
        $viejo = $carpeta . '/informe-VIEJO-' . $muestra->code . '.pdf';

        file_put_contents($nuevo, $this->renderNuevo($datos));
        file_put_contents($viejo, $this->renderViejo($muestra, $datos));

        $this->info('Muestra: ' . $muestra->code);
        $this->line('  nuevo → ' . $nuevo);
        $this->line('  viejo → ' . $viejo);

        return self::SUCCESS;
    }

    private function resolverMuestra(): ?Sample
    {
        $ref = $this->argument('sample');

        if ($ref) {
            return Sample::where('code', $ref)->orWhere('slug', $ref)->first();
        }

        return Sample::whereHas('results')->orderBy('id')->first();
    }

    // ── El informe de hoy ────────────────────────────────────────────────

    private function renderNuevo(array $datos): string
    {
        $tenant = auth()->user()?->tenant ?? \App\Models\Tenant::query()->orderBy('id')->first();

        $pdf = Pdf::loadView('lab_management/reports/test_report', $datos + [
            'generatedAt' => now(),
            'generatedBy' => 'comparación',
            // Sin código ni QR: el papel de comparación no se verifica contra
            // nada, y estampar un código que no existe en el registro sería
            // fabricar una constancia.
            'verifyCode'  => null,
            'verifyQr'    => null,
            'letterhead'  => [
                'name'               => $tenant?->name,
                'address'            => $tenant?->address,
                'logo'               => null,
                'accreditation_logo' => null,
                'accreditation_note' => $tenant?->accreditation_note,
                'disclaimer'         => $tenant?->report_disclaimer,
            ],
            'signers'     => collect(),
        ])->setPaper('a4');

        return $pdf->output();
    }

    // ── El informe de antes ──────────────────────────────────────────────

    private function renderViejo(Sample $muestra, array $datos): string
    {
        $muestra->loadMissing(['reception.customer', 'reception.sampler', 'equipment.equipmentType',
            'equipment.oilType', 'equipment.brand', 'equipment.preservation',
            'equipment.tapChangerType', 'equipment.location']);

        $resultados = Result::where('sample_id', $muestra->id)
            ->with(['analyte:id,code,name', 'field:id,decimals'])
            ->get()
            ->keyBy(fn ($r) => $r->analyte?->code);

        $paginas = [];

        // La norma del método sale del MISMO sitio que en el informe nuevo:
        // la columna `standard` de la hoja de bancada, no de la plantilla.
        $normas = $this->normasPorAnalito($datos);

        $fiquis = $this->paginaFiquis($resultados, $normas);
        if ($fiquis !== null) {
            $paginas[] = $fiquis;
        }

        $cromas = $this->paginaCromas($resultados);
        if ($cromas !== null) {
            $paginas[] = $cromas;
        }

        $paginas[] = $this->paginaAnalisis($datos);

        $re = $muestra->reception;
        $eq = $muestra->equipment;

        $pdf = Pdf::loadView('lab_management/reports/legacy/report', [
            'paginas' => $paginas,
            'numero'  => 'REP-LAB-' . $muestra->year . '-' . str_pad((string) $muestra->number, 4, '0', STR_PAD_LEFT),
            'logo'    => '<span style="font-size:16px;font-weight:bold;color:#354A5F">HITACHI ENERGY</span>',
            'anab'    => '<span style="font-size:11px;font-weight:bold;color:#7a7a7a">[ sello ANAB ]</span>',
            'cli' => [
                'nombre'      => $re?->customer?->name ?? '',
                'direccion'   => $re?->customer?->address ?? '',
                'contacto'    => $re?->contact_info ?? '',
                'usuario_final' => $re?->end_user,
                'os'          => $re?->service_order,
                'recepcion'   => $re?->received_at?->format('d-m-Y'),
                'emision'     => now()->format('d-m-Y'),
                'muestreador' => $re?->sampler?->name ?? $re?->sampler_name,
                'descripcion' => $muestra->description ?? '',
            ],
            'eq' => [
                'serie'        => $eq?->serial,
                'tag'          => $eq?->tag,
                'locacion'     => $eq?->location?->name,
                'tipo'         => $eq?->equipmentType?->name,
                'fabricante'   => $eq?->brand?->name,
                'anio'         => $eq?->manufacture_year,
                'conmutador'   => $eq?->tapChangerType?->name,
                'tension'      => $eq?->voltage_label,
                'potencia'     => $eq?->power_label,
                'preservacion' => $eq?->preservation?->name,
                'aceite'       => $eq?->oilType?->name,
                'marca_aceite' => $eq?->oil_brand,
                'volumen'      => $eq?->oil_volume,
                'unidad'       => $eq?->oil_volume_unit,
                'operacion'    => $eq?->service_state === 'in_service' ? 'Si' : '-',
                'muestreo'     => $muestra->sampled_at?->format('d-m-Y'),
                'punto'        => $muestra->sampling_point,
                'razon'        => $muestra->sampling_reason,
                'temp_aceite'  => $muestra->oil_temp_c,
                'temp_campo'   => $muestra->equipment_temp_c,
                'temp_ambiente' => $muestra->ambient_temp_c,
                'humedad'      => $muestra->relative_humidity,
            ],
            'acreditacion' => [
                'es' => 'Esta prueba está acreditada bajo la acreditación del laboratorio ISO/IEC 17025 emitida por la Junta Nacional de Acreditación ANSI-ASQ. Consulte el certificado y el alcance de la acreditación AT-2596.',
                'en' => 'This test is accredited under the laboratory\'s ISO/IEC 17025 accreditation issued by the ANSI-ASQ National Accreditation Board. Refer to certificate and scope of accreditation AT-2596.',
            ],
            'legal' => 'Los resultados obtenidos en este reporte solo corresponden a las muestras analizadas bajo las condiciones de ensayo. Cuando la muestra es proporcionada por el cliente interno o externo los resultados se aplican a la muestra como se recibio. Hitachi Energy Perú S.A. Cuando la muestra es proporcionada por el cliente interno o externo los resultados se aplican a la muestra como se recibio. Hitachi Energy Perú S.A. no se responsabiliza cuando algun componente de este informe ha sido proporcionado por el cliente y tampoco por el uso inadecuado de este documento. Hitachi Energy Perú S.A. no hace ninguna garantía o representación expresa o implícita en cuanto a condición, productividad o correcto funcionamiento de cualquier equipo u otros bienes que pueda ser objeto de este informe o depender de ella para la razón que sea. Se prohíbe la reproducción total o parcial de este documento sin autorización previa escrita. Los resultados de los ensayos no deben ser utilizados como una certificación de conformidad o como un certificado del sistema de calidad. Los análisis, opiniones o interpretaciones contenidas en este informe se basan en el material recolectado y representan el mejor juicio de Hitachi Energy Perú S.A. y no son refrendadas por el ente acreditador',
            'firma' => ['nombre' => '', 'cargo' => ''],
        ])->setPaper('a4');

        // El viejo numeraba con el JavaScript de wkhtmltopdf. dompdf no lo
        // tiene: se dibuja sobre el lienzo, igual que ya hace el informe nuevo.
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $fuente = $dompdf->getFontMetrics()->getFont('Helvetica', 'normal');
        $dompdf->getCanvas()->page_text(
            470.0, 812.0, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 8, [0.13, 0.15, 0.16],
        );

        return $pdf->output();
    }

    /** @param \Illuminate\Support\Collection<string,Result> $resultados */
    /** La norma del método, con el superíndice (A)/(NA) como lo imprimía el viejo. */
    private function normaConFlag(?array $norma): string
    {
        if ($norma === null || ($norma['label'] ?? null) === null) {
            return '';
        }

        return e($norma['label'])
            . (($norma['flag'] ?? null) ? '<sup>(' . e($norma['flag']) . ')</sup>' : '');
    }

    private function paginaFiquis($resultados, array $normas): ?array
    {
        $filas = [];
        $item  = 0;

        foreach (self::FIQUIS as $codigo => [$_, $nombre, $unidad]) {
            $r = $resultados->get($codigo);
            if ($r === null) {
                continue;
            }

            $item++;
            $filas[] = [
                'item'        => $item,
                'norma'       => $this->normaConFlag($normas[$codigo] ?? null),
                'ensayo'      => $nombre,
                'unidad'      => $unidad,
                'orientacion' => $this->orientacionVieja($r),
                'resultado'   => $this->numeroViejoFiqui($codigo, $r),
                'fuera'       => $r->spec_status === 'out_of_spec',
            ];
        }

        if ($filas === []) {
            return null;
        }

        return [
            'tipo' => 'ensayo', 'titulo' => 'ENSAYOS FISICO-QUIMICOS', 'col3' => 'ENSAYO',
            'anab' => true, 'pie_celda' => true, 'filas' => $filas, 'relaciones' => null,
            'condiciones' => [
                '(*) Norma de referencia ' => 'IEEE C57.106-2015',
                'Fecha de Análisis' => now()->format('d-m-Y'),
                'Temp. de Muestra en Laboratorio' => '-  °C',
                'Temperatura Lab' => '-  °C',
                'Humedad Relativa Lab' => '-  %HR',
            ],
        ];
    }

    /** @param \Illuminate\Support\Collection<string,Result> $resultados */
    private function paginaCromas($resultados): ?array
    {
        if ($resultados->get('h2') === null) {
            return null;
        }

        $filas = [];
        $v = [];

        foreach (self::GASES as $codigo => [$item, $nombre, $lod, $compara]) {
            $r = $resultados->get($codigo);
            $valor = $r?->value_num !== null ? (float) $r->value_num : 0.0;
            $v[$codigo] = $valor;

            $filas[] = [
                'item'        => $item,
                'norma'       => 'ASTM 3612 - Método C',
                'ensayo'      => $nombre,
                'unidad'      => 'ppm ',
                'orientacion' => $r ? $this->orientacionVieja($r) : '-',
                // Ésta es LA diferencia: por debajo del límite de detección el
                // viejo imprimía el límite, no el número medido.
                'resultado'   => $valor < $lod ? '< ' . rtrim(rtrim(number_format($lod, 1, '.', ''), '0'), '.')
                                               : number_format($valor, 1, '.', ''),
                'fuera'       => $compara && $r?->spec_status === 'out_of_spec',
            ];
        }

        $tgc  = $v['co'] + $v['h2'] + $v['ch4'] + $v['c2h6'] + $v['c2h4'] + $v['c2h2'];
        $sc   = $v['ch4'] + $v['c2h6'] + $v['c2h4'] + $v['c2h2'];
        $tg   = array_sum($v);
        $base = $sc + $v['h2'];

        $n = fn (float $x) => is_nan($x) ? '0.0' : number_format($x, 2, '.', '');
        $d = fn (float $a, float $b) => $b == 0.0 ? '0.0' : number_format($a / $b, 2, '.', '');

        return [
            'tipo' => 'ensayo', 'titulo' => 'CROMATOGRÁFICO', 'col3' => 'GAS',
            'anab' => true, 'pie_celda' => false, 'filas' => $filas,
            'relaciones' => [
                'totales' => ['TG:' => $n($tg), 'TGC:' => $n($tgc), 'TGC-CO:' => $n($tgc - $v['co'])],
                'porcentaje_total' => ['TGC(%):' => $d($tgc * 100, $tg)],
                'ratios' => [
                    'CH4/H2:'     => $d($v['ch4'], $v['h2']),
                    'C2H2/H2:'    => $d($v['c2h2'], $v['h2']),
                    'C2H2/C2H4:'  => $d($v['c2h2'], $v['c2h4']),
                    'C2H2/C2H6:'  => $d($v['c2h2'], $v['c2h6']),
                    'C2H4/C2H6:'  => $d($v['c2h4'], $v['c2h6']),
                    'CO2/CO:'     => $d($v['co2'], $v['co']),
                    'O2/N2:'      => $d($v['o2'], $v['n2']),
                ],
                'porcentajes' => [
                    '%H2:'   => $d($v['h2'] * 100, $base),
                    '%CH4:'  => $d($v['ch4'] * 100, $base),
                    '%C2H6:' => $d($v['c2h6'] * 100, $base),
                    '%C2H4:' => $d($v['c2h4'] * 100, $base),
                    '%C2H2:' => $d($v['c2h2'] * 100, $base),
                ],
            ],
            'condiciones' => [
                '(*) Norma de referencia' => 'IEC 60599-2022',
                'Fecha de Análisis' => now()->format('d-m-Y'),
                'Presión Atmosférica Lab' => '-  hPa',
                'Temperatura Lab' => '-  °C',
                'Humedad Relativa Lab' => '-  %HR',
            ],
        ];
    }

    /**
     * Norma del método por código de analito, sacada del payload del informe
     * nuevo: así los dos papeles citan exactamente la misma norma y la
     * comparación no confunde un cambio de dato con un cambio de formato.
     *
     * @return array<string,array{label:?string,flag:?string}>
     */
    private function normasPorAnalito(array $datos): array
    {
        $mapa = [];

        foreach ($datos['sections'] ?? [] as $seccion) {
            foreach ($seccion['rows'] ?? [] as $fila) {
                if (($fila['code'] ?? null) !== null) {
                    $mapa[$fila['code']] = [
                        'label' => $fila['method'] ?? null,
                        'flag'  => $fila['accreditation'] ?? null,
                    ];
                }
            }
        }

        return $mapa;
    }

    private function paginaAnalisis(array $datos): array
    {
        $titulos = [
            'fisicoquimico' => 'FISICOQUIMICO',
            'analisis_cromatografico' => 'CROMATOGRAFICO',
            'pcb' => 'PCB', 'furanos' => 'FURANOS', 'particulas' => 'PARTICULAS',
            'azufre_corrosivo' => 'AZUFRE CORROSIVO', 'sedimentos' => 'SEDIMENTOS',
            'metales_en_aceite' => 'METALES EN ACEITE', 'viscocidad' => 'VISCOCIDAD',
            'dbds' => 'DBDS', 'inflamacion' => 'PUNTO DE INFLAMACIÓN',
            'fluidez' => 'PUNTO DE FLUIDEZ', 'inhibidor' => 'CONTENIDO DE INHIBIDOR',
            'grado_de_polimerizacion' => 'GRADO DE POLIMERIZACIÓN',
            'pasivador' => 'CONTENIDO DE PASIVADOR',
        ];

        $familias = [];

        foreach ($datos['analysis'] ?? [] as $bloque) {
            $familias[] = [
                'titulo' => $titulos[$bloque['family'] ?? ''] ?? mb_strtoupper((string) ($bloque['label'] ?? '')),
                'texto'  => $bloque['body'] ?? '',
            ];
        }

        return ['tipo' => 'analisis', 'anab' => false, 'familias' => $familias];
    }

    private function orientacionVieja(Result $r): string
    {
        if ($r->spec_max !== null) {
            return $this->num($r->spec_max) . ' - máximo';
        }

        if ($r->spec_min !== null) {
            return $this->num($r->spec_min) . ' - mínimo';
        }

        return '-';
    }

    private function numeroViejoFiqui(string $codigo, Result $r): string
    {
        $valor = (float) $r->value_num;

        return match ($codigo) {
            // Los tres tramos de la acidez, tal cual estaban en el ERB.
            'acid' => $valor < 0.010 ? ($valor < 0.005 ? '< 0.01' : '0.01') : number_format($valor, 2, '.', ''),
            'fp25', 'fp90', 'fp100' => number_format($valor, 3, '.', ''),
            // El viejo TRUNCABA la rigidez: 44.9 kV se imprimía 44.
            'rig', 'rig877' => (string) (int) $valor,
            default => (string) ($r->value_text ?? $this->num($valor)),
        };
    }

    private function num(mixed $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 6, '.', ''), '0'), '.');
    }
}
