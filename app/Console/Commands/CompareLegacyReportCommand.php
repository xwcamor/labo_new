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
            $this->error('No se encontró ninguna muestra con resultados. Ejecute `php artisan setup:project` primero.');
            return self::FAILURE;
        }

        // El autodiagnóstico se compone al crear el informe desde la pantalla,
        // no al imprimirlo. Sin esta llamada la comparación salía con la página
        // de análisis en "sin análisis cargado" para todas las familias, y se
        // leía como si el motor de diagnóstico no existiera: lo que faltaba era
        // el paso que da la pantalla, no el texto.
        app(\App\Services\Lab\DiagnosisTextService::class)->generate($muestra);

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
            // Los MISMOS firmantes que el papel viejo: comparar uno firmado
            // contra otro sin firmar escondería justamente lo que cambió.
            'signers'     => $this->firmantesModelo(),
        ])->setPaper('a4');

        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $fuente = $dompdf->getFontMetrics()->getFont('Helvetica', 'normal');
        // Misma altura que en el controlador (827): a 812 el número caía dentro
        // del descargo legal y se leía pisado.
        $dompdf->getCanvas()->page_text(
            455.0, 827.0, __('reports.page_of', ['num' => '{PAGE_NUM}', 'total' => '{PAGE_COUNT}']),
            $fuente, 6.5, [0.33, 0.33, 0.33],
        );

        return $pdf->output();
    }

    /**
     * Los firmantes como MODELO, que es lo que espera el blade del informe
     * moderno (el clásico los recibe ya aplanados desde su propio servicio).
     *
     * Van los mismos en los dos papeles: comparar uno firmado contra otro sin
     * firmar escondería justamente lo que cambió.
     */
    private function firmantesModelo(): \Illuminate\Support\Collection
    {
        $tenantId = \App\Models\Tenant::query()->orderBy('id')->value('id');

        return \App\Models\Signature::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with('user:id,name,signature,auto_sign_reports')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(function ($firma) {
                $firma->stamp = $this->firmaComoImagen($firma);

                return $firma;
            });
    }

    /**
     * La firma escaneada, resuelta a data-URI.
     *
     * `imagePath()` devuelve la ruta RELATIVA al disco público, no una ruta del
     * sistema de archivos: preguntarle `is_file()` directamente da siempre
     * falso y la firma no se estampa nunca.
     */
    private function firmaComoImagen(\App\Models\Signature $firma): ?string
    {
        $ruta = $firma->imagePath();

        if (! $ruta) {
            return null;
        }

        $absoluta = \Illuminate\Support\Facades\Storage::disk('public')->path($ruta);

        if (! is_file($absoluta)) {
            return null;
        }

        $tipo = mime_content_type($absoluta) ?: 'image/png';

        return 'data:' . $tipo . ';base64,' . base64_encode((string) file_get_contents($absoluta));
    }

    // ── El informe de antes ──────────────────────────────────────────────

    /**
     * Delegado en `LegacyReportRenderer`: la maqueta clásica dejó de ser solo
     * documentación —ahora es una opción real del botón Exportar— y el dibujo
     * vive en el servicio. Este comando solo la invoca para la comparación.
     */
    private function renderViejo(Sample $muestra, array $datos): string
    {
        return app(\App\Services\Lab\LegacyReportRenderer::class)->render($muestra, $datos);
    }
}
