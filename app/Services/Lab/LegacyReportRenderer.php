<?php

namespace App\Services\Lab;

use App\Models\Result;
use App\Models\Sample;
use App\Models\Signature;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * El informe con la MAQUETA DEL SISTEMA ANTERIOR, como opción de exportación.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ EXISTE UN SEGUNDO FORMATO                                        │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El laboratorio lleva años entregando el papel con esta maqueta y sus clientes
 * la conocen: una hoja por prueba, la banda del título, el sello ANAB en las
 * acreditadas, las relaciones de gases al pie del cromatográfico. El botón
 * "Exportar" ofrece las dos plantillas —la clásica y la moderna— y la elección
 * es de quien emite, no del sistema.
 *
 * Nació como el comando de comparación (`report:compare`) y se extrajo a
 * servicio cuando pasó a ser una salida real de la pantalla de informes. La
 * reproducción sale de las plantillas ERB del repositorio `labo_old`, con sus
 * erratas incluidas a propósito ("Tension Interfacial" sin tilde, "omh*cm",
 * "VISCOCIDAD"): son la maqueta que el cliente reconoce.
 *
 * Lo que NO reproduce del viejo son sus mentiras de datos: el número impreso
 * sale del veredicto congelado en `results` (el `< LOD` es presentación, el
 * truncado de la rigidez es presentación) — la base guarda siempre lo medido.
 */
class LegacyReportRenderer
{
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

    /**
     * El PDF con la maqueta clásica, como bytes.
     *
     * @param array $datos el payload del informe nuevo (`TestReportPayload`):
     *                     de ahí salen las normas de método y el análisis, para
     *                     que las dos plantillas digan lo mismo.
     */
    /**
     * ¿Se está reproduciendo el papel viejo para compararlo?
     *
     * Solo en ese caso se cae a los logos del sistema anterior cuando el
     * workspace no tiene los suyos: la comparación existe para ver el original.
     * En la exportación normal, sin logo cargado no se dibuja ninguno.
     */
    private bool $comparacion = false;

    public function paraComparacion(bool $si = true): static
    {
        $this->comparacion = $si;

        return $this;
    }

    public function render(Sample $muestra, array $datos, ?string $numero = null): string
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

        $condiciones = $this->condicionesDeLaHoja($muestra);

        // ┌──────────────────────────────────────────────────────────────────┐
        // │ UNA HOJA POR FAMILIA, EN EL ORDEN DEL SISTEMA ANTERIOR            │
        // └──────────────────────────────────────────────────────────────────┘
        // El orden y la maqueta de cada hoja salen de `config/legacy_report.php`,
        // no de una cadena de `if`. El sistema anterior tenía DIECISÉIS partials
        // —uno por prueba— y este informe reproducía solo DOS: fisicoquímico y
        // cromatografía. Las otras trece pruebas se cargaban, se validaban, se
        // imprimían en el PDF moderno… y en el clásico no existían: el cliente
        // que pidió furanos recibía un papel sin furanos.
        //
        // Esas dos siguen teniendo constructor propio: el fisicoquímico porque su
        // orden de filas y sus rótulos son los del papel viejo y no los del
        // catálogo, y la cromatografía por el bloque RELACIONES.
        $porFamilia = collect($datos['sections'] ?? [])->keyBy(fn ($s) => $s['family'] ?? '');

        foreach (array_keys((array) config('legacy_report.sheets')) as $familia) {
            $seccion = $porFamilia->get($familia);

            $hoja = match ($familia) {
                'fisicoquimico'           => $this->paginaFiquis($resultados, $normas, $condiciones),
                'analisis_cromatografico' => $this->paginaCromas($resultados, $condiciones),
                default => $seccion !== null
                    ? $this->paginaGenerica($familia, $seccion, $condiciones)
                    : null,
            };

            if ($hoja !== null) {
                $paginas[] = $hoja;
            }
        }

        $paginas[] = $this->paginaAnalisis($datos);

        $re = $muestra->reception;
        $eq = $muestra->equipment;
        $tenant = $muestra->tenant ?? \App\Models\Tenant::query()->orderBy('id')->first();

