{{--
    Pliego de etiquetas para los envases de muestra.

    Se imprime en A4 con la grilla que dicten los ajustes (`labels.columns`,
    `labels.rows`, `labels.margin_mm`); el tamaño de cada etiqueta ya viene
    calculado desde el controlador para que la grilla entre siempre en la hoja.

    OJO dompdf: la etiqueta se arma con TABLAS y no con flex/grid — dompdf no
    implementa ninguno de los dos, y con `display:flex` el contenido sale
    apilado en una columna sin avisar. Y las medidas van en mm porque lo que
    importa acá es el papel: un `px` cambia de tamaño con el DPI del driver.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('labels.sheet_title') }}</title>
    <style>
        @page { margin: {{ $margin }}mm; }
        body  { margin: 0; font-family: Helvetica, Arial, sans-serif; color: #1a1a1a; }

        table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.grid td {
            width: {{ $width }}mm;
            height: {{ $height }}mm;
            padding: 0;
            vertical-align: top;
        }

        /* El recuadro punteado es la GUÍA DE CORTE, no un adorno: en hoja lisa
           marca dónde va la tijera. En hoja precortada queda por debajo del
           troquel y no molesta. */
        /* La altura va en mm y NO en `height:100%`. Con el porcentaje, dompdf
           no resuelve el alto contra la celda que lo contiene, se lo lleva a la
           altura de la página y mete DOS HOJAS EN BLANCO delante del pliego
           (verificado: la misma maqueta con 34.62mm da 1 página y con 100% da 3). */
        .lbl {
            width: 100%;
            height: {{ $height }}mm;
            border: 0.4mm dashed #b8b8b8;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .lbl td { padding: 1.2mm 1.6mm; vertical-align: top; }

        .lab   { font-size: 6.5pt; color: #55606b; text-transform: uppercase; letter-spacing: 0.2mm; }
        .lab img { max-height: 6mm; max-width: 26mm; }

        /* El número de muestra es lo ÚNICO que se lee de lejos en una gradilla
           con veinte envases. Todo lo demás es confirmación. */
        .code  { font-size: 15pt; font-weight: bold; letter-spacing: 0.2mm; }
        .urg   { font-size: 6.5pt; font-weight: bold; color: #C8281D; }

        .meta  { font-size: 6.5pt; line-height: 1.35; color: #33383d; }
        .meta b { font-weight: normal; color: #6b7680; }

        .qr    { width: 15mm; text-align: right; }
        .qr img { width: 14mm; height: 14mm; }
    </style>
</head>
<body>
@foreach (array_chunk($labels, $perPage) as $pagina)
    @if (! $loop->first)
        <div style="page-break-before: always;"></div>
    @endif

    <table class="grid">
        @foreach (array_chunk($pagina, $columns) as $fila)
            <tr>
                @foreach ($fila as $l)
                    <td>
                        <table class="lbl">
                            <tr>
                                <td>
                                    <div class="lab">
                                        @if ($logo)
                                            <img src="{{ $logo }}" alt="">
                                        @else
                                            {{ $lab }}
                                        @endif
                                    </div>
                                    <div class="code">{{ $l['code'] }}</div>
                                    @if ($l['urgent'])
                                        <div class="urg">{{ __('labels.urgent') }}</div>
                                    @endif
                                    <div class="meta">
                                        @if ($l['customer'])
                                            <div>{{ $l['customer'] }}</div>
                                        @endif
                                        @if ($l['equipment'])
                                            <div><b>{{ __('labels.equipment') }}:</b> {{ $l['equipment'] }}</div>
                                        @endif
                                        @if ($l['oil'])
                                            <div><b>{{ __('labels.oil') }}:</b> {{ $l['oil'] }}</div>
                                        @endif
                                        <div>
                                            <b>{{ __('labels.sampled') }}:</b> {{ $l['sampled'] ?? '—' }}
                                            &nbsp;<b>{{ __('labels.received') }}:</b> {{ $l['received'] ?? '—' }}
                                        </div>
                                    </div>
                                </td>
                                @if ($l['qr'])
                                    <td class="qr"><img src="{{ $l['qr'] }}" alt=""></td>
                                @endif
                            </tr>
                        </table>
                    </td>
                @endforeach

                {{-- Celdas de relleno de la última fila: sin ellas dompdf
                     reparte el ancho sobrante entre las etiquetas que sí hay y
                     la última fila sale más ancha que las de arriba, justo en
                     el pliego que después se corta a mano. --}}
                @for ($i = count($fila); $i < $columns; $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
    </table>
@endforeach
</body>
</html>
