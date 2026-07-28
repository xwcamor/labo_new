<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\InstrumentFile;
use App\Models\InstrumentFormat;
use App\Models\Worksheet;
use App\Services\Lab\InstrumentFileParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * "Lectura de Archivo TXT": subir el archivo crudo del instrumento y precargar
 * los valores en la hoja de trabajo.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ CAMBIA RESPECTO DEL SISTEMA VIEJO                                    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * 1. El archivo se GUARDA como archivo, con su huella. Allá se leía entero con
 *    `File.read` y se volcaba dentro de una columna de texto de la base, y todo
 *    el análisis posterior trabajaba sobre esa cadena.
 * 2. El archivo subido no se aceptaba con ninguna validación: el filtro de
 *    extensiones del sistema viejo llamaba a un método de una versión anterior
 *    de la biblioteca, así que era código muerto y entraba cualquier cosa.
 * 3. El registro nacía con la marca de borrado en verdadero y se "activaba" con
 *    un campo oculto del formulario de confirmación. Acá hay un estado
 *    explícito: subido, interpretado, fallido.
 * 4. Lo interpretado se DEVUELVE para que el analista lo confirme; no se
 *    escribe solo en la hoja. Eso el sistema viejo lo hacía bien y se conserva.
 *
 * El análisis en sí vive en App\Services\Lab\InstrumentFileParser, que documenta
 * los cinco defectos del parser anterior.
 */
class InstrumentFileController extends Controller
{
    public function __construct(private readonly InstrumentFileParser $parser)
    {
    }

    /**
     * Sube el archivo, lo interpreta y devuelve los valores encontrados.
     *
     * No escribe nada en la hoja: el analista revisa lo que salió y decide. Un
     * instrumento puede haber medido mal y el analista es quien lo sabe.
     */
    public function store(Request $request, Worksheet $worksheet): JsonResponse
    {
        $data = $request->validate([
            // El sistema viejo declaraba `accept=".txt"` SOLO en el HTML y su
            // validación del lado del servidor era código muerto.
            'file' => ['required', 'file', 'max:5120', 'mimetypes:text/plain,text/csv,application/csv'],
            'instrument_format_id' => [
                'required', 'integer',
                Rule::exists('instrument_formats', 'id')->where('is_active', true),
            ],
        ]);

        if (! $worksheet->isEditable()) {
            return response()->json([
                'message' => __('worksheets.errors.not_draft'),
            ], 422);
        }

        $format = InstrumentFormat::findOrFail($data['instrument_format_id']);
        $upload = $request->file('file');

        $path = $upload->store("instrument-files/{$worksheet->id}");

        $record = InstrumentFile::create([
            'slug'                 => Str::random(22),
            'worksheet_id'         => $worksheet->id,
            'original_name'        => $upload->getClientOriginalName(),
            'path'                 => $path,
            'mime'                 => $upload->getClientMimeType(),
            'size'                 => $upload->getSize(),
            // La huella permite reconocer que el mismo archivo se subió dos
            // veces, que en la bancada pasa cuando se repite una corrida.
            'sha256'               => hash_file('sha256', $upload->getRealPath()),
            'instrument_format_id' => $format->id,
            'created_by'           => $request->user()?->id,
        ]);

        $contents = $this->readAsUtf8(Storage::path($path), $format->encoding ?? 'UTF-8');
        $result = $this->parser->parse($contents, $format->column_map ?? []);

        $record->update([
            'status'      => $result['values'] === [] ? 'failed' : 'parsed',
            'rows_parsed' => count($result['values']),
            'parse_error' => $result['values'] === []
                ? __('instrument_files.errors.nothing_matched')
                : null,
        ]);

        return response()->json([
            'file'      => $record->only(['id', 'slug', 'original_name', 'status', 'rows_parsed']),
            'values'    => $result['values'],
            'unmatched' => $result['unmatched'],
        ]);
    }

    /**
     * Normaliza a UTF-8 y quita la marca de orden de bytes.
     *
     * Hace falta: uno de los archivos de cromatografía la trae, y en el sistema
     * viejo entraba a la columna de texto y arruinaba la coincidencia de la
     * primera línea.
     */
    private function readAsUtf8(string $path, string $encoding): string
    {
        $raw = (string) file_get_contents($path);

        if (strtoupper($encoding) !== 'UTF-8') {
            $converted = @iconv($encoding, 'UTF-8//TRANSLIT', $raw);
            $raw = $converted === false ? $raw : $converted;
        }

        return $raw;
    }
}