        $pdf = Pdf::loadView('lab_management/reports/legacy/report', [
            'paginas' => $paginas,
            // El correlativo del papel: el del informe registrado si lo hay, y
            // el formato del viejo (REP-LAB-año-número de muestra) si no.
            'numero'  => $numero ?: 'REP-LAB-' . $muestra->year . '-' . str_pad((string) $muestra->number, 4, '0', STR_PAD_LEFT),
            // Los logos SON los del sistema viejo: están en su carpeta de
            // assets y se copian a `storage/app/legacy-assets`. Un recuadro con
            // la palabra "ANAB" no sirve en un papel que sale a un cliente.
            // El membrete y el sello son DATOS DEL WORKSPACE, no archivos con
            // nombre fijo. Esta plantilla empezó como reproducción del papel
            // viejo y hoy es una exportación que el laboratorio ofrece: si
            // imprimiera el logo y el nombre de Hitachi, cualquier otro
            // laboratorio que la use estaría emitiendo un papel que dice ser de
            // otra empresa. Sin logo cargado no se dibuja ninguno — nunca el de
            // otro. El del sistema viejo queda de respaldo SOLO para la
            // comparación (`report:compare`), que es donde reproducir el
            // original es el objetivo.
            'logo'    => $this->imagen($tenant?->logo, 140, 70)
                ?? ($this->comparacion ? $this->imagenLegado('hitachi_logo_new.png', 140, 70) : ''),
            'anab'    => $this->imagen($tenant?->accreditation_logo, 100, 90)
                ?? ($this->comparacion ? $this->imagenLegado('anab_logo.png', 100, 90) : ''),
            // Los tres textos del pie: nombre, dirección y descargo legal. Los
            // tres estaban clavados con los datos de Hitachi.
            'empresa'   => $tenant?->name ?? '',
            'direccion' => $tenant?->address ?? '',
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
            // El párrafo de la acreditación (organismo, certificado y alcance)
            // también es del workspace: el número de certificado vence y otro
            // laboratorio se acredita con otro organismo. Vacío no imprime nada
            // — un laboratorio sin acreditar no puede emitir un papel que
            // insinúe que sí.
            'acreditacion' => $tenant?->accreditation_note ?? '',
            // El descargo legal es del workspace (`tenants.report_disclaimer`),
            // editable en "Mi workspace". Estaba clavado acá con el texto de
            // Hitachi —repetición incluida—: eso servía para la comparación y
            // no para un papel que sale a un cliente de otro laboratorio.
            'legal' => $tenant?->report_disclaimer ?? '',
            'firmantes' => $this->firmantes($tenant?->id),
        ])->setPaper('a4');

        // El viejo numeraba con el JavaScript de wkhtmltopdf. dompdf no lo
        // tiene: se dibuja sobre el lienzo, igual que hace el informe nuevo.
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $fuente = $dompdf->getFontMetrics()->getFont('Helvetica', 'normal');
        // Alineada con la dirección, en el mismo renglón y a la derecha. Antes
        // caía sobre la regla roja del pie y se leía pisada.
        $dompdf->getCanvas()->page_text(
            455.0, 806.0, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 9, [0.13, 0.15, 0.16],
        );

