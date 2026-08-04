<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Sample;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Las etiquetas que se pegan al envase de la muestra.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ ES UN MENÚ APARTE, COMO EN EL SISTEMA ANTERIOR                           │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Antes esto vivía como dos botones dentro de la ficha de la entrega. Se sacó a
 * su propio menú porque así trabaja el laboratorio: quien imprime etiquetas va
 * a imprimir etiquetas y nada más — no está en medio de registrar una entrega.
 * En el sistema anterior era "Control de Stickers", con su entrada de menú, su
 * listado y su vista de impresión.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA ETIQUETA ES HTML, NO UN PDF, Y ESO ES A PROPÓSITO                     │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sistema anterior imprimía una tabla HTML con `window.print()`, y de ahí
 * salen las medidas con las que está calibrada la impresora de etiquetas del
 * laboratorio: el recuadro, el logo de 80×50 px, el QR y el desplazamiento de
 * 1 mm al imprimir. El pliego A4 que había acá —una grilla de N×M etiquetas
 * generada con dompdf— tenía su propia caja y sus propios márgenes, así que las
 * etiquetas no caían donde la impresora las espera. Se replica la maqueta
 * exacta del anterior.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LO QUE NO SE COPIA DEL ANTERIOR                                          │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Allá cada etiqueta era una FILA que se daba de alta en una tabla `stickers`,
 * con su número de muestra guardado aparte del de la muestra: dos números que
 * podían no coincidir, y un vínculo que se reconstruía partiendo la cadena con
 * `first(4)`/`last(4)`. Acá no hay nada que dar de alta — la etiqueta se arma
 * con los datos de la muestra que ya existe, y lo que queda registrado es la
 * CONSTANCIA de la impresión (quién, cuándo, cuáles), que es justo lo que allá
 * no quedaba.
 *
 * Y el QR del anterior llevaba `https://lab.softwarebu.com/...` escrito en la
 * vista: una etiqueta impresa desde pruebas mandaba a producción, y mudar el
 * dominio habría dejado inservible todo envase ya rotulado. Acá sale de
 * `route()`.
 */
