{{--
  REPRODUCCIÓN DEL INFORME DEL SISTEMA VIEJO — solo para comparar.

  Esto NO es un informe que el laboratorio emita. Es la maqueta del papel que
  imprimía el sistema Ruby, reconstruida a partir de sus plantillas ERB, para
  poder ponerla al lado del informe nuevo con LOS MISMOS DATOS y discutir las
  diferencias sobre algo concreto en vez de sobre recuerdos.

  Se reproducen a propósito las erratas del original ("Tension Interfacial" sin
  tilde, "omh*cm" por "ohm*cm", "VISCOCIDAD", la frase duplicada del descargo
  legal): corregirlas acá haría que la comparación mintiera.

  Lo que NO se reproduce, porque son defectos del renderizador y no del diseño:
  el `aaaaaaaaa` suelto que dejaba un parcial muerto al final del PDF, y la
  página en blanco del salto sobrante después de la última sección.

  Fuentes: app/views/im_management/rem_reports/{show.erb,partials/_report_*.erb}
  y app/views/layouts/pdf_footer.erb del repositorio `labo_old`.
--}}
@php
    $o  = fn ($v) => ($v === null || $v === '') ? '-' : $v;
    // El viejo imprimía "-" con `if x.to_i == 0`, así que se tragaba tanto el
    // nulo como el cero real y cualquier valor entre -1 y 1.
    $t0 = fn ($v) => ((int) $v === 0) ? '-' : number_format((float) $v, 2, '.', '');
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 3mm 10mm 30mm 10mm; }
        body { font-family: Helvetica, sans-serif; color: #212529; font-size: 10px; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .grid, .grid td, .grid th { border: 1px solid #343a40; }
        .grid td, .grid th { padding: .3rem; }
        .c { text-align: center; }
        .r { text-align: right; }
        .bar { background-color: #E8E8E8; }
        .red { color: #dc3545; }
        .sec { font-size: 12px; font-weight: bold; padding-top: 6px; display: block; }
        .ttl { font-size: 14px; font-weight: bold; }
        .brk { page-break-before: always; }
        .legend { font-size: 8px; font-style: italic; }
        .cond { width: 500px; }
        .cond td { border: 1px solid #343a40; padding: .3rem; }
        .foot { position: fixed; bottom: -26mm; left: 0; right: 0; }
        .foot .legal { font-size: 8px; text-align: justify; }
        .foot .company { font-size: 10px; text-align: center; font-weight: bold;
                         border-bottom: 1px solid #dc3545; padding-bottom: 2px; margin-top: 4px; }
        .foot .addr { font-size: 10px; margin-top: 4px; }
        .sign { margin-top: 8px; }
        .sign .line { border-top: 1px solid #343a40; width: 200px; margin: 0 auto; }
    </style>
</head>
<body>

{{-- El pie va en TODAS las páginas: es footer de wkhtmltopdf en el original. --}}
<div class="foot">
    <div class="legal">{{ $legal }}</div>
    <div class="company">HITACHI ENERGY PERÚ SA</div>
    <table class="addr"><tr>
        <td style="width:70%">Calle Kapalla Mz. B Lote 5-6 de Urbanización Las Praderas de Lurín-Lurín-Lima</td>
        <td class="r"></td>
    </tr></table>
</div>

@foreach ($paginas as $i => $pagina)
<div @if($i > 0) class="brk" @endif>

    {{-- Logos. El sello ANAB SOLO en las secciones que el programador eligió:
         fisicoquímicos, cromatografía y azufres. No hay condición en el viejo,
         es qué parcial se escribió en la línea 2 de cada archivo. --}}
    <table><tr>
        <td style="width:33%">{!! $logo !!}</td>
        <td style="width:34%"></td>
        <td style="width:33%" class="r">@if ($pagina['anab']) {!! $anab !!} @endif</td>
    </tr></table>

    <table class="bar" style="margin-top:4px"><tr>
        <td class="c" style="padding:4px"><b>INFORME DE RESULTADOS {{ $numero }}</b></td>
    </tr></table>

    <span class="sec">INFORMACIÓN DEL CLIENTE </span>
    <table class="grid">
        <tr>
            <td><b>Cliente</b></td><td>{{ $cli['nombre'] }}</td>
            <td style="width:19%"><b>Nº Orden Servicio</b></td><td>{{ $o($cli['os']) }}</td>
        </tr>
        <tr>
            <td><b>Dirección</b></td><td>{{ $cli['direccion'] }}</td>
            <td><b>Fecha de Recepción (dd-mm-yy)</b></td><td>{{ $o($cli['recepcion']) }}</td>
        </tr>
        <tr>
            <td><b>Contacto</b></td><td>{{ $cli['contacto'] }}</td>
            <td><b>Muestra Extraida por</b></td><td>{{ $o($cli['muestreador']) }}</td>
        </tr>
        <tr>
            <td><b>Usuario Final</b></td><td>{{ $o($cli['usuario_final']) }}</td>
            <td><b>Fecha de Emisión (dd-mm-yy)</b></td><td>{{ $o($cli['emision']) }}</td>
        </tr>
        <tr>
            <td><b>Descripción Muestra</b></td><td colspan="3">{{ $cli['descripcion'] }}</td>
        </tr>
    </table>

    <span class="sec">INFORMACIÓN DEL EQUIPO (DATOS PROPORCIONADOS POR EL CLIENTE)</span>
    <table class="grid">
        <tr>
            <td><b>Serie</b></td><td>{{ $o($eq['serie']) }}</td>
            <td><b>Tensión (Kv)</b></td><td>{{ $o($eq['tension']) }}</td>
            <td><b>Fecha de muestreo</b></td><td>{{ $o($eq['muestreo']) }}</td>
        </tr>
        <tr>
            <td><b>Código de cliente / TAG</b></td><td>{{ $o($eq['tag']) }}</td>
            <td><b>Potencia (MVA)</b></td><td>{{ $o($eq['potencia']) }}</td>
            <td><b>Punto de Muestreo</b></td><td>{{ $o($eq['punto']) }}</td>
        </tr>
        <tr>
            <td><b>Locación</b></td><td>{{ $o($eq['locacion']) }}</td>
            <td><b>Sistema de Expansión </b></td><td>{{ $o($eq['preservacion']) }}</td>
            <td><b>Razón de Muestreo</b></td><td>{{ $o($eq['razon']) }}</td>
        </tr>
        <tr>
            <td><b>Tipo de Equipo </b></td><td>{{ $o($eq['tipo']) }}</td>
            <td><b>Tipo de Aceite</b></td><td>{{ $o($eq['aceite']) }}</td>
            <td><b>Temp. Aceite Transform. (°C) </b></td><td>{{ $t0($eq['temp_aceite']) }}</td>
        </tr>
        <tr>
            <td><b>Fabricante</b></td><td>{{ $o($eq['fabricante']) }}</td>
            <td><b>Marca de aceite</b></td><td>{{ $o($eq['marca_aceite']) }}</td>
            <td><b>Temp. Aceite campo(°C)</b></td><td>{{ $t0($eq['temp_campo']) }}</td>
        </tr>
        <tr>
            <td><b>Año de Fabricación</b></td><td>{{ (int) $eq['anio'] === 0 ? '-' : $eq['anio'] }}</td>
            <td><b>Cant. de Aceite </b></td><td>{{ (int) $eq['volumen'] === 0 ? '-' : $eq['volumen'] . ' ' . $eq['unidad'] }}</td>
            <td><b>Temp. Amb. campo(°C)</b></td><td>{{ $t0($eq['temp_ambiente']) }}</td>
        </tr>
        <tr>
            <td><b>Conmutador</b></td><td>{{ $o($eq['conmutador']) }}</td>
            <td><b>En operación</b></td><td>{{ $o($eq['operacion']) }}</td>
            <td><b>Hum. Relat. campo(%HR)</b></td><td>{{ $t0($eq['humedad']) }}</td>
        </tr>
    </table>

    @if ($pagina['tipo'] === 'analisis')
        <div style="font-size:12px">
            <div style="font-size:14px"><b>ANÁLISIS DE RESULTADOS (opiniones e interpretaciones):</b></div>
            <table class="grid" style="margin-top:6px">
                @foreach ($pagina['familias'] as $fam)
                    <tr>
                        <td class="bar" style="width:28%; vertical-align:middle; padding:.75rem">{{ $fam['titulo'] }}</td>
                        <td style="padding:.75rem">{{ $fam['texto'] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @else
        <div><b style="font-size:12px">RESULTADOS DE ENSAYOS</b></div>
        <table class="grid">
            <tr class="bar"><td colspan="6" class="c"><span class="ttl">{{ $pagina['titulo'] }}</span></td></tr>
            <tr>
                <th class="c">ITEM</th>
                <th class="c">NORMA</th>
                <th class="c">{{ $pagina['col3'] }}</th>
                <th class="c">UNIDAD</th>
                <th class="c">VALOR DE ORIENTACIÓN (*)</th>
                <th class="c">RESULTADO</th>
            </tr>
            @foreach ($pagina['filas'] as $fila)
                <tr>
                    <td class="c">{{ $fila['item'] }}</td>
                    <td class="c">{!! $fila['norma'] !!}</td>
                    <td>{{ $fila['ensayo'] }}</td>
                    <td class="c">{{ $fila['unidad'] }}</td>
                    <td class="c">{{ $fila['orientacion'] }}</td>
                    <td class="c bar"><b @if($fila['fuera']) class="red" @endif>{{ $fila['resultado'] }}</b></td>
                </tr>
            @endforeach
        </table>

        @if ($pagina['pie_celda'])
            <div>(1) Tipo de celda: MC2A, tensión (RMS): 2000VCA / 500VDC</div>
            <div>(A) Acreditado</div>
            <div>(NA) No Acreditado</div>
        @endif

        @if ($pagina['anab'])
            <div class="legend" style="margin-top:6px">{{ $acreditacion['es'] }}<br>{{ $acreditacion['en'] }}</div>
        @endif

        @if (! empty($pagina['relaciones']))
            <div><b>RELACIONES</b></div>
            <table class="grid">
                <tr>
                    <td style="width:50%">
                        @foreach ($pagina['relaciones']['totales'] as $etiqueta => $valor)
                            <div>{{ $etiqueta }} &nbsp; {{ $valor }}</div>
                        @endforeach
                    </td>
                    <td>
                        @foreach ($pagina['relaciones']['porcentaje_total'] as $etiqueta => $valor)
                            <div>{{ $etiqueta }} &nbsp; {{ $valor }}</div>
                        @endforeach
                    </td>
                </tr>
                <tr>
                    <td>
                        @foreach ($pagina['relaciones']['ratios'] as $etiqueta => $valor)
                            <div>{{ $etiqueta }} &nbsp; {{ $valor }}</div>
                        @endforeach
                    </td>
                    <td>
                        @foreach ($pagina['relaciones']['porcentajes'] as $etiqueta => $valor)
                            <div>{{ $etiqueta }} &nbsp; {{ $valor }}</div>
                        @endforeach
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <table>
                            <tr>
                                <td style="width:8%"><b>TGC</b><br><b>TGC-CO</b></td>
                                <td style="width:25%">= CO+H2+CH4+C2H6+C2H4+C2H2<br>= H2+CH4+C2H6+C2H4+C2H2</td>
                                <td style="width:8%"><b>%GAS</b><br><b>TGC(%)</b></td>
                                <td style="width:25%">= GAS/(TGC-CO)x100<br>= TGC/TG x100</td>
                                <td style="width:8%"><b>TG</b><br><b>TGC</b></td>
                                <td>= Total de Gases<br>= Total de Gases Combustibles</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        @endif

        <br>
        <table class="cond">
            @foreach ($pagina['condiciones'] as $etiqueta => $valor)
                <tr><td>{{ $etiqueta }}</td><td>{{ $valor }}</td></tr>
            @endforeach
        </table>
    @endif

    <table class="sign"><tr>
        <td style="width:22%"></td>
        <td style="width:22%" class="r"><br><br><br>Reportado por:</td>
        <td style="width:34%" class="c">
            <br><br><br>
            <div class="line"></div>
            {{ $firma['nombre'] }}<br>{{ $firma['cargo'] }}
        </td>
        <td></td>
    </tr></table>

</div>
@endforeach

</body>
</html>
