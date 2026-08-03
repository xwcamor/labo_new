<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Reception;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * La etiqueta que se pega al envase de la muestra.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ NO ES UN MÓDULO CON SU CRUD                                      │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema anterior era uno ("Control de Stickers"): una tabla `stickers`
 * donde se DABA DE ALTA una etiqueta escribiendo a mano el número de muestra
 * como texto libre, sin relación con la muestra —el vínculo se reconstruía
 * partiendo la cadena con `first(4)`/`last(4)`— y sin registro de quién
 * imprimió. O sea: un segundo lugar donde tipear un número que el sistema ya
 * tenía, con su propia forma de quedar desincronizado.
 *
 * Acá imprimir es una ACCIÓN sobre muestras que ya existen. No hay nada que
 * dar de alta: la etiqueta se arma con los datos de la muestra en el momento
 * de imprimir, y lo que queda guardado es la CONSTANCIA de la impresión en el
 * registro de auditoría (quién, cuándo, cuántas, cuáles).
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL QR NO LLEVA UN DOMINIO ESCRITO EN EL CÓDIGO                           │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El del sistema anterior codificaba `https://lab.softwarebu.com/...` quemado
 * en la vista: una etiqueta impresa desde el entorno de pruebas mandaba a
 * producción, y mudar el dominio habría dejado inservible todo envase con
 * etiqueta pegada. Acá la URL sale de `route()`, que se arma con APP_URL.
 */
class SampleLabelController extends Controller
{
    /** A4 en milímetros. El pliego de etiquetas se calcula sobre esto. */
    private const PAGE_W = 210.0;
    private const PAGE_H = 297.0;

    /**
     * Lo que dompdf agrega a cada etiqueta POR ENCIMA del alto declarado: el
     * recuadro de corte queda FUERA de la caja, no dentro. Sin descontarlo, la
     * última fila de un pliego 3x8 se iba a una segunda hoja —24 etiquetas
     * pedidas, 21 impresas y tres sueltas en una hoja aparte—. Es un milímetro
     * medido, no estimado: con el alto ideal el pliego se desborda y con un
     * milímetro menos entra, en las tres grillas probadas (3x8, 3x7 y 2x5).
     */
    private const BORDER_MM = 1.0;

    /**
     * El pliego de etiquetas de una entrega.
     *
     * Sin `samples` en la consulta salen TODAS las muestras de la entrega, que
     * es el caso normal: se imprime el pliego una vez, al confirmar los
     * correlativos y antes de repartir los envases a los ensayos.
     */
    public function sheet(Request $request, Reception $reception)
    {
        // Una entrega en borrador todavía no tiene números de muestra: no hay
        // nada que pegar en un envase.
        abort_if($reception->isDraft(), 404);

        $datos = $request->validate([
            'samples'   => ['sometimes', 'array'],
            'samples.*' => ['integer'],
        ]);

        $muestras = $reception->samples()
            ->with(['equipment:id,name,serial,tag', 'oilType:id,name'])
            ->when(
                ! empty($datos['samples']),
                // El filtro se aplica SOBRE las muestras de esta entrega: un id
                // de otra entrega no imprime su etiqueta acá.
                fn ($q) => $q->whereIn('id', $datos['samples'])
            )
            ->orderBy('number')
            ->get();

        abort_if($muestras->isEmpty(), 404);

        $reception->loadMissing(['customer:id,name', 'tenant:id,name,logo']);

        $columnas = max(1, (int) Setting::get('labels.columns', 3));
        $filas    = max(1, (int) Setting::get('labels.rows', 8));
        $margen   = max(0, (float) Setting::get('labels.margin_mm', 6));
        $conQr    = Setting::getBool('labels.show_qr', true);

        // El tamaño de la etiqueta se DERIVA de la grilla y del margen en vez de
        // configurarse aparte. Con ancho y alto propios, cualquiera podía dejar
        // "3 columnas de 90 mm" —270 mm en una hoja de 210— y el pliego salía
        // cortado sin decir nada. Derivándolo, la grilla siempre entra.
        $ancho = (self::PAGE_W - 2 * $margen) / $columnas - self::BORDER_MM;
        $alto  = (self::PAGE_H - 2 * $margen) / $filas - self::BORDER_MM;

        // Una grilla que no da el alto MÍNIMO del contenido de la etiqueta se
        // rechaza en vez de imprimirse mal: por debajo de eso, dompdf empuja
        // las últimas filas a otra hoja y salen 21 etiquetas de las 24 pedidas
        // más tres sueltas. El piso está medido —con 24.91 mm entra y con 23.08
        // no— y es lo que ocupan las seis líneas de la etiqueta.
        abort_if($ancho < 30 || $alto < 25, 422);

        $etiquetas = $muestras->map(fn ($muestra) => [
            'code'      => $muestra->code,
            'customer'  => $reception->customer?->name,
            'equipment' => $muestra->equipment?->tag
                ?: $muestra->equipment?->serial
                ?: $muestra->equipment?->name,
            'oil'       => $muestra->oilType?->name,
            'sampled'   => $muestra->sampled_at?->format('d-m-Y'),
            'received'  => $reception->received_at?->format('d-m-Y'),
            'urgent'    => (bool) $muestra->is_urgent,
            'qr'        => $conQr ? $this->qr($reception, $muestra->code) : null,
        ])->all();

        // La constancia de la impresión, que es lo que la tabla `stickers` del
        // sistema anterior NO guardaba: allá quedaba el alta de la etiqueta,
        // pero no quién la sacó ni cuántas veces. Un envase reetiquetado a mano
        // era indistinguible de uno etiquetado por el sistema.
        AuditLog::create([
            'user_id'        => $request->user()?->id,
            'auditable_type' => Reception::class,
            'auditable_id'   => $reception->id,
            'event'          => 'labels_printed',
            'new_values'     => [
                'count'   => count($etiquetas),
                'samples' => $muestras->pluck('code')->all(),
                'grid'    => $columnas . 'x' . $filas,
            ],
            'url'        => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'module'     => 'receptions',
        ]);

        $pdf = Pdf::loadView('lab_management/labels/sheet', [
            'labels'   => $etiquetas,
            'lab'      => $reception->tenant?->name,
            'logo'     => $this->logo($reception->tenant?->logo),
            'width'    => round($ancho, 2),
            'height'   => round($alto, 2),
            'margin'   => $margen,
            'columns'  => $columnas,
            'perPage'  => $columnas * $filas,
        ])->setPaper('a4');

        return $pdf->stream('etiquetas-' . $reception->code . '.pdf');
    }

    /**
     * El QR apunta a la entrega, con la muestra en la consulta para que la
     * ficha la resalte. Un envase escaneado en la bancada tiene que abrir SU
     * fila, no una lista de veinte donde hay que buscarla a ojo.
     */
    private function qr(Reception $reception, string $code): string
    {
        $url = route('lab_management.receptions.show', [$reception->slug, 'sample' => $code]);

        $qr  = new \Endroid\QrCode\QrCode($url, size: 220, margin: 0);
        $png = (new \Endroid\QrCode\Writer\PngWriter())->write($qr);

        return $png->getDataUri();
    }

    /** El logo del laboratorio como data-URI: dompdf no sale a la red. */
    private function logo(?string $ruta): ?string
    {
        if (! $ruta) {
            return null;
        }

        $absoluta = Storage::disk('public')->path($ruta);

        if (! is_file($absoluta)) {
            return null;
        }

        return 'data:image/' . pathinfo($absoluta, PATHINFO_EXTENSION)
            . ';base64,' . base64_encode((string) file_get_contents($absoluta));
    }
}
