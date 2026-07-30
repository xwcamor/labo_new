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
        /* El papel viejo APRIETA la letra para que cada ensayo entre en una
           sola hoja: 10px de cuerpo con celdas de .3rem, y las tablas de
           cabecera todavía más chicas. Si esto se agranda, la cromatografía se
           parte en dos páginas y deja de ser el mismo papel. */
        body { font-family: Helvetica, sans-serif; color: #212529; font-size: 9px; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .grid, .grid td, .grid th { border: 1px solid #343a40; }
        .grid td, .grid th { padding: 2.2px 3px; }
        .c { text-align: center; }
        .r { text-align: right; }
        .bar { background-color: #E8E8E8; }
        .red { color: #dc3545; }
        .sec { font-size: 10px; font-weight: bold; padding-top: 4px; display: block; }
        .ttl { font-size: 12px; font-weight: bold; }
        .brk { page-break-before: always; }
        .legend { font-size: 7px; font-style: italic; }
        .cond { width: 420px; }
        .cond td { border: 1px solid #343a40; padding: 2.2px 3px; }
        .foot { position: fixed; bottom: -26mm; left: 0; right: 0; }
        .foot .legal { font-size: 7px; text-align: justify; }
        .foot .company { font-size: 10px; text-align: center; font-weight: bold;
                         border-bottom: 1px solid #dc3545; padding-bottom: 2px; margin-top: 4px; }
        .foot .addr { font-size: 9px; margin-top: 3px; }
        .sign { margin-top: 10px; }
        .sign td { vertical-align: bottom; text-align: center; padding: 0 6px; }
        .sign .line { border-top: 1px solid #343a40; margin: 2px 0 3px; }
        .sign .stamp { height: 34px; }
        .sign .rel { font-size: 8px; color: #555; }
        .sign .who { font-weight: bold; }
        .sign .role { font-size: 8px; color: #555; }
    </style>
</head>
<body>

{{-- El pie va en TODAS las páginas: es footer de wkhtmltopdf en el original. --}}
<div class="foot">
    {{-- Los tres del pie salen del WORKSPACE, no del código: el descargo
         legal (`tenants.report_disclaimer`), el nombre y la dirección. Estaban
         clavados con los datos de Hitachi, y esta plantilla dejó de ser solo
         una reproducción para comparar: es una exportación que el laboratorio
         ofrece, así que un papel de otro laboratorio no puede salir diciendo
         el nombre y la dirección de otra empresa. Vacío no imprime nada. --}}
    <div class="legal">{{ $legal }}</div>
    @if ($empresa)
        <div class="company">{{ \Illuminate\Support\Str::upper($empresa) }}</div>
    @endif
    <table class="addr"><tr>
        <td style="width:70%">{{ $direccion }}</td>
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
        {{-- La tabla de resultados. Las COLUMNAS las declara la hoja
             (`config/legacy_report.php`) y no están escritas acá: el sistema
             anterior tenía dieciséis partials con esta misma tabla copiada, cada
             uno con su variación —columna MÉTODO en furanos, sin columna de
             orientación en partículas, solo tres columnas en azufre— y corregir la
             cabecera había que corregirla dieciséis veces. --}}
        @php
            $rotulos = [
                'item'        => 'ITEM',
                'norma'       => 'NORMA',
                'ensayo'      => $pagina['col3'] ?? 'ENSAYO',
                'metodo'      => 'METODO',
                'unidad'      => 'UNIDAD',
                'orientacion' => 'VALOR DE ORIENTACIÓN (*)',
                'resultado'   => 'RESULTADO',
            ];
            $columnas = $pagina['columnas'];
        @endphp
        <div><b style="font-size:12px">RESULTADOS DE ENSAYOS</b></div>
        <table class="grid">
            <tr class="bar"><td colspan="{{ count($columnas) }}" class="c"><span class="ttl">{{ $pagina['titulo'] }}</span></td></tr>
            <tr>
                @foreach ($columnas as $col)
                    <th class="c">{{ $rotulos[$col] ?? mb_strtoupper($col) }}</th>
                @endforeach
            </tr>
            @foreach ($pagina['filas'] as $fila)
                <tr>
                    @foreach ($columnas as $col)
                        @if ($col === 'resultado')
                            {{-- El valor medido: negrita, banda de fondo, y rojo si
                                 quedó fuera de norma. El color sale del veredicto
                                 congelado, no de comparar acá. --}}
                            <td class="c bar"><b @if($fila['fuera']) class="red" @endif>{{ $fila['resultado'] }}</b></td>
                        @elseif ($col === 'norma')
                            <td class="c">{!! $fila['norma'] !!}</td>
                        @elseif ($col === 'ensayo')
                            <td>{{ $fila['ensayo'] }}</td>
                        @else
                            <td class="c">{{ $fila[$col] ?? '-' }}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </table>

        @if ($pagina['pie_celda'])
            <div>(1) Tipo de celda: MC2A, tensión (RMS): 2000VCA / 500VDC</div>
            <div>(A) Acreditado</div>
            <div>(NA) No Acreditado</div>
        @endif

        {{-- La nota al pie propia de la hoja: en furanos, que el grado de
             polimerización sale de la correlación de Chendong. En el sistema
             anterior era una línea escrita dentro de su propio partial. --}}
        @if (! empty($pagina['nota']))
            <div class="legend">{{ $pagina['nota'] }}</div>
        @endif

        {{-- El párrafo de la acreditación también es del workspace: el número
             de certificado vence y otro laboratorio se acredita con otro
             organismo. Sin el dato cargado no se imprime nada — insinuar una
             acreditación que el laboratorio no tiene es lo peor que puede hacer
             este papel. --}}
        @if ($pagina['anab'] && $acreditacion)
            <div class="legend" style="margin-top:6px">{{ $acreditacion }}</div>
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

    {{-- Las firmas de quienes aprobaron el informe. El papel viejo tenía UNA
         ("Reportado por:"); el laboratorio hoy mantiene una lista con su
         relación y su cargo, y todas van al pie de cada hoja. --}}
    @if (! empty($firmantes))
        <table class="sign"><tr>
            @foreach ($firmantes as $f)
                <td style="width:{{ (int) (100 / max(count($firmantes), 1)) }}%">
                    @if ($f['imagen'])
                        <img src="{{ $f['imagen'] }}" class="stamp">
                    @else
                        <br><br>
                    @endif
                    <div class="line"></div>
                    <div class="rel">{{ $f['relacion'] }}</div>
                    <div class="who">{{ $f['nombre'] }}</div>
                    <div class="role">{{ $f['cargo'] }}</div>
                </td>
            @endforeach
        </tr></table>
    @endif

</div>
@endforeach

</body>
</html>
