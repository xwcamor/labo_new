{{--
  EL CUADRO DE CONDICIONES DE ENSAYO (norma de referencia, fecha de análisis,
  temperaturas, humedad, presión del laboratorio).

  Es un parcial por el mismo motivo que las firmas: en la hoja de cromatografía
  va en una columna al lado de la firma, y ahí el ancho fijo de 460 px no cabe.
  `$ancho` se pasa en esa hoja como `100%`.
--}}
<table class="cond" style="width:{{ $ancho ?? '460px' }}">
    @foreach ($condiciones as $etiqueta => $valor)
        <tr><td>{{ $etiqueta }}</td><td>{{ $valor }}</td></tr>
    @endforeach
</table>
