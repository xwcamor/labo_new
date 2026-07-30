<?php

namespace App\Support;

/**
 * Texto seguro para los PDF de dompdf.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL PROBLEMA                                                              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Los PDF del proyecto usan Helvetica, que es una de las 14 fuentes base de
 * PostScript y NO trae glifos griegos ni matemáticos. dompdf no sustituye la
 * fuente: dibuja un `?`. Se descubrió en el informe de ensayo, donde la unidad
 * de la resistividad volumétrica —`Ω·cm`— salía impresa como `?-cm`. Un informe
 * acreditado que dice `?` en la unidad de un ensayo no se puede entregar.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ TRANSLITERAR Y NO CAMBIAR LA FUENTE                              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Embeber una fuente Unicode completa (DejaVu) resuelve el glifo pero engorda
 * cada PDF unos 700 kB y cambia la tipografía de TODO el informe, que es el
 * formato acreditado del laboratorio. Transliterar cuesta nada y da un texto que
 * un electricista lee igual: `ohm-cm`, `<=`, `10^12`.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ DÓNDE APLICARLO                                                          │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En todo texto DINÁMICO del PDF: nombres de analito, unidades, límites,
 * normas, nombres de cliente y equipo, y los párrafos de diagnóstico. Lo que
 * está escrito en la plantilla ya se controla al escribirlo; lo que viene de la
 * base o de un archivo de idioma, no.
 *
 * OJO: la raya (`—`) y el punto medio de las unidades SÍ los dibuja Helvetica
 * (están en el rango latino de WinAnsi), así que no se tocan — el informe usa
 * la raya en todas las celdas vacías y reemplazarla por un guion pelado se
 * confundiría con un signo menos.
 */
final class PdfText
{
    /**
     * Los símbolos que Helvetica no tiene, y con qué se escriben.
     *
     * El orden importa para los de varios caracteres: se reemplaza con
     * `strtr`, que toma siempre la clave más larga que coincida.
     */
    private const REEMPLAZOS = [
        // Griegas de uso metrológico
        'Ω' => 'ohm',
        'ω' => 'ohm',
        'µ' => 'u',   // micro (signo U+00B5)
        'μ' => 'u',   // mu griega (U+03BC), indistinguible a la vista
        'Δ' => 'delta',
        'ρ' => 'rho',
        'σ' => 'sigma',
        '°' => '°',   // el grado SÍ está en WinAnsi: se deja

        // Comparadores
        '≤' => '<=',
        '≥' => '>=',
        '≠' => '!=',
        '±' => '+/-',
        '×' => 'x',
        '÷' => '/',
        '∞' => 'inf',

        // Superíndices y subíndices, que aparecen en fórmulas de gases
        // (C₂H₂, 10¹²) y en unidades (mm², cm³).
        '⁰' => '0', '¹' => '1', '²' => '2', '³' => '3', '⁴' => '4',
        '⁵' => '5', '⁶' => '6', '⁷' => '7', '⁸' => '8', '⁹' => '9',
        '⁻' => '-', '⁺' => '+',
        '₀' => '0', '₁' => '1', '₂' => '2', '₃' => '3', '₄' => '4',
        '₅' => '5', '₆' => '6', '₇' => '7', '₈' => '8', '₉' => '9',
    ];

    /** Deja el texto con solo glifos que Helvetica sabe dibujar. */
    public static function safe(?string $texto): string
    {
        if ($texto === null || $texto === '') {
            return '';
        }

        return strtr($texto, self::REEMPLAZOS);
    }
}
