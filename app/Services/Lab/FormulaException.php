<?php

namespace App\Services\Lab;

use RuntimeException;

/**
 * Error de una fórmula MAL ESCRITA (sintaxis, función inexistente, entrada
 * patológica). NO representa datos faltantes: un ensayo a medio cargar es
 * normal y se resuelve devolviendo null, no lanzando.
 *
 * La distinción importa porque el editor de plantillas sí quiere ver estos
 * errores (para no dejar guardar), mientras que la hoja de trabajo del
 * analista nunca debe romperse por ellos.
 */
class FormulaException extends RuntimeException
{
    /**
     * @param string $reason Clave estable del motivo, para que el llamador
     *                       decida sin tener que leer el texto del mensaje.
     */
    public function __construct(string $message, public readonly string $reason = 'syntax')
    {
        parent::__construct($message);
    }
}
