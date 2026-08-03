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
        /* ── EL TAMAÑO DEL PAPEL VIEJO: 10px, MEDIDO ───────────────────────
           Tercera vez que se toca y esta vez con la fuente a la vista, porque
           las dos anteriores se equivocaron en direcciones opuestas: primero
           9px («el papel viejo aprieta la letra»), después 11px («el original
           no fijaba tamaño y heredaba el 1rem de Bootstrap»). Falso lo
           segundo: cada hoja del viejo abre con
           `<div id="main_content" style="font-size: 10px;">`
           (`_report_cromas.erb:9`, `_report_physicals.erb`, y la cabecera en
           `_report_main_info.erb:11,52`). El cuerpo del papel viejo es 10px,
           los títulos de sección 12px, el título de la hoja 14px y la página
           de análisis 12px/14px — exactamente lo que este blade tiene ahora.
           A 10px todo sigue entrando en una hoja por prueba (a 11px ya
           entraba; achicar no rompe el paginado). */
        body { font-family: Helvetica, sans-serif; color: #212529; font-size: 10px; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .grid, .grid td, .grid th { border: 1px solid #343a40; }
        .grid td, .grid th { padding: 3px 4px; }
        .c { text-align: center; }
        .r { text-align: right; }
        .bar { background-color: #E8E8E8; }
        .red { color: #dc3545; }
        .sec { font-size: 12px; font-weight: bold; padding-top: 4px; display: block; }
        .ttl { font-size: 14px; font-weight: bold; }
        .brk { page-break-before: always; }
        .legend { font-size: 8.5px; font-style: italic; }
        .cond td { border: 1px solid #343a40; padding: 3px 4px; }
        /* La grilla de relaciones de gases y su leyenda de fórmulas: dieciocho
           renglones de números cortos, donde el interlineado del cuerpo pesa más
           que el tamaño de la letra. */
        .rel td { padding: 2px 4px; line-height: 1.2; }
        /* Sin bordes propios: la leyenda vive DENTRO de una celda del `.grid`
           y `.grid td` alcanza también a las celdas de esta tabla anidada, así
           que cada fórmula quedaba con su cajita dentro de la caja — el borde
           doble que no está en el papel viejo (allá la leyenda es UN td con
           texto plano: `_report_cromas.erb:448-457`). */
        .rel__def { font-size: 9.5px; }
        .rel__def td { border: 0; }
        /* La etiqueta y el valor en DOS celdas: así los números de una columna
           caen alineados entre sí, como en el papel viejo, en vez de arrancar
           donde termine cada etiqueta. */
        .rel__pares { width: 100%; border-collapse: collapse; }
        .rel__pares td { padding: 0; border: 0; line-height: 1.25; }
        .rel__k { white-space: nowrap; padding-right: 8px !important; }
        .rel__v { text-align: right; }
        .foot { position: fixed; bottom: -26mm; left: 0; right: 0; }
        .foot .legal { font-size: 8px; text-align: justify; }
        .foot .company { font-size: 11px; text-align: center; font-weight: bold;
                         border-bottom: 1px solid #dc3545; padding-bottom: 2px; margin-top: 4px; }
        .foot .addr { font-size: 10px; margin-top: 3px; }
        /* ── LAS FIRMAS: DOS POR FILA ──────────────────────────────────────
           Antes se repartían las N firmas en UNA fila, dividiendo el ancho por
           N: con una sola, la línea de firma cruzaba la hoja entera; con cuatro,
           cada nombre entraba en 130 px y se partía en tres renglones.
           
           Ahora van de dos en dos, y la LÍNEA tiene ancho fijo centrada en su
           celda. De ahí sale solo el comportamiento que se pide: una firma
           queda centrada en la hoja con su línea corta, dos quedan a izquierda y
           derecha, tres dejan la última a la izquierda, cuatro forman 2×2. */
        .sign { margin-top: 12px; }
        .sign td { vertical-align: bottom; text-align: center; padding: 6px 6px 0; width: 50%; }
        .sign .box { width: 230px; margin: 0 auto; }
        .sign .line { border-top: 1px solid #343a40; margin: 2px 0 3px; }
        .sign .stamp { height: 38px; }
        .sign .rel { font-size: 8.5px; color: #555; }
        .sign .who { font-weight: bold; font-size: 11px; }
        .sign .role { font-size: 8.5px; color: #555; }
        /* La variante de la hoja de cromatografía: la misma rejilla de dos por
           fila, pero dentro de una columna que ocupa poco más de la mitad de la
           hoja, así que la caja se angosta para que el nombre no se parta. */
        .sign--side { margin-top: 0; }
        .sign--side .box { width: 165px; }
        .sign--side .stamp { height: 32px; }
        .sign--side .who { font-size: 10px; }
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
@php
    /* ── DÓNDE VA LA FIRMA EN ESTA HOJA ────────────────────────────────────
       Al costado del cuadro de condiciones en cromatografía y fisicoquímico;
       debajo en el resto. Lo decide la maqueta de la hoja
       (`config/legacy_report.php`, `side_signature`), no una condición
       derivada: antes se infería por la grilla de RELACIONES —que solo la trae
       cromas— y al pedir el laboratorio que el fisicoquímico también la lleve
       al costado (2026-08-03), la inferencia dejó de alcanzar. En el papel
       viejo solo cromas la tenía al costado (`_report_cromas.erb`, col-5/col-7);
       el fisicoquímico al costado es decisión nueva del laboratorio. */
    $firmasAlLado = ! empty($pagina['firma_lado']) && ! empty($firmantes);
@endphp
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
                        {{-- El relleno era de `.75rem`, copiado del original,
                             que tenía un puñado de familias. Hoy son trece y con
                             ese alto la tabla empujaba las firmas a una segunda
                             hoja, donde quedaba una firma sola y sin contexto. --}}
                        <td class="bar" style="width:28%; vertical-align:middle; padding:5px 8px">{{ $fam['titulo'] }}</td>
                        <td style="padding:5px 8px">{{ $fam['texto'] }}</td>
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
        {{-- `nl2br` y no interpolación pelada: el papel viejo imprimía este
             párrafo en DOS líneas, la castellana y la inglesa, porque el
             certificado lo exige bilingüe. Acá el texto es un campo del
             workspace, así que el laboratorio pega las dos; sin esto el HTML
             colapsa el salto y las dos frases salen pegadas en un renglón
             corrido. El `e()` va primero: el texto lo escribe una persona. --}}
        @if ($pagina['anab'] && $acreditacion)
            <div class="legend" style="margin-top:6px">{!! nl2br(e($acreditacion)) !!}</div>
        @endif

        @if (! empty($pagina['relaciones']))
            {{-- LAS RELACIONES DE GASES, EN TRES COLUMNAS.
                 El original las apilaba en dos filas de dos columnas: totales
                 arriba, ratios abajo. Eso hace que el bloque mida lo que suman
                 las dos filas —diez renglones— aunque la columna de al lado
                 quede en blanco. Acá van en una sola fila: mide lo que la
                 columna más larga, siete, y esos tres renglones son parte de lo
                 que hace que la hoja de cromatografía siga entrando en una
                 página.

                 DOS CORRECCIONES sobre esa versión aplanada (2026-08-02), las
                 dos por lo mismo: aplanarla la había dejado peor que el
                 original a la vista.

                 · TGC(%) tenía una columna PARA ÉL SOLO — una celda de un
                   cuarto del ancho con un renglón adentro y el resto en blanco,
                   que es justo lo que hace ver un cuadro mal armado. Va con los
                   totales, que es de lo que habla (TGC/TG).
                 · Cada renglón era «etiqueta &nbsp; valor» en un mismo bloque
                   de texto, así que los números quedaban donde terminara la
                   etiqueta: 0.87 debajo de 0.29 pero arrancando dos caracteres
                   más a la derecha. Ahora la etiqueta y el valor son DOS
                   celdas, así que los números caen alineados como en el papel
                   viejo. --}}
            <div><b>RELACIONES</b></div>
            <table class="grid rel">
                <tr>
                    @foreach (['totales', 'ratios', 'porcentajes'] as $grupo)
                        @php
                            $filas = $pagina['relaciones'][$grupo];

                            // El total de combustibles cierra el bloque de
                            // totales: es el mismo dato visto en porcentaje.
                            if ($grupo === 'totales') {
                                $filas = $filas + $pagina['relaciones']['porcentaje_total'];
                            }
                        @endphp
                        <td style="width:33%; vertical-align:top">
                            <table class="rel__pares">
                                @foreach ($filas as $etiqueta => $valor)
                                    <tr>
                                        <td class="rel__k">{{ $etiqueta }}</td>
                                        <td class="rel__v">{{ $valor }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td colspan="3">
                        {{-- La leyenda de las fórmulas. Los anchos son más
                             holgados que los `col-1`/`col-3` del original
                             porque acá el cuerpo es más chico que el ancho de
                             columna: con 8% y 25% cada fórmula se partía en dos
                             renglones y la leyenda medía el doble. --}}
                        <table class="rel__def">
                            <tr>
                                <td style="width:7%"><b>TGC</b><br><b>TGC-CO</b></td>
                                <td style="width:26%">= CO+H2+CH4+C2H6+C2H4+C2H2<br>= H2+CH4+C2H6+C2H4+C2H2</td>
                                <td style="width:7%"><b>%GAS</b><br><b>TGC(%)</b></td>
                                <td style="width:22%">= GAS/(TGC-CO)x100<br>= TGC/TG x100</td>
                                <td style="width:6%"><b>TG</b><br><b>TGC</b></td>
                                <td>= Total de Gases<br>= Total de Gases Combustibles</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        @endif

        @unless ($firmasAlLado)<br>@endunless
        @if ($firmasAlLado)
            {{-- Cromatografía y fisicoquímico: condiciones a la izquierda,
                 firmas a la derecha. Ver `$firmasAlLado` más arriba. --}}
            <table><tr>
                <td style="width:42%; vertical-align:top">
                    @include('lab_management.reports.legacy._condiciones', [
                        'condiciones' => $pagina['condiciones'], 'ancho' => '100%',
                    ])
                </td>
                <td style="vertical-align:bottom">
                    @include('lab_management.reports.legacy._firmas', [
                        'firmantes' => $firmantes, 'compacto' => true,
                    ])
                </td>
            </tr></table>
        @else
            @include('lab_management.reports.legacy._condiciones', [
                'condiciones' => $pagina['condiciones'],
            ])
        @endif
    @endif

    {{-- Las firmas de quienes aprobaron el informe. El papel viejo tenía UNA
         ("Reportado por:"); el laboratorio hoy mantiene una lista con su
         relación y su cargo, y todas van al pie de cada hoja. --}}
    @unless ($firmasAlLado)
        @include('lab_management.reports.legacy._firmas', ['firmantes' => $firmantes])
    @endunless

</div>
@endforeach

</body>
</html>
