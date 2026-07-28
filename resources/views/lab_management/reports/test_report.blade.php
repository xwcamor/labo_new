{{--
    Informe de ensayo de una muestra.

    Reglas de DomPDF que hay que respetar (mismas que el resto de los PDF del
    proyecto): solo "Helvetica", `font-weight` únicamente normal o bold, y nada
    de `position: fixed` con offsets negativos.

    El informe anterior repetía la cabecera completa —logo, datos del cliente,
    datos del equipo— dentro de CADA sección, porque cada prueba era una página
    suelta renderizada por su propio parcial. Acá la cabecera se arma una vez y
    lo que se repite es solo el encabezado corto de la tabla, que es lo que hace
    falta cuando una sección parte de página.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('reports.title') }} {{ $sample['code'] }}</title>
    <style>
        @page { margin: 26px 30px 40px 30px; }

        body { font-family: Helvetica; font-size: 8.5pt; color: #32363A; margin: 0; }

        .band { background: #354A5F; color: #ffffff; padding: 12px 16px; margin-bottom: 12px; }
        .band__title { font-size: 13pt; font-weight: bold; margin: 0; }
        .band__sub { font-size: 8pt; color: #cbd5e1; margin: 3px 0 0 0; }
        .band__meta { float: right; text-align: right; font-size: 8pt; color: #cbd5e1; line-height: 1.5; }
        .band__meta strong { color: #ffffff; }

        .cards { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .cards td { vertical-align: top; width: 50%; padding: 0 6px 0 0; }
        .cards td + td { padding: 0 0 0 6px; }

        .card { border: 1px solid #d9dde3; }
        .card__head {
            background: #F2F5F8; padding: 5px 9px; font-weight: bold;
            font-size: 8pt; border-bottom: 1px solid #d9dde3;
        }
        .card__body { padding: 6px 9px; }
        .kv { width: 100%; border-collapse: collapse; }
        .kv td { padding: 2px 0; font-size: 8pt; }
        .kv td:first-child { color: #6A6D70; width: 40%; }

        .section { margin-top: 10px; }
        .section__title {
            background: #E8ECF1; border: 1px solid #d9dde3; border-bottom: 0;
            padding: 5px 9px; font-weight: bold; font-size: 9pt;
        }
        /* La norma del MÉTODO, con su marca de acreditación. Va en el
           encabezado de la sección y no repetida en cada fila: es la misma para
           todo el ensayo, y repetirla catorce veces en cromatografía comía la
           columna del parámetro. */
        .section__method { font-weight: normal; font-size: 8pt; color: #4A5568; }
        .section__method sup { font-size: 6.5pt; }
        /* De dónde salió el límite. Debajo del número, chico: el cliente lee el
           número; el auditor necesita saber contra qué cuadro se juzgó. */
        .criterion { font-size: 6.5pt; color: #6A6D70; }

        table.results { width: 100%; border-collapse: collapse; }
        table.results th {
            background: #F2F5F8; border: 1px solid #d9dde3;
            padding: 4px 6px; font-size: 7.5pt; text-transform: uppercase;
        }
        table.results td { border: 1px solid #d9dde3; padding: 4px 6px; }
        .num { text-align: center; }
        .result { text-align: center; font-weight: bold; background: #F7F8FA; }

        /* Fuera de norma: rojo Y la palabra. El color solo no sobrevive a una
           fotocopia en blanco y negro, y este papel se fotocopia. */
        .out { color: #C8281D; }
        .out__flag { font-size: 7pt; }
        /* Sin criterio: ni verde ni rojo. No se comparó contra nada. */
        .none { color: #6A6D70; }

        .notes { margin-top: 12px; border-left: 3px solid #E9A23B; background: #FDF6EC; padding: 7px 10px; font-size: 7.5pt; }
        .notes p { margin: 0 0 3px 0; }

        .letterhead { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .letterhead__logo { width: 45%; vertical-align: middle; }
        .letterhead__logo img { max-height: 46px; }
        .letterhead__name { font-size: 12pt; font-weight: bold; color: #354A5F; }
        .letterhead__addr { text-align: right; font-size: 7.5pt; color: #6A6D70; vertical-align: middle; }

        .signatures { width: 100%; border-collapse: collapse; margin-top: 26px; }
        .signatures td { vertical-align: top; padding: 0 8px; text-align: center; }
        .sign__line { border-top: 1px solid #32363A; margin-bottom: 4px; }
        .sign__rel { font-size: 7pt; color: #6A6D70; text-transform: uppercase; }
        .sign__name { font-size: 8.5pt; font-weight: bold; }
        .sign__title { font-size: 7.5pt; color: #6A6D70; }
        .qr { width: 90px; }
        .qr img { width: 74px; height: 74px; }
        .qr__hint { font-size: 6pt; color: #6A6D70; line-height: 1.3; }

        .foot { margin-top: 16px; border-top: 1px solid #d9dde3; padding-top: 6px; font-size: 7pt; color: #6A6D70; }
        .empty { border: 1px solid #d9dde3; padding: 14px; text-align: center; color: #6A6D70; }

        /* Numeración de página.
           DomPDF resuelve `counter(page)` / `counter(pages)` dentro de `content`
           SIN necesidad de habilitar PHP embebido en el HTML (que es una puerta
           que no vale la pena abrir para poner un número). Va en el margen
           inferior de `@page` —por eso el margen de abajo es más grande que el
           de arriba—, así ninguna fila de tabla se le monta encima. */
        .pagenum {
            position: fixed; bottom: -26px; left: 0; right: 0;
            text-align: center; font-size: 7pt; color: #6A6D70;
        }
        .pagenum:after { content: counter(page) " / " counter(pages); }
        .pagenum__code {
            position: fixed; bottom: -26px; right: 0;
            font-size: 7pt; color: #6A6D70;
        }
    </style>
</head>
<body>

{{-- El número de página y el código de verificación se repiten en TODAS las
     páginas: una hoja suelta de un informe de seis páginas tiene que poder
     identificarse sola. --}}
<div class="pagenum"></div>
<div class="pagenum__code">{{ $verifyCode }}</div>

@php
    // OJO: no escribir las directivas de bloque de PHP dentro de un comentario
    // acá adentro. El compilador de Blade las busca con una expresión regular
    // que no distingue código de comentario, así que la primera que aparezca
    // —aunque esté dentro de un //— cierra el bloque antes de tiempo y las
    // closures de abajo quedan sin definir. Se cayó así, escribiendo esta misma
    // advertencia.
    $fecha = fn ($f) => $f ? \Illuminate\Support\Carbon::parse($f)->format('d-m-Y') : '—';
    $o     = fn ($v) => ($v === null || $v === '') ? '—' : $v;
@endphp

<table class="letterhead">
    <tr>
        <td class="letterhead__logo">
            @if ($letterhead['logo'])
                <img src="{{ $letterhead['logo'] }}" alt="">
            @else
                <span class="letterhead__name">{{ $letterhead['name'] ?? '' }}</span>
            @endif
        </td>
        <td class="letterhead__addr">
            @if ($letterhead['logo'] && $letterhead['name'])
                <strong>{{ $letterhead['name'] }}</strong><br>
            @endif
            {{ $letterhead['address'] }}
        </td>
    </tr>
</table>

<div class="band">
    <div class="band__meta">
        <strong>{{ $sample['code'] }}</strong><br>
        {{ __('reports.emitted') }} {{ $generatedAt->format('d-m-Y H:i') }}<br>
        {{ __('reports.verify_code') }} {{ $verifyCode }}
    </div>
    <p class="band__title">{{ __('reports.title') }}</p>
    <p class="band__sub">{{ __('reports.subtitle') }}</p>
</div>

<table class="cards">
    <tr>
        <td>
            <div class="card">
                <div class="card__head">{{ __('reports.customer') }}</div>
                <div class="card__body">
                    <table class="kv">
                        <tr><td>{{ __('reports.customer_name') }}</td><td><strong>{{ $o($customer['name']) }}</strong></td></tr>
                        <tr><td>{{ __('reports.service_order') }}</td><td>{{ $o($sample['service_order']) }}</td></tr>
                        <tr><td>{{ __('reports.sampled_at') }}</td><td>{{ $fecha($sample['sampled_at']) }}</td></tr>
                        <tr><td>{{ __('reports.received_at') }}</td><td>{{ $fecha($sample['received_at']) }}</td></tr>
                        <tr><td>{{ __('reports.sampler') }}</td><td>{{ $o($sample['sampler']) }}</td></tr>
                    </table>
                </div>
            </div>
        </td>
        <td>
            <div class="card">
                <div class="card__head">{{ __('reports.equipment') }}</div>
                <div class="card__body">
                    @if ($equipment['missing'])
                        <p class="none">{{ __('reports.note_no_equipment') }}</p>
                    @else
                        <table class="kv">
                            <tr><td>{{ __('reports.equipment_name') }}</td><td><strong>{{ $o($equipment['name']) }}</strong></td></tr>
                            <tr><td>{{ __('reports.serial') }}</td><td>{{ $o($equipment['serial']) }} · {{ $o($equipment['tag']) }}</td></tr>
                            <tr><td>{{ __('reports.equipment_type') }}</td><td>{{ $o($equipment['type']) }}</td></tr>
                            <tr><td>{{ __('reports.oil_type') }}</td><td>{{ $o($equipment['oil_type']) }}</td></tr>
                            <tr><td>{{ __('reports.voltage') }}</td><td>{{ $o($equipment['voltage_hv']) }} / {{ $o($equipment['voltage_lv']) }} kV</td></tr>
                        </table>
                    @endif
                </div>
            </div>
        </td>
    </tr>
</table>

@forelse ($sections as $section)
    <div class="section">
        <div class="section__title">
            {{ $section['test'] }}
            @if ($section['method'])
                <span class="section__method">· {{ $section['method'] }}@if ($section['accreditation'])<sup>({{ $section['accreditation'] }})</sup>@endif</span>
            @endif
        </div>
        <table class="results">
            <thead>
                <tr>
                    <th style="width: 6%">{{ __('reports.col_item') }}</th>
                    <th>{{ __('reports.col_test') }}</th>
                    <th style="width: 12%">{{ __('reports.col_unit') }}</th>
                    <th style="width: 26%">{{ __('reports.col_limit') }}</th>
                    <th style="width: 16%">{{ __('reports.col_result') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($section['rows'] as $row)
                    <tr>
                        <td class="num">{{ $row['item'] }}</td>
                        <td>{{ $o($row['analyte']) }}</td>
                        <td class="num">{{ $o($row['unit']) }}</td>
                        <td class="num">{{ $row['limit'] }}
                            @if ($row['criterion'])
                                <div class="criterion">{{ $row['criterion'] }}</div>
                            @endif
                        </td>
                        <td class="result">
                            @if ($row['status'] === 'out_of_spec')
                                <span class="out">{{ $row['value'] }}
                                    <span class="out__flag">({{ __('reports.out_of_spec') }})</span>
                                </span>
                            @elseif ($row['status'] === null)
                                <span class="none">{{ $row['value'] }}</span>
                            @else
                                {{ $row['value'] }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@empty
    <div class="empty">{{ __('reports.no_results') }}</div>
@endforelse

@if (! empty($notes))
    <div class="notes">
        @foreach ($notes as $note)
            <p>{{ $note }}</p>
        @endforeach
    </div>
@endif

{{-- Las firmas van DESPUÉS de los resultados y de las notas: firmar es dar
     por bueno lo que está arriba. --}}
<table class="signatures">
    <tr>
        @forelse ($signers as $signer)
            <td>
                <div class="sign__line"></div>
                <div class="sign__rel">{{ __('reports.relation.' . $signer->relation, [], null) }}</div>
                <div class="sign__name">{{ $signer->user?->name ?? $signer->name }}</div>
                <div class="sign__title">{{ $signer->title }}</div>
            </td>
        @empty
            {{-- Sin firmantes cargados el informe no inventa ninguno: deja el
                 espacio para firmar a mano y lo dice. --}}
            <td>
                <div class="sign__line"></div>
                <div class="sign__rel">{{ __('reports.no_signers') }}</div>
            </td>
        @endforelse
        <td class="qr">
            <img src="{{ $verifyQr }}" alt="">
            <div class="qr__hint">{{ __('reports.verify_hint') }}</div>
        </td>
    </tr>
</table>

<div class="foot">
    {{ __('reports.footer_legend') }}<br>
    {{ __('reports.footer_accreditation') }}<br>
    {{ __('reports.generated_by', ['name' => $generatedBy ?? '—']) }}
    @if ($letterhead['disclaimer'])
        <br>{{ $letterhead['disclaimer'] }}
    @endif
</div>

</body>
</html>
