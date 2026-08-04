<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('labels.title') }}</title>
    <style>
        /*
        | LAS MEDIDAS SON LAS DEL SISTEMA ANTERIOR, AL PIXEL
        |
        | La impresora de etiquetas del laboratorio está calibrada contra esta
        | maqueta: el recuadro con esquinas redondeadas, las celdas de
        | .5em 1em, el logo de 80x50 y el desplazamiento de 1 mm al imprimir.
        | Cualquier cambio de estos números mueve dónde cae la etiqueta en el
        | rollo. No se "mejoran" sin volver a calibrar la impresora.
        |
        | Origen: labo_old, app/views/im_management/stickers/partials/
        |         _form_show.html.erb
        */
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 12px;
            background: #f4f5f7;
            font-family: Arial, Helvetica, sans-serif;
        }

        table, tr, th, td {
            border-collapse: collapse;
            border: 1px solid;
        }

        table {
            border-radius: 1em;
            outline: 1px solid;
            outline-offset: -1px;
            overflow: hidden;
            background: #fff;
        }

        th, td {
            padding: .5em 1em;
            vertical-align: middle;
        }

        /* El sistema anterior usaba <h5> en cada celda: mismo tamaño y peso,
           sin el margen inferior que arrastraba de Bootstrap. */
        td .h5 {
            font-size: 1.25rem;
            font-weight: 500;
            line-height: 1.2;
            margin: 0;
        }

        .sticker { page-break-after: always; margin-bottom: 14px; }
        .sticker:last-child { page-break-after: auto; margin-bottom: 0; }

        .toolbar {
            margin: 0 0 14px 0;
            font-family: Arial, Helvetica, sans-serif;
        }
        .toolbar button {
            font-size: 15px;
            padding: 8px 18px;
            cursor: pointer;
            border: 1px solid #0A6ED1;
            background: #0A6ED1;
            color: #fff;
            border-radius: 4px;
        }
        .toolbar span { margin-left: 10px; color: #6A6D70; font-size: 13px; }

        @media print {
            /* El botón no se imprime; la etiqueta se corre 1 mm, igual que en
               el sistema anterior. */
            .no-printme { display: none; }
            body { background: #fff; padding: 0; }
            .printme {
                display: block;
                margin-left: 1mm;
                margin-top: 1mm;
            }
            .sticker { margin-bottom: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-printme">
        <button type="button" onclick="window.print()">{{ __('labels.print') }}</button>
        <span>{{ trans_choice('labels.print_count', count($labels), ['count' => count($labels)]) }}</span>
    </div>

    <div class="printme">
        @foreach ($labels as $label)
            <div class="sticker">
                <table border="1">
                    <tr>
                        <td><div class="h5">{{ __('labels.sample_no') }}</div></td>
                        <td><div class="h5">{{ $label['code'] }}</div></td>
                    </tr>
                    <tr>
                        <td><div class="h5">{{ __('labels.date') }}</div></td>
                        <td><div class="h5">{{ $label['date'] }}</div></td>
                    </tr>
                    {{-- La fila del comentario solo existe si hay comentario,
                         igual que en el anterior: una fila vacía cambiaría el
                         alto de la etiqueta y la sacaría de registro. --}}
                    @if (filled($comment))
                        <tr>
                            <td><div class="h5">{{ __('labels.comment') }}</div></td>
                            <td><div class="h5">{{ $comment }}</div></td>
                        </tr>
                    @endif
                    <tr>
                        <td>
                            @if ($logo)
                                <img src="{{ $logo }}" width="80" height="50" alt="">
                            @endif
                        </td>
                        <td><img src="{{ $label['qr'] }}" alt="{{ $label['code'] }}"></td>
                    </tr>
                </table>
            </div>
        @endforeach
    </div>
</body>
</html>
