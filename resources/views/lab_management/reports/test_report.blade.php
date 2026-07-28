{{--
    Informe de ensayo de una muestra.

    ┌──────────────────────────────────────────────────────────────────────────┐
    │ ESTE FORMATO NO SE REDISEÑA: SE REPRODUCE                                │
    └──────────────────────────────────────────────────────────────────────────┘
    Es el formato ACREDITADO del laboratorio. Una página por ensayo, y en TODAS
    la misma cabecera: logo, número de informe, datos del cliente y datos del
    equipo. Eso no es redundancia ni un descuido del sistema anterior: los
    informes se desarman —se fotocopian, se escanean, se adjuntan sueltos a un
    correo— y una hoja separada del resto tiene que poder identificarse sola. Es
    lo que pide la ISO/IEC 17025, y es la razón por la que la versión condensada
    de una sola página no servía.

    Lo que SÍ cambia respecto del informe viejo, y hay que conservar:

      · El veredicto viene CONGELADO en el resultado (`status`), decidido al
        validar la hoja. Acá no se compara nada: se lee. El viejo volvía a
        interpretar el límite al imprimir, con un `delete!` que devolvía nil
        cuando la palabra "(máximo)" no estaba, y entonces coloreaba contra 0.
      · El "sin criterio" sale en gris Y con la palabra, nunca como conforme.
      · Las temperaturas nulas salen como raya. En el viejo un campo vacío se
        imprimía "0.00" y era indistinguible de una medición real de cero.
      · Código de verificación + QR en todas las páginas, y numeración.

    Reglas de DomPDF que hay que respetar (mismas que el resto de los PDF del
    proyecto): solo "Helvetica"; `font-weight` únicamente normal o bold —nada de
    600, que dompdf redondea y descoloca la maqueta—; y nada de `position: fixed`
    con offsets negativos salvo la numeración de página, que vive en el margen
    inferior de `@page`.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('reports.title') }} {{ $sample['code'] }}</title>
    <style>
        @page { margin: 22px 26px 40px 26px; }

        body { font-family: Helvetica; font-size: 8pt; color: #1a1a1a; margin: 0; }
        p { margin: 0 0 2px 0; }

        /* Salto de página entre ensayos: el `.alwaysbreak` del informe viejo.
           No se pone en la primera hoja, o el PDF abre con una página en blanco. */
        .brk { page-break-before: always; }

        /* ── Membrete ───────────────────────────────────────────────────── */
        .lh { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .lh td { vertical-align: middle; }
        .lh__logo { width: 40%; }
        .lh__logo img { max-height: 52px; }
        .lh__name { font-size: 12pt; font-weight: bold; color: #354A5F; }
        .lh__addr { font-size: 6.5pt; color: #555555; }
        .lh__mid { width: 30%; text-align: center; font-size: 7pt; color: #555555; }
        .lh__acc { width: 30%; text-align: right; }
        .lh__acc img { max-height: 62px; }

        /* ── Banda del número de informe ─────────────────────────────────── */
        .band {
            background: #E8E8E8; border: 1px solid #000000;
            text-align: center; font-weight: bold; font-size: 10pt;
            padding: 3px 0; margin-bottom: 5px;
        }

        .blk { font-weight: bold; font-size: 8.5pt; margin: 5px 0 2px 0; }

        /* ── Las dos tablas de cabecera ──────────────────────────────────── */
        table.grid { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
        table.grid th, table.grid td {
            border: 1px solid #000000; padding: 2px 4px; text-align: left;
            vertical-align: middle;
        }
        table.grid th { font-weight: bold; background: #F4F4F4; width: 13%; }
        table.grid td { width: 20%; }

        /* ── Resultados ──────────────────────────────────────────────────── */
        table.res { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
        table.res th, table.res td { border: 1px solid #000000; padding: 2px 4px; }
        table.res th { background: #E8E8E8; font-weight: bold; text-align: center; }
        .res__family {
            background: #E8E8E8; border: 1px solid #000000;
            text-align: center; font-weight: bold; font-size: 10pt; padding: 3px 0;
        }
        .c { text-align: center; }
        .res__value { text-align: center; font-weight: bold; background: #E8E8E8; }

        /* Fuera de norma: rojo Y la palabra. El color solo no sobrevive a una
           fotocopia en blanco y negro, y este papel se fotocopia. */
        .out { color: #C8281D; }
        /* Sin criterio: ni negro ni rojo. No se comparó contra nada, y eso no
           puede leerse como conforme. */
        .none { color: #666666; font-weight: normal; }
        .flag { font-size: 6pt; font-weight: normal; }
        /* De qué cuadro salió el límite. Chico, debajo del número: el cliente
           lee el número; quien audita necesita saber contra qué se lo juzgó. */
        .crit { font-size: 6pt; color: #555555; }
        .sup { font-size: 6pt; }

        /* ── Notas al pie de la página de ensayo ─────────────────────────── */
        .foot { margin-top: 5px; font-size: 7pt; line-height: 1.35; }
        .foot__iso { font-size: 6.5pt; font-style: italic; color: #333333; }
        .foot__warn { margin-top: 3px; color: #8a5a00; font-size: 7pt; }

        /* ── Pie: condiciones de ensayo · firmas · verificación ──────────── */
        .strip { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .strip td { vertical-align: top; }
        .strip__cond { width: 46%; }
        .strip__sign { width: 39%; }
        .strip__qr { width: 15%; text-align: center; }

        table.cond { width: 100%; border-collapse: collapse; font-size: 7pt; }
        table.cond td { border: 1px solid #000000; padding: 2px 4px; }
        table.cond td.cond__k { font-weight: bold; width: 58%; }

        table.sign { width: 100%; border-collapse: collapse; }
        table.sign td { text-align: center; padding: 0 4px; vertical-align: bottom; border: 0; }
        .sign__lead { font-size: 7.5pt; padding-bottom: 22px; }
        .sign__line { border-top: 1px solid #000000; margin: 0 0 2px 0; }
        .sign__rel { font-size: 6.5pt; color: #555555; }
        .sign__name { font-size: 7.5pt; font-weight: bold; }
        .sign__title { font-size: 6.5pt; color: #555555; }

        .qr img { width: 62px; height: 62px; }
        .qr__code { font-size: 6.5pt; font-weight: bold; }
        .qr__hint { font-size: 5.5pt; color: #555555; line-height: 1.2; }

        /* ── Última página: análisis de resultados ───────────────────────── */
        table.an { width: 100%; border-collapse: collapse; font-size: 8pt; }
        table.an td { border: 1px solid #000000; padding: 4px 6px; vertical-align: top; }
        .an__fam { background: #E8E8E8; font-weight: bold; width: 22%; vertical-align: middle; }
        .an__empty { color: #666666; font-style: italic; }
        .an__edited { margin-top: 3px; font-size: 6.5pt; color: #555555; font-style: italic; }
        .notes { margin-top: 8px; border: 1px solid #E9A23B; background: #FDF6EC; padding: 5px 7px; font-size: 7pt; }
        .scope { margin-top: 6px; font-size: 6.5pt; color: #555555; font-style: italic; }
        .empty { border: 1px solid #000000; padding: 14px; text-align: center; color: #555555; }

        /* El código de muestra y el de verificación van en el margen inferior
           de `@page` —por eso el margen de abajo es más grande que el de
           arriba—, así ninguna fila de tabla se les monta encima.

           La NUMERACIÓN ("Página 3 de 5") no está acá: se dibuja sobre el
           lienzo desde el controlador. DomPDF resuelve `counter(page)` pero
           devuelve 0 en `counter(pages)`, así que el papel salía "3 / 0", que en
           un informe de ensayo es peor que no numerar: quien recibe tres hojas
           sueltas no puede saber si le faltan. Ver TestReportController. */
        .pagenum__code {
            position: fixed; bottom: -26px; right: 0;
            font-size: 6.5pt; color: #555555;
        }
        .pagenum__sample {
            position: fixed; bottom: -26px; left: 0;
            font-size: 6.5pt; color: #555555;
        }
    </style>
</head>
<body>

{{-- El código de muestra, el de verificación y —desde el lienzo— el número de
     página se repiten en TODAS las páginas: una hoja suelta de un informe de
     seis páginas tiene que poder identificarse sola. --}}
<div class="pagenum__sample">{{ $sample['code'] }}</div>
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

    // La regla que el informe viejo rompía: si no se midió, RAYA. Nunca "0.00",
    // que es indistinguible de una medición real de cero.
    $temp = fn ($v, $suf = '') => $v === null ? '—' : number_format((float) $v, 2, '.', '') . $suf;

    // Los datos del equipo, tolerando la muestra sin equipo asignado: la
    // cabecera se dibuja igual —el formato es el formato— y los campos salen en
    // raya, con la advertencia impresa en la última página.
    $eqFaltante = $equipment['missing'] ?? false;
    $eq = fn ($k) => $eqFaltante ? null : ($equipment[$k] ?? null);

    $tension = $eqFaltante ? null : implode(' / ', array_filter(
        [$equipment['voltage_hv'] ?? null, $equipment['voltage_lv'] ?? null],
        fn ($v) => $v !== null,
    ));

    $aceite = $eqFaltante ? null : implode(' ', array_filter([
        $equipment['oil_volume'] ?? null,
        $equipment['oil_volume_unit'] ?? null,
    ]));

    // "En operación" se guarda como clave y se traduce con el MISMO archivo de
    // idioma que usa la ficha del equipo: una sola redacción para los dos.
    $enServicio = $eq('service_state')
        ? __('equipment.service_states.' . $equipment['service_state'])
        : null;

    // Las páginas del informe: una por ensayo y, al final, el análisis de
    // resultados. Se arma la lista para escribir la cabecera UNA vez en el
    // código fuente y dibujarla N veces en el papel.
    $paginas = [];

    foreach ($sections as $seccion) {
        $paginas[] = ['kind' => 'test', 'section' => $seccion];
    }

    $paginas[] = $sections === [] ? ['kind' => 'empty'] : ['kind' => 'analysis'];
@endphp

@foreach ($paginas as $indice => $pagina)
<div class="{{ $indice > 0 ? 'brk' : '' }}">

    {{-- ── Membrete ────────────────────────────────────────────────────────
         El sello del organismo de acreditación va solo en las páginas de
         RESULTADOS. La del análisis no lo lleva —el informe viejo tampoco, y
         por la razón correcta: una opinión o interpretación queda fuera del
         alcance acreditado. --}}
    <table class="lh">
        <tr>
            <td class="lh__logo">
                @if ($letterhead['logo'])
                    <img src="{{ $letterhead['logo'] }}" alt="">
                @else
                    <span class="lh__name">{{ $letterhead['name'] ?? '' }}</span>
                @endif
            </td>
            <td class="lh__mid">
                @if ($letterhead['logo'] && $letterhead['name'])
                    {{ $letterhead['name'] }}<br>
                @endif
                <span class="lh__addr">{{ $letterhead['address'] }}</span>
            </td>
            <td class="lh__acc">
                @if (($letterhead['accreditation_logo'] ?? null) && $pagina['kind'] !== 'analysis')
                    <img src="{{ $letterhead['accreditation_logo'] }}" alt="">
                @endif
            </td>
        </tr>
    </table>

    <div class="band">{{ __('reports.header_title') }} {{ $sample['report_number'] }}</div>

    {{-- ── Información del cliente ─────────────────────────────────────── --}}
    <div class="blk">{{ __('reports.customer_info') }}</div>
    <table class="grid">
        <tr>
            <th>{{ __('reports.customer_name') }}</th>
            <td>{{ $o($customer['name']) }}</td>
            <th>{{ __('reports.service_order') }}</th>
            <td>{{ $o($sample['service_order']) }}</td>
        </tr>
        <tr>
            <th>{{ __('reports.address') }}</th>
            <td>{{ $o($customer['address']) }}</td>
            <th>{{ __('reports.received_at') }}</th>
            <td>{{ $fecha($sample['received_at']) }}</td>
        </tr>
        <tr>
            <th>{{ __('reports.contact') }}</th>
            <td>{{ $o($sample['contact_info']) }}</td>
            <th>{{ __('reports.sampler') }}</th>
            <td>{{ $o($sample['sampler']) }}</td>
        </tr>
        <tr>
            <th>{{ __('reports.end_user') }}</th>
            <td>{{ $o($sample['end_user']) }}</td>
            <th>{{ __('reports.issued_at') }}</th>
            <td>{{ $generatedAt->format('d-m-Y') }}</td>
        </tr>
        <tr>
            <th>{{ __('reports.sample_description') }}</th>
            <td colspan="3">{{ $o($sample['description']) }}</td>
        </tr>
    </table>

    {{-- ── Información del equipo ──────────────────────────────────────── --}}
    <div class="blk">{{ __('reports.equipment_info') }}</div>
    <table class="grid">
        <tr>
            <th>{{ __('reports.serial') }}</th>
            <td>{{ $o($eq('serial')) }}</td>
            <th>{{ __('reports.voltage') }}</th>
            <td>{{ $o($tension) }}</td>
            <th>{{ __('reports.sampled_at') }}</th>
            <td>{{ $fecha($sample['sampled_at']) }}</td>
        </tr>
        <tr>
            <th>{{ __('reports.tag') }}</th>
            <td>{{ $o($eq('tag')) }}</td>
            <th>{{ __('reports.power') }}</th>
            <td>{{ $o($eq('power_mva')) }}</td>
            <th>{{ __('reports.sampling_point') }}</th>
            <td>{{ $o($sample['sampling_point']) }}</td>
        </tr>
        <tr>
            <th>{{ __('reports.location') }}</th>
            <td>{{ $o($eq('location') ?? $eq('substation')) }}</td>
            <th>{{ __('reports.preservation') }}</th>
            <td>{{ $o($eq('preservation')) }}</td>
            <th>{{ __('reports.sampling_reason') }}</th>
            <td>{{ $o($sample['sampling_reason']) }}</td>
        </tr>
        <tr>
            <th>{{ __('reports.equipment_type') }}</th>
            <td>{{ $o($eq('type')) }}</td>
            <th>{{ __('reports.oil_type') }}</th>
            <td>{{ $o($eq('oil_type')) }}</td>
            <th>{{ __('reports.oil_temp') }}</th>
            <td>{{ $temp($sample['oil_temp_c']) }}</td>
        </tr>
        <tr>
            <th>{{ __('reports.brand') }}</th>
            <td>{{ $o($eq('brand')) }}</td>
            {{-- La marca del ACEITE no existe en el esquema nuevo: el equipo
                 guarda el TIPO de aceite, no su marca comercial. Se deja la
                 celda —el formato acreditado la tiene— y sale en raya hasta que
                 el laboratorio decida dónde vive ese dato. --}}
            <th>{{ __('reports.oil_brand') }}</th>
            <td>—</td>
            <th>{{ __('reports.equipment_temp') }}</th>
            <td>{{ $temp($sample['equipment_temp_c']) }}</td>
        </tr>
        <tr>
            <th>{{ __('reports.manufacture_year') }}</th>
            <td>{{ $o($eq('year')) }}</td>
            <th>{{ __('reports.oil_qty') }}</th>
            <td>{{ $o($aceite) }}</td>
            <th>{{ __('reports.ambient_temp') }}</th>
            <td>{{ $temp($sample['ambient_temp_c']) }}</td>
        </tr>
        <tr>
            <th>{{ __('reports.tap_changer') }}</th>
            <td>{{ $o($eq('tap_changer')) }}</td>
            <th>{{ __('reports.in_service') }}</th>
            <td>{{ $o($enServicio) }}</td>
            <th>{{ __('reports.humidity') }}</th>
            <td>{{ $temp($sample['relative_humidity']) }}</td>
        </tr>
    </table>

    @if ($pagina['kind'] === 'test')
        @php $s = $pagina['section']; @endphp

        {{-- ── Resultados del ensayo de esta página ────────────────────── --}}
        <div class="blk">{{ __('reports.results_title') }}</div>
        <table class="res">
            <thead>
                <tr>
                    <td colspan="6" class="res__family">
                        {{ \Illuminate\Support\Str::upper($s['test'] ?? '') }}
                    </td>
                </tr>
                <tr>
                    <th style="width: 5%">{{ __('reports.col_item') }}</th>
                    <th style="width: 20%">{{ __('reports.col_standard') }}</th>
                    <th style="width: 28%">{{ __('reports.col_test') }}</th>
                    <th style="width: 10%">{{ __('reports.col_unit') }}</th>
                    <th style="width: 21%">{{ __('reports.col_limit') }}</th>
                    <th style="width: 16%">{{ __('reports.col_result') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($s['rows'] as $row)
                    <tr>
                        <td class="c">{{ $row['item'] }}</td>
                        {{-- La norma del MÉTODO, con su marca de acreditación.
                             Es la que el analista eligió al correr el ensayo, o
                             sea la que de verdad se usó. No es la norma del
                             CRITERIO: esa va al pie, en las condiciones. --}}
                        <td class="c">
                            {{ $o($row['method'] ?? null) }}@if (!empty($row['accreditation']))<sup class="sup">({{ $row['accreditation'] }})</sup>@endif
                        </td>
                        <td>{{ $o($row['analyte']) }}</td>
                        <td class="c">{{ $o($row['unit']) }}</td>
                        <td class="c">
                            {{ $row['limit'] }}
                            @if ($row['criterion'])
                                <div class="crit">{{ $row['criterion'] }}</div>
                            @endif
                        </td>
                        <td class="res__value">
                            @if ($row['status'] === 'out_of_spec')
                                <span class="out">{{ $row['value'] }}
                                    <span class="flag">({{ __('reports.out_of_spec') }})</span>
                                </span>
                            @elseif ($row['status'] === null)
                                <span class="none">{{ $row['value'] }}
                                    <span class="flag">({{ __('reports.no_criterion') }})</span>
                                </span>
                            @else
                                {{ $row['value'] }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ── Notas al pie ───────────────────────────────────────────── --}}
        <div class="foot">
            @if ($s['footnote'])
                {{ $s['footnote'] }}<br>
            @endif
            {{-- La leyenda de las marcas solo se imprime si el método de esta
                 página lleva una: explica el superíndice de la columna NORMA, y
                 sin superíndice no explica nada. --}}
            @if (collect($s['rows'])->pluck('accreditation')->filter()->isNotEmpty())
                {{ __('reports.foot_accredited') }}<br>
                {{ __('reports.foot_not_accredited') }}<br>
            @endif
            <span class="foot__iso">
                {{-- El párrafo de la acreditación es un DATO del laboratorio
                     (organismo, certificado y alcance), no un texto de la
                     plantilla: el número vence y otro laboratorio se acredita
                     con otro organismo. Si está vacío no se imprime nada — un
                     laboratorio sin acreditar no puede emitir un papel que
                     insinúe que sí. --}}
                @if ($letterhead['accreditation_note'] ?? null)
                    {{ $letterhead['accreditation_note'] }}<br>
                @endif
                {{ __('reports.footer_legend') }}
            </span>
            @if ($s['no_criteria'] > 0)
                <div class="foot__warn">
                    {{ __('reports.note_no_criteria_page', ['count' => $s['no_criteria']]) }}
                </div>
            @endif
        </div>

    @elseif ($pagina['kind'] === 'analysis')

        {{-- ── Última página: análisis de resultados ───────────────────── --}}
        <div class="blk">{{ __('reports.analysis_title') }}</div>
        <table class="an">
            @foreach ($analysis as $fila)
                <tr>
                    <td class="an__fam">{{ \Illuminate\Support\Str::upper($fila['label'] ?? '') }}</td>
                    <td>
                        @if ($fila['body'])
                            {!! nl2br(e($fila['body'])) !!}
                            @if ($fila['edited'])
                                <div class="an__edited">{{ __('reports.analysis_edited') }}</div>
                            @endif
                        @else
                            <span class="an__empty">{{ __('reports.analysis_empty') }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>

        {{-- Las advertencias del informe completo: cuántos resultados quedaron
             sin criterio, qué ensayos siguen pendientes, si la muestra no tiene
             equipo asignado. Un informe que calla lo que le falta se lee como
             completo, y ahí es donde un valor sin criterio pasa por conforme. --}}
        @if (! empty($notes))
            <div class="notes">
                @foreach ($notes as $note)
                    <p>{{ $note }}</p>
                @endforeach
            </div>
        @endif

        <div class="scope">
            {{ __('reports.analysis_scope') }}<br>
            {{ __('reports.generated_by', ['name' => $generatedBy ?? '—']) }}
            @if ($letterhead['disclaimer'])
                <br>{{ $letterhead['disclaimer'] }}
            @endif
        </div>

    @else

        <div class="empty">{{ __('reports.no_results') }}</div>

    @endif

    {{-- ── Pie de página: condiciones de ensayo · firma · verificación ───
         Va en TODAS las páginas, como el "Reportado por:" del informe viejo.
         Las condiciones son las de la hoja de bancada que produjo ESTOS
         resultados: dos ensayos de la misma muestra se corren días distintos y
         con el laboratorio en otro estado. --}}
    <table class="strip">
        <tr>
            <td class="strip__cond">
                @if ($pagina['kind'] === 'test')
                    <table class="cond">
                        <tr>
                            <td class="cond__k">{{ __('reports.cond_standard') }}</td>
                            <td>{{ $o($pagina['section']['conditions']['standard']) }}</td>
                        </tr>
                        <tr>
                            <td class="cond__k">{{ __('reports.cond_run_date') }}</td>
                            <td>{{ $fecha($pagina['section']['conditions']['run_date']) }}</td>
                        </tr>
                        <tr>
                            <td class="cond__k">{{ __('reports.cond_sample_temp') }}</td>
                            <td>{{ $temp($pagina['section']['conditions']['sample_temp_c'], ' °C') }}</td>
                        </tr>
                        <tr>
                            <td class="cond__k">{{ __('reports.cond_lab_temp') }}</td>
                            <td>{{ $temp($pagina['section']['conditions']['ambient_temp_c'], ' °C') }}</td>
                        </tr>
                        <tr>
                            <td class="cond__k">{{ __('reports.cond_lab_humidity') }}</td>
                            <td>{{ $temp($pagina['section']['conditions']['ambient_humidity'], ' %HR') }}</td>
                        </tr>
                    </table>
                @endif
            </td>
            <td class="strip__sign">
                <table class="sign">
                    <tr>
                        <td class="sign__lead">{{ __('reports.reported_by') }}</td>
                        @forelse ($signers as $signer)
                            <td>
                                <div class="sign__line"></div>
                                <div class="sign__rel">{{ __('reports.relation.' . $signer->relation, [], null) }}</div>
                                <div class="sign__name">{{ $signer->user?->name ?? $signer->name }}</div>
                                <div class="sign__title">{{ $signer->title }}</div>
                            </td>
                        @empty
                            {{-- Sin firmantes cargados el informe no inventa
                                 ninguno: deja el espacio para firmar a mano. --}}
                            <td>
                                <div class="sign__line"></div>
                                <div class="sign__rel">{{ __('reports.no_signers') }}</div>
                            </td>
                        @endforelse
                    </tr>
                </table>
            </td>
            <td class="strip__qr">
                <div class="qr">
                    <img src="{{ $verifyQr }}" alt="">
                    <div class="qr__code">{{ $verifyCode }}</div>
                    <div class="qr__hint">{{ __('reports.verify_hint') }}</div>
                </div>
            </td>
        </tr>
    </table>

</div>
@endforeach

</body>
</html>