        return $pdf->output();
    }

    /**
     * Las condiciones del laboratorio al correr el ensayo, tomadas de la hoja.
     *
     * Los cuatro valores estaban CLAVADOS en raya en las dos plantillas de este
     * informe: se imprimía "-  °C" siempre, aunque el analista hubiera cargado la
     * temperatura. Un renglón que muestra una raya fija es peor que no estar,
     * porque parece un dato que alguien olvidó.
     *
     * Se toma la hoja MÁS RECIENTE que tocó esta muestra. No es exacto cuando la
     * muestra pasó por dos bancadas en días distintos —cada ensayo tuvo sus
     * condiciones— y el informe moderno sí lo resuelve por sección; acá se acepta
     * la aproximación porque esta maqueta imprime UN bloque de condiciones por
     * página de ensayo y el dato fino no cabe sin rediseñarla, que es justamente
     * lo que esta plantilla no debe hacer.
     *
     * @return array{temp:string,humedad:string,presion:string,sample_temp:string}
     */
    private function condicionesDeLaHoja(Sample $muestra): array
    {
        $hoja = \App\Models\Worksheet::query()
            ->join('worksheet_rows', 'worksheet_rows.worksheet_id', '=', 'worksheets.id')
            ->where('worksheet_rows.sample_id', $muestra->id)
            ->whereNull('worksheet_rows.deleted_at')
            ->orderByDesc('worksheets.run_date')
            ->first([
                'worksheets.ambient_temp_c',
                'worksheets.ambient_humidity',
                'worksheets.lab_pressure_hpa',
                'worksheets.sample_temp_c',
            ]);

        // La raya se conserva cuando no hay dato: es el criterio del proyecto —
        // nunca imprimir 0.00 donde no se midió, que es lo que hacía el viejo.
        $ver = fn (?string $campo, string $sufijo) => $hoja?->{$campo} === null
            ? '-  ' . trim($sufijo)
            : rtrim(rtrim(number_format((float) $hoja->{$campo}, 2, '.', ''), '0'), '.') . $sufijo;

        return [
            'temp'        => $ver('ambient_temp_c', ' °C'),
            'humedad'     => $ver('ambient_humidity', ' %HR'),
            'presion'     => $ver('lab_pressure_hpa', ' hPa'),
            'sample_temp' => $ver('sample_temp_c', ' °C'),
        ];
    }

    /** La norma del método, con el superíndice (A)/(NA) como lo imprimía el viejo. */
    private function normaConFlag(?array $norma): string
    {
        if ($norma === null || ($norma['label'] ?? null) === null) {
            return '';
        }

        return e($norma['label'])
            . (($norma['flag'] ?? null) ? '<sup>(' . e($norma['flag']) . ')</sup>' : '');
    }

    /** @param \Illuminate\Support\Collection<string,Result> $resultados */
    private function paginaFiquis($resultados, array $normas, array $condiciones): ?array
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

        return $this->hoja('fisicoquimico', $filas, [
            'Fecha de Análisis' => now()->format('d-m-Y'),
            // Las tres condiciones estaban CLAVADAS en raya. Salen de la hoja
            // que corrió el ensayo, que es donde se registran.
            'Temp. de Muestra en Laboratorio' => $condiciones['sample_temp'],
            'Temperatura Lab' => $condiciones['temp'],
            'Humedad Relativa Lab' => $condiciones['humedad'],
        ]);
    }

    /** @param \Illuminate\Support\Collection<string,Result> $resultados */
    private function paginaCromas($resultados, array $condiciones): ?array
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
                // Por debajo del límite de detección el viejo imprimía el
                // límite, no el número medido. Es presentación: la base guarda
                // siempre lo que midió el instrumento.
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

        return $this->hoja('analisis_cromatografico', $filas, [
            'Fecha de Análisis' => now()->format('d-m-Y'),
            // La presión del laboratorio: era una raya CLAVADA porque no existía
            // columna donde guardarla. Ahora sale de la hoja que corrió el
            // ensayo, igual que la temperatura y la humedad.
            'Presión Atmosférica Lab' => $condiciones['presion'],
            'Temperatura Lab' => $condiciones['temp'],
            'Humedad Relativa Lab' => $condiciones['humedad'],
        ], [
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
        ]);
    }

    /**
     * Arma una hoja de ensayo con la maqueta que su familia declara.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ UNA TABLA, DIECISÉIS HOJAS                                           │
     * └──────────────────────────────────────────────────────────────────────┘
     * Todo lo que distingue una hoja de otra —qué columnas lleva, si su método
     * está dentro del alcance acreditado, qué norma cita al pie, si lleva la
     * leyenda del tipo de celda— sale de `config/legacy_report.php`. El sistema
     * anterior resolvía eso con dieciséis archivos ERB casi idénticos, y por eso
     * el límite del DBDS terminó escrito a mano dentro del HTML de uno de ellos.
     *
     * @param  array<int,array<string,mixed>>  $filas
     * @param  array<string,string>            $condiciones
     * @param  array<string,mixed>|null        $relaciones
     * @return array<string,mixed>|null
     */
    private function hoja(string $familia, array $filas, array $condiciones, ?array $relaciones = null): ?array
    {
        if ($filas === []) {
            return null;
        }

        $maqueta = array_merge(
            (array) config('legacy_report.defaults'),
            (array) config("legacy_report.sheets.{$familia}", []),
        );

        // La norma de referencia va PRIMERA entre las condiciones, con el
        // asterisco al que remite la columna del valor de orientación. Si la
        // familia no declara ninguna, el renglón no se dibuja: el sistema
        // anterior imprimía "(*) Norma de referencia -" en hojas sin criterio, o
        // sea un asterisco que no llevaba a ninguna parte.
        if (($maqueta['standard_note'] ?? null) !== null) {
            $condiciones = ['(*) Norma de referencia' => $maqueta['standard_note']] + $condiciones;
        }

        return [
            'tipo'        => 'ensayo',
            'titulo'      => $this->tituloDeFamilia($familia),
            'columnas'    => $maqueta['columns'],
            // El rótulo de la columna del parámetro cambia por familia —GAS en
            // cromatografía, COMPUESTO en furanos, TAMAÑO DE PARTICULA en
            // partículas— y es texto, así que sale del archivo de idioma.
            'col3'        => $this->textoDeFamilia('legacy_col3', $familia, 'ENSAYO'),
            'anab'        => (bool) $maqueta['anab'],
            'pie_celda'   => (bool) $maqueta['cell_note'],
            'nota'        => isset($maqueta['footnote']) ? __($maqueta['footnote']) : null,
            'filas'       => $filas,
            'relaciones'  => $relaciones,
            'condiciones' => $condiciones,
        ];
    }

    /**
     * El título impreso de la hoja, en mayúsculas y como lo decía el papel viejo.
     *
     * Es TEXTO, así que vive en los archivos de idioma (`reports.family.*`) y no
     * en la configuración ni en una columna de la base: cambiarlo no necesita
     * migración ni seeder.
     */
    private function tituloDeFamilia(string $familia): string
    {
        return $this->textoDeFamilia(
            'family',
            $familia,
            mb_strtoupper(str_replace('_', ' ', $familia)),
        );
    }

    /**
     * Un texto de idioma por familia, con respaldo.
     *
     * El respaldo evita que agregar una prueba nueva al catálogo saque una hoja en
     * blanco o con el nombre de una clave de traducción impreso: sin entrada de
     * idioma se imprime algo legible y el papel sale.
     */
    private function textoDeFamilia(string $grupo, string $familia, string $respaldo): string
    {
        $clave = "reports.{$grupo}.{$familia}";

        return \Illuminate\Support\Facades\Lang::has($clave) ? __($clave) : $respaldo;
    }

    /**
     * Una hoja de ensayo cualquiera, armada desde la sección del payload nuevo.
     *
     * Sirve para las trece pruebas que el informe clásico NO imprimía: PCB,
     * furanos, partículas, azufre corrosivo, sedimentos, metales, viscosidad,
     * DBDS, punto de inflamación, punto de fluidez, inhibidor, grado de
     * polimerización y pasivador.
     *
     * Los valores salen del MISMO payload que alimenta el informe moderno, así que
     * los dos papeles no pueden decir números distintos — que es justamente lo que
     * pasaba en el sistema anterior, donde cada partial releía la base a su manera
     * y volvía a interpretar el límite al imprimir.
     *
     * @param  array<string,mixed>   $seccion
     * @param  array<string,string>  $condiciones
     * @return array<string,mixed>|null
     */
    private function paginaGenerica(string $familia, array $seccion, array $condiciones): ?array
    {
        // La técnica instrumental de la familia (HPLC, ICP-AES, GC-MS): es la
        // columna MÉTODO del papel viejo, distinta de la columna NORMA. Texto, o
        // sea archivo de idioma.
        $claveTecnica = "reports.legacy_technique.{$familia}";
        $tecnica = \Illuminate\Support\Facades\Lang::has($claveTecnica) ? __($claveTecnica) : '-';

        $filas = [];
        $item = 0;

        foreach ($seccion['rows'] ?? [] as $fila) {
            $item++;
            $filas[] = [
                'item'   => $item,
                'norma'  => $this->normaConFlag([
                    'label' => $fila['method'] ?? null,
                    'flag'  => $fila['accreditation'] ?? null,
                ]),
                'ensayo' => (string) ($fila['analyte'] ?? ''),
                'metodo' => $tecnica,
                'unidad' => (string) ($fila['unit'] ?? '-'),
                // El límite ya viene ARMADO desde el payload, con los mismos
                // números con los que se decidió el veredicto al validar la hoja.
                // Acá no se reinterpreta nada: es la diferencia central con el
                // informe viejo.
                'orientacion' => in_array($fila['limit'] ?? '—', ['—', '', null], true) ? '-' : (string) $fila['limit'],
                'resultado'   => (string) ($fila['value'] ?? '-'),
                'fuera'       => ($fila['status'] ?? null) === 'out_of_spec',
            ];
        }

        // La fecha de análisis de ESTA prueba, no la de la muestra: dos ensayos
        // de la misma muestra se corren días distintos.
        $fecha = $seccion['conditions']['run_date'] ?? null;

        return $this->hoja($familia, $filas, [
            'Fecha de Análisis' => $fecha
                ? \Illuminate\Support\Carbon::parse($fecha)->format('d-m-Y')
                : now()->format('d-m-Y'),
            'Temperatura Lab' => $condiciones['temp'],
            'Humedad Relativa Lab' => $condiciones['humedad'],
        ]);
    }

    /**
     * Norma del método por código de analito, sacada del payload del informe
     * nuevo: así los dos papeles citan exactamente la misma norma.
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

    /**
     * Una imagen del sistema viejo, resuelta a data-URI.
     *
     * DomPDF no sale a buscar archivos por la red, y el papel viejo llevaba
     * estos dos: el logotipo de la empresa y el sello del organismo que la
     * acredita. Si el archivo no está, se devuelve el rótulo entre corchetes
     * para que la hoja siga siendo legible y quede claro que falta.
     */
    /** Una imagen del workspace (disco público) como `<img>` embebido. */
    private function imagen(?string $ruta, int $ancho, int $alto): ?string
    {
        if (! $ruta) {
            return null;
        }

        $absoluta = \Illuminate\Support\Facades\Storage::disk('public')->path($ruta);

        if (! is_file($absoluta)) {
            return null;
        }

        $tipo = mime_content_type($absoluta) ?: 'image/png';

        return '<img src="data:' . $tipo . ';base64,'
            . base64_encode((string) file_get_contents($absoluta))
            . '" width="' . $ancho . '" height="' . $alto . '">';
    }

    private function imagenLegado(string $archivo, int $ancho, int $alto): string
    {
        $ruta = storage_path('app/legacy-assets/' . $archivo);

        if (! is_file($ruta)) {
            // No están versionados a propósito: este repositorio es público y
            // ni una marca registrada ni un sello de acreditación tienen por
            // qué estar en él. Se copian del sistema viejo:
            //   cp <labo_old>/app/assets/images/{hitachi_logo_new,anab_logo}.png \
            //      storage/app/legacy-assets/
            return '<span style="font-size:9px;color:#7a7a7a">[ falta ' . e($archivo)
                . ' — copiar de los assets del sistema viejo a storage/app/legacy-assets ]</span>';
        }

        $datos = base64_encode((string) file_get_contents($ruta));

        return '<img src="data:image/png;base64,' . $datos . '" width="' . $ancho . '" height="' . $alto . '">';
    }

    /**
     * Quiénes firman. El papel viejo imprimía UNA firma ("Reportado por"); el
     * laboratorio hoy tiene una lista con su relación y su cargo. Se imprimen
     * todos, con el mismo criterio de estampa que el informe moderno.
     *
     * @return array<int,array{relacion:string,nombre:string,cargo:?string,imagen:?string}>
     */
    private function firmantes(?int $tenantId): array
    {
        return Signature::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with('user:id,name,signature,auto_sign_reports')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn ($f) => [
                // La RELACIÓN (Aprobado por / Revisado por) y el CARGO son dos
                // cosas distintas: el papel viejo solo tenía "Reportado por".
                'relacion' => __('reports.relation.' . $f->relation),
                'nombre'   => $f->printedName(),
                'cargo'    => $f->title,
                'imagen'   => $this->firmaComoImagen($f),
            ])
            ->all();
    }

    /**
     * La firma escaneada, resuelta a data-URI.
     *
     * `imagePath()` devuelve la ruta RELATIVA al disco público, no una ruta del
     * sistema de archivos: preguntarle `is_file()` directamente da siempre
     * falso y la firma no se estampa nunca. Es el mismo camino que usa el
     * informe moderno.
     */
    private function firmaComoImagen(Signature $firma): ?string
    {
        $ruta = $firma->imagePath();

        if (! $ruta) {
            return null;
        }

        $absoluta = Storage::disk('public')->path($ruta);

        if (! is_file($absoluta)) {
            return null;
        }

        $tipo = mime_content_type($absoluta) ?: 'image/png';

        return 'data:' . $tipo . ';base64,' . base64_encode((string) file_get_contents($absoluta));
    }

    private function num(mixed $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 6, '.', ''), '0'), '.');
    }
}