class SampleLabelController extends Controller
{
    /**
     * El listado: qué muestras hay para etiquetar.
     *
     * De la más nueva hacia atrás — es lo que se está rotulando ahora. El
     * buscador va contra el Nº de muestra, que es lo que dice el envase que se
     * tiene en la mano.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $buscado = trim((string) $request->get('search', ''));
        $desde   = $request->get('from');
        $hasta   = $request->get('to');

        $muestras = Sample::query()
            ->with([
                'reception:id,slug,code,customer_id,received_at',
                'reception.customer:id,name',
                'equipment:id,name,tag',
            ])
            ->when($buscado !== '', fn ($q) => $q->where('code', 'like', "%{$buscado}%"))
            ->when($desde, fn ($q) => $q->whereHas('reception', fn ($r) => $r->whereDate('received_at', '>=', $desde)))
            ->when($hasta, fn ($q) => $q->whereHas('reception', fn ($r) => $r->whereDate('received_at', '<=', $hasta)))
            ->orderByDesc('year')
            ->orderByDesc('number')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('SampleLabels/Index', [
            'samples' => $muestras,
            'filters' => [
                'search'   => $buscado,
                'from'     => $desde,
                'to'       => $hasta,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * La hoja imprimible, con la maqueta EXACTA del sistema anterior.
     *
     * Acepta una muestra o varias: una etiqueta por muestra, cada una en su
     * página. Reponer una despegada y rotular una entrega entera son la misma
     * pantalla — la única diferencia es cuántas se eligieron.
     */
    public function print(Request $request)
    {
        $datos = $request->validate([
            'codes'   => ['required', 'array', 'min:1', 'max:200'],
            'codes.*' => ['string', 'max:60'],
            // El comentario del sistema anterior: una línea suelta que el
            // laboratorio a veces escribe en la etiqueta ("recontramuestra",
            // "urgente"). Allá era un campo de la fila `stickers`; acá va en la
            // tanda que se está imprimiendo, que es como se usa.
            'comment' => ['nullable', 'string', 'max:120'],
        ]);

        // La consulta pasa por el ámbito de workspace del modelo: un código de
        // otra empresa simplemente no aparece.
        $muestras = Sample::query()
            ->whereIn('code', $datos['codes'])
            ->with('reception:id,slug,code,received_at')
            ->orderBy('year')
            ->orderBy('number')
            ->get();

        abort_if($muestras->isEmpty(), 404);

        $etiquetas = $muestras->map(fn (Sample $m) => [
            'code' => $m->code,
            // La FECHA DE LA ENTREGA, en el formato del sistema anterior
            // (`str_date_test_small`: d-m-Y). Es la que se busca en el envase
            // para saber qué antigüedad tiene la muestra.
            'date' => $m->reception?->received_at?->format('d-m-Y') ?? '',
            'qr'   => $this->qr($m),
        ])->all();

        // La constancia de la impresión, que la tabla `stickers` del anterior NO
        // guardaba: allá quedaba el alta de la etiqueta, pero no quién la sacó
        // ni cuántas veces. Un envase reetiquetado a mano era indistinguible de
        // uno etiquetado por el sistema.
        AuditLog::create([
            'user_id'        => $request->user()?->id,
            'event'          => 'labels_printed',
            'auditable_type' => Sample::class,
            'auditable_id'   => $muestras->count() === 1 ? $muestras->first()->id : null,
            'module'         => 'sample_labels',
            'old_values'     => null,
            'new_values'     => [
                'count'   => count($etiquetas),
                'samples' => $muestras->pluck('code')->all(),
            ],
            'url'        => route('lab_management.sample_labels.index'),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);

        return response()->view('lab_management.labels.sticker', [
            'labels'  => $etiquetas,
            'comment' => $datos['comment'] ?? null,
            'logo'    => $this->logo(),
        ]);
    }

    /**
     * El QR abre la entrega con la muestra resaltada.
     *
     * Un envase escaneado en la bancada tiene que abrir SU fila, no una lista
     * de veinte donde hay que buscarla a ojo.
     *
     * El `module_size: 2` del anterior daba un QR de unos 120 px de lado para
     * una dirección de este largo. Es el tamaño con el que la etiqueta está
     * calibrada y el que el lector del laboratorio ya lee.
     */
    private function qr(Sample $sample): string
    {
        $url = $sample->reception
            ? route('lab_management.receptions.show', [$sample->reception->slug, 'sample' => $sample->code])
            : url('/');

        $qr  = new \Endroid\QrCode\QrCode($url, size: 120, margin: 0);
        $png = (new \Endroid\QrCode\Writer\PngWriter())->write($qr);

        return $png->getDataUri();
    }

    /**
     * El logo de la etiqueta: el del workspace, y si no tiene, el del legado.
     *
     * El del legado no está versionado a propósito —este repositorio es público
     * y una marca registrada no tiene por qué estar en él—; se copia a mano a
     * `storage/app/legacy-assets`. Sin ninguno de los dos la etiqueta sale sin
     * imagen: el Nº de muestra y el QR, que es lo que la hace útil, siguen ahí.
     */
    private function logo(): ?string
    {
        $delWorkspace = auth()->user()?->tenant?->logo;

        if ($delWorkspace) {
            $absoluta = Storage::disk('public')->path($delWorkspace);

            if (is_file($absoluta)) {
                return 'data:image/' . pathinfo($absoluta, PATHINFO_EXTENSION)
                    . ';base64,' . base64_encode((string) file_get_contents($absoluta));
            }
        }

        $legado = storage_path('app/legacy-assets/hitachi_logo_new.png');

        return is_file($legado)
            ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($legado))
            : null;
    }
}
