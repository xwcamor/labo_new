{{--
  LAS FIRMAS DEL INFORME VIEJO.

  Es un parcial porque el mismo bloque se dibuja en DOS lugares distintos de la
  hoja: al pie, debajo del cuadro de condiciones, en casi todas las pruebas; y
  AL LADO de ese cuadro en la hoja de cromatografía, que es la única tan densa
  que no le sobra el alto (`$compacto`). Así era en el sistema anterior:
  `_report_physicals.erb` incluía el parcial de la firma al final, y
  `_report_cromas.erb` la escribía dentro de un `col-7` a la derecha del cuadro
  de condiciones (`col-5`).

  Variables: `$firmantes` (lista) y `$compacto` (opcional).
--}}
@if (! empty($firmantes))
    {{-- DOS POR FILA. Con una sola firma la celda ocupa el ancho entero y la
         línea —de ancho fijo— queda centrada; con dos, una a cada lado; con
         tres, la última a la izquierda y la celda de la derecha vacía. --}}
    <table class="sign @if (! empty($compacto)) sign--side @endif">
        @foreach (array_chunk($firmantes, 2) as $fila)
            <tr>
                @foreach ($fila as $f)
                    <td @if (count($firmantes) === 1) style="width:100%" @endif>
                        <div class="box">
                            @if ($f['imagen'])
                                <img src="{{ $f['imagen'] }}" class="stamp">
                            @else
                                <br><br>
                            @endif
                            <div class="line"></div>
                            <div class="rel">{{ $f['relacion'] }}</div>
                            <div class="who">{{ $f['nombre'] }}</div>
                            <div class="role">{{ $f['cargo'] }}</div>
                        </div>
                    </td>
                @endforeach
                {{-- La fila impar deja su celda vacía a la derecha, para que la
                     firma sobrante quede a la IZQUIERDA y no centrada. --}}
                @if (count($fila) === 1 && count($firmantes) > 1)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>
@endif
