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
        /*
            ── LA MAQUETA MODERNA ──────────────────────────────────────────
            Este es el formato NUEVO del informe. El clásico (la maqueta del
            sistema anterior) vive aparte, en `legacy/report.blade.php`, y se
            baja con el otro botón de exportar: no hay que "reproducir" nada
            acá, y por eso este archivo puede diseñarse.

            La diferencia de fondo con el clásico no es decorativa. El clásico
            encierra CADA dato en una celda con sus cuatro bordes y pone la
            etiqueta en negrita adentro: todo pesa lo mismo y hay que leerlo
            entero para encontrar un número. Acá manda la jerarquía: la
            etiqueta es chica, gris y en versalita; el valor es el que se lee;
            y el papel se ordena con espacio en blanco y una línea fina, no con
            una rejilla. Las reglas verticales se eliminan a propósito — son
            las que hacen que una tabla parezca una planilla de cálculo.

            ── UN DOCUMENTO, NO TRES IMPRESIONES ENGRAPADAS ────────────────
            El clásico repite la ficha del cliente Y la del equipo —unas
            treinta líneas— en cada hoja, y vuelve a firmar cada hoja. Eso
            venía de que allá cada ensayo era una impresión independiente. Acá
            no: la ficha del cliente y la del equipo van UNA vez, en la primera
            página, y las siguientes llevan una banda de una línea que
            identifica la hoja (informe · muestra · cliente · equipo). La ISO
            17025 pide identificación única en cada página, no la ficha
            completa; el número de informe, la muestra y el código de
            verificación están en todas.

            Las firmas van UNA vez, al cierre. Un documento firmado cuatro
            veces no está más firmado.

            Lo que NO cambia, porque es normativo: el sello de acreditación
            solo en páginas cuyos métodos están dentro del alcance —por eso las
            secciones se agrupan por ese estado y nunca se mezclan en una
            hoja—, el descargo en todas, y el veredicto leído del resultado
            congelado.
        */

        @page { margin: 20px 26px 42px 26px; }

        body { font-family: Helvetica; font-size: 8pt; color: #101828; margin: 0; }
        p { margin: 0 0 2px 0; }

        /* Salto de página entre ensayos: el `.alwaysbreak` del informe viejo.
           No se pone en la primera hoja, o el PDF abre con una página en blanco. */
        .brk { page-break-before: always; }

        /* ── Membrete: identidad a la izquierda, el informe a la derecha ───
           El clásico centra el número dentro de una banda gris con borde
           negro. Acá el número vive en una tarjeta a la derecha, que es donde
           lo busca quien tiene el papel en la mano, y la identidad del
           laboratorio queda sola a la izquierda sin competirle. */
        .lh { width: 100%; border-collapse: collapse; }
        .lh td { vertical-align: top; padding: 0; }
        .lh__logo { width: 46%; }
        .lh__logo img { max-height: 46px; }
        .lh__name { font-size: 14pt; font-weight: bold; color: #1B2A3A; }
        .lh__addr { font-size: 6.5pt; color: #5B6672; }
        .lh__acc { width: 16%; text-align: center; }
        .lh__acc img { max-height: 54px; }

        /* La tarjeta del número de informe. */
        .lh__doc { width: 38%; text-align: right; }
        .doc {
            display: inline-block; background: #F7F9FB; border: 1px solid #E3E8EE;
            border-left: 3px solid #0E7490; padding: 4px 9px 5px 9px; text-align: left;
        }
        .doc__kicker {
            font-size: 5.5pt; color: #5B6672; letter-spacing: 1.1px; text-transform: uppercase;
        }
        .doc__num { font-size: 12pt; font-weight: bold; color: #1B2A3A; }
        .doc__meta { font-size: 6pt; color: #5B6672; }

        /* La regla de marca que cierra el membrete. Un solo trazo: es lo que
           separa la cabecera del contenido sin dibujar una caja. */
        .rule { height: 2px; background: #1B2A3A; margin: 5px 0 9px 0; font-size: 0; }

        /* ── Título de sección: versalita chica + acento corto ─────────────
           Reemplaza al `.blk` en negrita del clásico. La barrita de color hace
           el trabajo que allá hacía el fondo gris, con una fracción de la
           tinta y sin encajonar el texto. */
        .blk {
            font-size: 6.5pt; font-weight: bold; color: #1B2A3A;
            letter-spacing: 1.2px; text-transform: uppercase;
            border-bottom: 1px solid #E3E8EE; padding-bottom: 2px;
            margin: 9px 0 4px 0;
        }
        .blk--first { margin-top: 0; }

        /* ── Resumen del veredicto (solo la primera página) ────────────────
           Esto no existe en el clásico y es lo primero que quiere saber quien
           abre el informe: cuántos ensayos se corrieron y si alguno salió
           fuera de norma. Las fichas se leen de un vistazo; el detalle está
           en las páginas siguientes. */
        .sum { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin: 0 0 4px -5px; }
        .sum td { width: 25%; vertical-align: top; }
        .card { background: #F7F9FB; border: 1px solid #E3E8EE; padding: 5px 8px 6px 8px; }
        .card--ok   { background: #F0FAF5; border-color: #BFE6D2; }
        .card--bad  { background: #FEF3F2; border-color: #FBD3CE; }
        .card__k {
            font-size: 5.5pt; color: #5B6672; letter-spacing: 1px; text-transform: uppercase;
        }
        .card__v { font-size: 12pt; font-weight: bold; color: #1B2A3A; }
        .card__v--ok  { color: #0E7A4B; }
        .card__v--bad { color: #B42318; }
        .card__note { font-size: 5.5pt; color: #5B6672; }

        /* ── Datos: etiqueta arriba, valor abajo, sin rejilla ──────────────
           Misma estructura de filas que el clásico (pares etiqueta/valor),
           pero la etiqueta deja de ser una celda gris con borde: es un rótulo
           en versalita sobre el dato. Solo queda una línea horizontal fina
           para guiar la lectura a lo ancho. */
        table.grid { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
        table.grid th, table.grid td {
            border: 0; border-bottom: 1px solid #EDF0F4;
            padding: 3px 8px 4px 0; text-align: left; vertical-align: top;
        }
        table.grid th {
            font-weight: normal; font-size: 5.5pt; color: #5B6672;
            letter-spacing: 0.9px; text-transform: uppercase;
            width: 12%; padding-top: 4px;
        }
        table.grid td { width: 21%; color: #101828; }
        table.grid tr:last-child th, table.grid tr:last-child td { border-bottom: 0; }

        /* ── Banda de continuación (páginas 2 en adelante) ─────────────────
           Reemplaza a las dos fichas repetidas del clásico. Una línea: quién
           es el cliente, de qué equipo y de qué muestra. Con eso una hoja
           suelta se identifica, que es lo que pide la norma. */
        table.run { width: 100%; border-collapse: collapse; font-size: 6.5pt; margin-bottom: 8px; }
        table.run td {
            border: 0; border-top: 1px solid #EDF0F4; border-bottom: 1px solid #EDF0F4;
            padding: 3px 10px 3px 0; vertical-align: top;
        }
        .run__k { color: #5B6672; letter-spacing: 0.9px; text-transform: uppercase; font-size: 5.5pt; }
        .run__v { color: #101828; font-size: 7pt; }

        /* ── Resultados ───────────────────────────────────────────────────
           Encabezado en el azul de marca con texto blanco, filas cebradas y
           SOLO líneas horizontales. El clásico usa borde negro en las cuatro
           caras de cada celda; eso es lo que le da el aire de planilla.

           Y la columna ÍTEM ya no está: numerar las filas es una costumbre de
           libro contable, no un dato del ensayo. El sujeto de la fila es el
           ENSAYO, así que va primero y en cuerpo legible, con la norma del
           método como rótulo chico debajo — allá ocupaba una columna entera
           repitiendo "ASTM D3612 - Método C" once veces. */
        table.res { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
        table.res th, table.res td {
            border: 0; border-bottom: 1px solid #EDF0F4; padding: 4px 6px;
        }
        table.res th {
            background: #1B2A3A; color: #FFFFFF; font-weight: bold; text-align: center;
            font-size: 6pt; letter-spacing: 0.6px; text-transform: uppercase;
            border-bottom: 0;
        }
        table.res th.l { text-align: left; }
        table.res tr.zebra td { background: #F7F9FB; }
        .res__family {
            font-size: 9.5pt; font-weight: bold; color: #1B2A3A;
            border-left: 3px solid #0E7490; padding: 1px 0 1px 7px; margin-bottom: 5px;
        }
        /* El nombre del ensayo, y debajo la norma del método con la que se
           corrió. Dos jerarquías en una celda en vez de dos columnas. */
        .res__name { font-size: 8pt; }
        .res__meth { font-size: 6pt; color: #5B6672; }

        /* El veredicto como etiqueta, no como una palabra entre paréntesis
           colgada del número. Es la columna que el informe viejo no tenía: allá
           había que mirar el color, y el color no sobrevive a una fotocopia. */
        .pill {
            font-size: 5.5pt; font-weight: bold; letter-spacing: 0.7px;
            text-transform: uppercase; padding: 2px 5px; white-space: nowrap;
        }
        .pill--ok   { background: #ECFDF3; color: #0E7A4B; border: 1px solid #BFE6D2; }
        .pill--bad  { background: #FEF3F2; color: #B42318; border: 1px solid #FBD3CE; }
        .pill--none { background: #F2F4F7; color: #5B6672; border: 1px solid #E3E8EE; }
        .c { text-align: center; }
        /* El valor medido es el dato que se busca: alineado a la derecha para
           poder comparar las columnas de números de un vistazo, y sin fondo
           gris — el énfasis lo da el cuerpo, no un recuadro. */
        .res__value { text-align: right; font-weight: bold; font-size: 8.5pt; }

        /* Fuera de norma: rojo Y la palabra. El color solo no sobrevive a una
           fotocopia en blanco y negro, y este papel se fotocopia. */
        .out { color: #B42318; }
        /* Sin criterio: ni negro ni rojo. No se comparó contra nada, y eso no
           puede leerse como conforme. */
        .none { color: #5B6672; font-weight: normal; }
        .flag { font-size: 6pt; font-weight: normal; }
        /* De qué cuadro salió el límite. Chico, debajo del número: el cliente
           lee el número; quien audita necesita saber contra qué se lo juzgó. */
        .crit { font-size: 6pt; color: #5B6672; }
        .sup { font-size: 6pt; }

        /* ── Notas al pie de la página de ensayo ─────────────────────────── */
        .foot { margin-top: 6px; font-size: 7pt; line-height: 1.35; }
        .foot__iso { font-size: 6.5pt; font-style: italic; color: #5B6672; }
        .foot__warn {
            margin-top: 4px; color: #B45309; font-size: 7pt;
            background: #FFFAEB; border-left: 3px solid #F5C86B; padding: 3px 7px;
        }

        /* ── Condiciones del ensayo: una banda horizontal bajo su tabla ────
           Antes era una lista vertical de seis renglones metida en una columna
           angosta del pie, lejos de la tabla que describía. Son las condiciones
           de ESA corrida —dos ensayos de la misma muestra se corren días
           distintos—, así que van pegadas a su propia tabla y a lo ancho, que
           es donde hay lugar. */
        table.cnd {
            width: 100%; border-collapse: collapse; font-size: 6.5pt;
            background: #F7F9FB; margin-top: 5px;
        }
        table.cnd td {
            border: 0; border-top: 1px solid #E3E8EE; border-bottom: 1px solid #E3E8EE;
            padding: 3px 8px; vertical-align: top;
        }
        .cnd__k { color: #5B6672; letter-spacing: 0.8px; text-transform: uppercase; font-size: 5.5pt; }
        .cnd__v { color: #101828; font-size: 7pt; }

        /* ── Cierre: firmas · verificación ────────────────────────────────
           Una sola vez, al final del documento. */
        .strip { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .strip td { vertical-align: top; }
        .strip__sign { width: 78%; }
        .strip__qr { width: 22%; text-align: center; }

        table.sign { width: 100%; border-collapse: collapse; }
        table.sign td { text-align: center; padding: 0 5px; vertical-align: bottom; border: 0; }
        .sign__lead { font-size: 6pt; color: #5B6672; letter-spacing: 0.9px; text-transform: uppercase; padding-bottom: 24px; }
        .sign__line { border-top: 1px solid #98A2B3; margin: 0 0 2px 0; }
        .sign__rel { font-size: 5.5pt; color: #5B6672; letter-spacing: 0.7px; text-transform: uppercase; }
        .sign__name { font-size: 7.5pt; font-weight: bold; }
        .sign__title { font-size: 6pt; color: #5B6672; }

        .qr { background: #F7F9FB; border: 1px solid #E3E8EE; padding: 4px 2px 3px 2px; }
        .qr img { width: 58px; height: 58px; }
        .sign__img { display: block; max-height: 34px; margin: 0 auto 1px; }
        .qr__code { font-size: 6.5pt; font-weight: bold; color: #1B2A3A; }
        .qr__hint { font-size: 5pt; color: #5B6672; line-height: 1.2; }

        /* ── Última página: análisis de resultados ─────────────────────────
           El clásico lo mete en una tabla de dos columnas con bordes, con la
           familia en una celda gris. Acá cada familia es un bloque con su
           título y su párrafo: se lee como un texto, que es lo que es. */
        .an__block { margin-bottom: 9px; }
        .an__fam {
            font-size: 6.5pt; font-weight: bold; color: #1B2A3A;
            letter-spacing: 1.1px; text-transform: uppercase;
            border-left: 3px solid #0E7490; padding-left: 7px; margin-bottom: 3px;
        }
        .an__body {
            font-size: 8pt; line-height: 1.45; text-align: justify;
            padding-left: 10px;
        }
        .an__empty { color: #5B6672; font-style: italic; }
        .an__edited { margin-top: 3px; font-size: 6.5pt; color: #5B6672; font-style: italic; padding-left: 10px; }
        .notes {
            margin-top: 10px; border: 1px solid #E3E8EE; border-left: 3px solid #B45309;
            background: #FFFAEB; padding: 5px 8px; font-size: 7pt;
        }
        .scope { margin-top: 8px; font-size: 6.5pt; color: #5B6672; font-style: italic; }
        .empty {
            background: #F7F9FB; border: 1px solid #E3E8EE;
            padding: 16px; text-align: center; color: #5B6672;
        }

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
        /* El descargo legal va en el pie de TODAS las páginas, como en el
           informe anterior. No es redundancia: el informe se reparte una hoja
           por ensayo y cada una se fotocopia, se escanea y se adjunta a un
           correo por separado. Una hoja suelta sin el descargo es una hoja
           que afirma más de lo que el laboratorio firmó. */
        .disclaimer {
            position: fixed; bottom: -18px; left: 0; right: 0;
            font-size: 5.5pt; color: #666666; text-align: justify; line-height: 1.25;
        }
    </style>
</head>
<body>

{{-- El código de muestra, el de verificación y —desde el lienzo— el número de
     página se repiten en TODAS las páginas: una hoja suelta de un informe de
     seis páginas tiene que poder identificarse sola. --}}
<div class="pagenum__sample">{{ $sample['code'] }}</div>
<div class="pagenum__code">{{ $verifyCode }}</div>
@if ($letterhead['disclaimer'])
    <div class="disclaimer">{{ $letterhead['disclaimer'] }}</div>
@endif

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

    // La placa entera, terciario incluido: "500 / 220 / 33". La arma el modelo
    // del equipo; acá solo se elige mostrarla o dejar la raya.
    // El informe EMITIDO imprime desde su snapshot, y los congelados antes de
    // que existiera el terciario no traen la placa armada: en ese caso se
    // rearma con lo que el snapshot sí guardó, en vez de imprimir una raya
    // donde el papel original decía una tensión.
    $placa = fn (array $vs) => implode(' / ', array_map(
        fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'),
        array_filter($vs, fn ($v) => $v !== null && $v !== ''),
    )) ?: null;

    $tension = $eqFaltante ? null : (($equipment['voltage'] ?? null)
        ?: $placa([$equipment['voltage_hv'] ?? null, $equipment['voltage_lv'] ?? null]));

    $potencia = $eqFaltante ? null : (($equipment['power'] ?? null)
        ?: $placa([$equipment['power_mva'] ?? null]));

    $aceite = $eqFaltante ? null : implode(' ', array_filter([
        $equipment['oil_volume'] ?? null,
        $equipment['oil_volume_unit'] ?? null,
    ]));

    // "En operación" se guarda como clave y se traduce con el MISMO archivo de
    // idioma que usa la ficha del equipo: una sola redacción para los dos.
    $enServicio = $eq('service_state')
        ? __('equipment.service_states.' . $equipment['service_state'])
        : null;

    // Las páginas del informe. El clásico gastaba una hoja por ensayo: tres
    // ensayos de cuatro filas cada uno salían en tres páginas con dos tercios
    // en blanco, porque cada una repetía las dos fichas completas. Acá las
    // secciones se AGRUPAN y fluyen.
    //
    // El corte no es arbitrario: se agrupan las CONSECUTIVAS que comparten el
    // estado de acreditación. Es la única restricción real de maquetado que
    // tiene este papel — el sello del organismo acreditador se estampa por
    // página, así que una hoja no puede llevar a la vez un método dentro del
    // alcance y uno fuera: el sello afirmaría una acreditación que el
    // laboratorio no tiene para ese ensayo. Agrupando por ese estado, el sello
    // sigue siendo verdadero incluso si dompdf parte el grupo en dos hojas.
    //
    // El segundo corte es de espacio. DomPDF no mide antes de maquetar, así que
    // el presupuesto de filas es un tope CONSERVADOR, no un cálculo: si un grupo
    // se pasara, dompdf lo parte solo y la hoja de continuación sale sin
    // membrete —lo cual ya pasaba con una tabla larga en el formato anterior—,
    // pero con el tope no llega a ese caso. La primera página lleva mucho menos
    // porque arriba tiene las fichas del cliente y del equipo.
    $TOPE_PRIMERA = 9;
    $TOPE_RESTO   = 24;

    $paginas = [];
    $grupo = null;
    $filasEnGrupo = 0;

    foreach ($sections as $seccion) {
        $acreditada = (bool) ($seccion['accredited'] ?? false);
        $filas = count($seccion['rows'] ?? []) + 2;   // +2: el título y la banda de condiciones
        $tope = $paginas === [] ? $TOPE_PRIMERA : $TOPE_RESTO;

        $cambiaAcreditacion = $grupo !== null && $grupo['accredited'] !== $acreditada;
        $noEntra = $grupo !== null && $filasEnGrupo > 0 && $filasEnGrupo + $filas > $tope;

        if ($grupo === null || $cambiaAcreditacion || $noEntra) {
            if ($grupo !== null) {
                $paginas[] = $grupo;
            }

            $grupo = ['kind' => 'test', 'accredited' => $acreditada, 'sections' => []];
            $filasEnGrupo = 0;
        }

        $grupo['sections'][] = $seccion;
        $filasEnGrupo += $filas;
    }

    if ($grupo !== null) {
        $paginas[] = $grupo;
    }

    $paginas[] = $sections === [] ? ['kind' => 'empty'] : ['kind' => 'analysis'];

    // El recuento del veredicto para las fichas de la primera página. Se cuenta
    // sobre el estado CONGELADO de cada resultado, el mismo que se imprime: si
    // la ficha dijera una cosa y la tabla otra, el papel se contradice.
    $totalEnsayos = 0;
    $fueraDeNorma = 0;
    $sinCriterio  = 0;

    foreach ($sections as $seccion) {
        foreach ($seccion['rows'] ?? [] as $fila) {
            $totalEnsayos++;

            if (($fila['status'] ?? null) === 'out_of_spec') {
                $fueraDeNorma++;
            } elseif (($fila['status'] ?? null) === null || ($fila['status'] ?? null) === 'no_spec') {
                $sinCriterio++;
            }
        }
    }
@endphp

@foreach ($paginas as $indice => $pagina)
<div class="{{ $indice > 0 ? 'brk' : '' }}">

    {{-- ── Membrete ────────────────────────────────────────────────────────
         El sello del organismo de acreditación NO va en todas las páginas: va
         en las que de verdad llevan un método dentro del alcance acreditado.
         La página del análisis nunca lo lleva —una opinión o interpretación
         queda fuera del alcance—, y una página de resultados corridos con un
         método NO acreditado tampoco. Estampar el sello ahí no es un detalle de
         maquetado: es afirmar por escrito una acreditación que el laboratorio
         no tiene para ese ensayo.

         El dato llega resuelto desde el payload (`section.accredited`): la
         plantilla no interpreta rótulos. --}}
    <table class="lh">
        <tr>
            <td class="lh__logo">
                @if ($letterhead['logo'])
                    <img src="{{ $letterhead['logo'] }}" alt="">
                    @if ($letterhead['name'])
                        <div class="lh__addr">{{ $letterhead['name'] }}</div>
                    @endif
                @else
                    <div class="lh__name">{{ $letterhead['name'] ?? '' }}</div>
                @endif
                <div class="lh__addr">{{ $letterhead['address'] }}</div>
            </td>
            <td class="lh__acc">
                @if (($letterhead['accreditation_logo'] ?? null) && ! empty($pagina['accredited']))
                    <img src="{{ $letterhead['accreditation_logo'] }}" alt="">
                @endif
            </td>
            {{-- El número de informe vive acá, en su tarjeta, y no centrado en
                 una banda: es lo que busca primero quien tiene el papel en la
                 mano, junto con la muestra y la fecha de emisión. --}}
            <td class="lh__doc">
                <div class="doc">
                    <div class="doc__kicker">{{ __('reports.header_title') }}</div>
                    <div class="doc__num">{{ $sample['report_number'] }}</div>
                    <div class="doc__meta">
                        {{ __('reports.sample') }} {{ $sample['code'] }}
                        &nbsp;·&nbsp; {{ $fecha($sample['issued_at'] ?? $generatedAt) }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    {{-- ── Resumen del veredicto (solo la primera hoja) ──────────────────
         No está en el formato clásico y es lo primero que se pregunta quien
         abre el informe. Se arma con el estado congelado de cada resultado,
         el mismo que imprimen las tablas de las páginas siguientes. --}}
    @if ($indice === 0 && $totalEnsayos > 0)
        <table class="sum">
            <tr>
                <td>
                    <div class="card">
                        <div class="card__k">{{ __('reports.sum_tests') }}</div>
                        <div class="card__v">{{ $totalEnsayos }}</div>
                        <div class="card__note">{{ trans_choice('reports.sum_tests_note', count($sections), ['count' => count($sections)]) }}</div>
                    </div>
                </td>
                <td>
                    <div class="card {{ $fueraDeNorma > 0 ? 'card--bad' : 'card--ok' }}">
                        <div class="card__k">{{ __('reports.sum_out') }}</div>
                        <div class="card__v {{ $fueraDeNorma > 0 ? 'card__v--bad' : 'card__v--ok' }}">{{ $fueraDeNorma }}</div>
                        <div class="card__note">
                            {{ $fueraDeNorma > 0 ? __('reports.sum_out_note') : __('reports.sum_ok_note') }}
                        </div>
                    </div>
                </td>
                <td>
                    <div class="card">
                        <div class="card__k">{{ __('reports.sum_no_spec') }}</div>
                        <div class="card__v">{{ $sinCriterio }}</div>
                        <div class="card__note">{{ __('reports.sum_no_spec_note') }}</div>
                    </div>
                </td>
                <td>
                    <div class="card">
                        <div class="card__k">{{ __('reports.sampled_at') }}</div>
                        <div class="card__v" style="font-size: 9pt">{{ $fecha($sample['sampled_at'] ?? null) }}</div>
                        <div class="card__note">{{ $o($sample['sampling_point'] ?? null) }}</div>
                    </div>
                </td>
            </tr>
        </table>
    @endif

    {{-- ── Páginas 2 en adelante: banda de continuación ──────────────────
         El clásico repite acá las dos fichas completas —cliente y equipo, unas
         treinta líneas— porque allá cada ensayo era una impresión aparte. Este
         informe es UN documento: las fichas van una vez, en la primera hoja, y
         las siguientes llevan esta línea. Lo que la norma pide de una hoja
         suelta es poder identificarla, y con el informe, la muestra, el cliente
         y el equipo queda identificada. --}}
    @if ($indice > 0)
        <table class="run">
            <tr>
                <td style="width: 34%">
                    <div class="run__k">{{ __('reports.customer_name') }}</div>
                    <div class="run__v">{{ $o($customer['name']) }}</div>
                </td>
                <td style="width: 22%">
                    <div class="run__k">{{ __('reports.tag') }}</div>
                    <div class="run__v">{{ $o($eq('tag') ?? $eq('serial')) }}</div>
                </td>
                <td style="width: 22%">
                    <div class="run__k">{{ __('reports.sample') }}</div>
                    <div class="run__v">{{ $sample['code'] }}</div>
                </td>
                <td style="width: 22%">
                    <div class="run__k">{{ __('reports.sampled_at') }}</div>
                    <div class="run__v">{{ $fecha($sample['sampled_at']) }}</div>
                </td>
            </tr>
        </table>
    @endif

    @if ($indice === 0)
    {{-- ── Información del cliente ─────────────────────────────────────── --}}
    <div class="blk {{ $totalEnsayos > 0 ? '' : 'blk--first' }}">{{ __('reports.customer_info') }}</div>
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
            <td>{{ $o($potencia) }}</td>
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
            {{-- La marca COMERCIAL del aceite (Nynas, Shell, Ergon), distinta
                 del TIPO (mineral, silicona, vegetal) que va más arriba.
                 Estaba clavada en una raya con un comentario que decía que el
                 esquema no la guardaba: sí la guarda —`equipment.oil_brand`, y
                 el informe clásico ya la imprimía—, así que el papel nuevo
                 mostraba un hueco donde había un dato cargado. --}}
            <th>{{ __('reports.oil_brand') }}</th>
            <td>{{ $o($eq('oil_brand')) }}</td>
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
    @endif

    @if ($pagina['kind'] === 'test')

        {{-- ── Resultados de los ensayos de esta página ────────────────── --}}
        <div class="blk {{ $indice === 0 ? '' : 'blk--first' }}">{{ __('reports.results_title') }}</div>

        @foreach ($pagina['sections'] as $s)
        {{-- El nombre del ensayo va FUERA de la tabla, como título del bloque:
             adentro obligaba a una fila de cabecera falsa con seis columnas
             fusionadas y hacía que la tabla arrancara con una banda gris. --}}
        <div class="res__family" style="{{ $loop->first ? '' : 'margin-top: 13px' }}">{{ \Illuminate\Support\Str::upper($s['test'] ?? '') }}</div>

        <table class="res">
            <thead>
                <tr>
                    <th class="l" style="width: 34%">{{ __('reports.col_test') }}</th>
                    <th style="width: 10%">{{ __('reports.col_unit') }}</th>
                    <th style="width: 22%">{{ __('reports.col_limit') }}</th>
                    <th style="width: 15%">{{ __('reports.col_result') }}</th>
                    <th style="width: 19%">{{ __('reports.col_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($s['rows'] as $row)
                    {{-- Cebra: la fila alterna reemplaza a las reglas
                         verticales para guiar el ojo a lo ancho. --}}
                    <tr class="{{ $loop->index % 2 === 1 ? 'zebra' : '' }}">
                        {{-- El ensayo, y debajo la norma del MÉTODO con su marca
                             de acreditación: la que el analista eligió al
                             correrlo, o sea la que de verdad se usó. No es la
                             norma del CRITERIO — esa va con las condiciones. --}}
                        <td>
                            <div class="res__name">{{ $o($row['analyte']) }}</div>
                            @if ($row['method'] ?? null)
                                <div class="res__meth">{{ $row['method'] }}@if (!empty($row['accreditation']))<sup class="sup">({{ $row['accreditation'] }})</sup>@endif</div>
                            @endif
                        </td>
                        <td class="c">{{ $o($row['unit']) }}</td>
                        <td class="c">
                            {{ $row['limit'] }}
                            @if ($row['criterion'])
                                <div class="crit">{{ $row['criterion'] }}</div>
                            @endif
                        </td>
                        {{-- El número, sin la palabra colgada al lado: el
                             veredicto tiene su propia columna. --}}
                        <td class="res__value">
                            <span class="{{ $row['status'] === 'out_of_spec' ? 'out' : ($row['status'] === null ? 'none' : '') }}">{{ $row['value'] }}</span>
                        </td>
                        <td class="c">
                            @if ($row['status'] === 'out_of_spec')
                                <span class="pill pill--bad">{{ __('reports.out_of_spec') }}</span>
                            @elseif ($row['status'] === null)
                                <span class="pill pill--none">{{ __('reports.no_criterion') }}</span>
                            @else
                                <span class="pill pill--ok">{{ __('reports.in_spec') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ── Condiciones de ESTA corrida, pegadas a su tabla ───────────
             Son las de la hoja de bancada que produjo estos resultados. Van acá
             y no en el pie porque dos ensayos de la misma muestra se corren
             días distintos y con el laboratorio en otro estado: puestas en el
             pie de la página, no se sabía a qué tabla pertenecían. --}}
        @php
            $cnd = [
                ['k' => __('reports.cond_standard'),     'v' => $o($s['conditions']['standard'])],
                ['k' => __('reports.cond_run_date'),     'v' => $fecha($s['conditions']['run_date'])],
                ['k' => __('reports.cond_sample_temp'),  'v' => $temp($s['conditions']['sample_temp_c'], ' °C')],
                ['k' => __('reports.cond_lab_temp'),     'v' => $temp($s['conditions']['ambient_temp_c'], ' °C')],
                ['k' => __('reports.cond_lab_humidity'), 'v' => $temp($s['conditions']['ambient_humidity'], ' %HR')],
            ];

            // La presión solo si hay dato: un renglón fijo en raya sugiere que
            // alguien olvidó cargarla, cuando puede ser que ese ensayo no la
            // registre. La cromatografía sí —un volumen de gas depende de la
            // presión a la que se midió— y es parte de su trazabilidad.
            if ($s['conditions']['lab_pressure_hpa'] !== null) {
                $cnd[] = ['k' => __('reports.cond_lab_pressure'), 'v' => $temp($s['conditions']['lab_pressure_hpa'], ' hPa')];
            }
        @endphp
        <table class="cnd">
            <tr>
                @foreach ($cnd as $par)
                    <td style="width: {{ round(100 / count($cnd), 2) }}%">
                        <div class="cnd__k">{{ $par['k'] }}</div>
                        <div class="cnd__v">{{ $par['v'] }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
        @endforeach

        {{-- ── Notas al pie ─────────────────────────────────────────────
             Las marcas y advertencias se resuelven sobre TODAS las secciones de
             la hoja: la leyenda explica los superíndices que de verdad
             aparecieron en ella, ni uno más. --}}
        @php
            $secciones     = $pagina['sections'];
            $notasAlPie    = array_filter(array_map(fn ($x) => $x['footnote'] ?? null, $secciones));
            $hayAcreditado = (bool) array_filter($secciones, fn ($x) => ! empty($x['accredited']));
            $haySinAcred   = (bool) array_filter($secciones, fn ($x) => ! empty($x['not_accredited']));
            $sinCriterioEnHoja = array_sum(array_map(fn ($x) => (int) ($x['no_criteria'] ?? 0), $secciones));
        @endphp
        @php $s = ['accredited' => $hayAcreditado, 'not_accredited' => $haySinAcred, 'no_criteria' => $sinCriterioEnHoja]; @endphp
        <div class="foot">
            @foreach ($notasAlPie as $nota)
                {{ $nota }}<br>
            @endforeach
            {{-- Cada marca se explica solo si aparece en ESTA hoja: la leyenda
                 traduce el superíndice de la columna NORMA, y una hoja donde
                 todos los métodos están acreditados no tiene por qué explicar
                 qué significa "no acreditado". --}}
            @if ($s['accredited'])
                {{ __('reports.foot_accredited') }}<br>
            @endif
            @if ($s['not_accredited'])
                {{ __('reports.foot_not_accredited') }}<br>
            @endif
            <span class="foot__iso">
                {{-- El párrafo del certificado acompaña al sello y sigue la
                     misma regla: solo donde hay un método dentro del alcance.
                     Es además un DATO del laboratorio (organismo, certificado y
                     alcance), no un texto de la plantilla: el número vence y
                     otro laboratorio se acredita con otro organismo. Vacío no
                     imprime nada — un laboratorio sin acreditar no puede emitir
                     un papel que insinúe que sí. --}}
                @if ($s['accredited'] && ($letterhead['accreditation_note'] ?? null))
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
        <div class="blk blk--first">{{ __('reports.analysis_title') }}</div>

        {{-- Cada familia es un bloque con su título y su párrafo, no una fila
             de una tabla con bordes: el análisis es un texto y se lee como
             texto. Justificado y con interlínea holgada, que es lo que
             distingue un informe de una planilla. --}}
        @foreach ($analysis as $fila)
            <div class="an__block">
                <div class="an__fam">{{ \Illuminate\Support\Str::upper($fila['label'] ?? '') }}</div>
                <div class="an__body">
                    @if ($fila['body'])
                        {!! nl2br(e($fila['body'])) !!}
                    @else
                        <span class="an__empty">{{ __('reports.analysis_empty') }}</span>
                    @endif
                </div>
                @if ($fila['body'] && $fila['edited'])
                    <div class="an__edited">{{ __('reports.analysis_edited') }}</div>
                @endif
            </div>
        @endforeach

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
            {{-- El descargo legal ya NO se repite acá: está en el pie de todas
                 las páginas, que es donde el informe anterior lo tenía. --}}
            {{ __('reports.generated_by', ['name' => $generatedBy ?? '—']) }}
        </div>

    @else

        <div class="empty">{{ __('reports.no_results') }}</div>

    @endif

    {{-- ── Cierre del documento: firmas + verificación ───────────────────
         UNA vez, en la última página. El informe viejo firmaba cada hoja porque
         cada hoja era una impresión independiente; un documento firmado cuatro
         veces no está más firmado. El QR verifica el documento entero, no la
         hoja, y por eso acompaña a la firma en el cierre. Lo que va en TODAS las
         páginas es el código de verificación, chico, en el margen inferior:
         identificación única por página, que es lo que pide la ISO 17025. --}}
    @if ($indice === count($paginas) - 1)
    <table class="strip">
        <tr>
            <td class="strip__sign">
                <table class="sign">
                    <tr>
                        <td class="sign__lead">{{ __('reports.reported_by') }}</td>
                        @forelse ($signers as $signer)
                            <td>
                                {{-- La imagen se estampa solo si existe. Sin
                                     ella queda la línea para firmar a mano, que
                                     es lo correcto: un informe no debe insinuar
                                     una firma que nadie puso. --}}
                                @if ($signer->stamp ?? null)
                                    <img class="sign__img" src="{{ $signer->stamp }}" alt="">
                                @endif
                                <div class="sign__line"></div>
                                <div class="sign__rel">{{ __('reports.relation.' . $signer->relation, [], null) }}</div>
                                <div class="sign__name">{{ $signer->printedName() }}</div>
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
    @endif

</div>
@endforeach

</body>
</html>
