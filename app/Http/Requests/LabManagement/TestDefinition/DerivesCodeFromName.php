<?php

namespace App\Http\Requests\LabManagement\TestDefinition;

use Illuminate\Support\Str;

/**
 * El CÓDIGO de la prueba sale del NOMBRE. No se teclea.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ NO SE ESCRIBE A MANO                                             │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El código es la clave estable del sistema: los cuadros de límites, el mapa de
 * analitos, la política de control de calidad y las cartas de control se atan a
 * él. Dejarlo escribir invita a dos filas para la misma prueba —«numero_acido» y
 * «num_acido»—, y a partir de ahí el cuadro de límites resuelve para una y la
 * otra sale sin criterio, sin que nada avise.
 *
 * `Str::slug($nombre, '_')` es la misma regla que ya usaba el importador de las
 * 29 pruebas del sistema anterior, y reproduce sus 29 códigos al carácter
 * —verificado uno por uno—: «Número Ácido» → `numero_acido`, «Factor De
 * Potencia 25º» → `factor_de_potencia_25o`, «Azufre 62535 (48 horas)» →
 * `azufre_62535_48_horas`. Por eso dar de alta una prueba a mano y volver a
 * correr el importador no termina en dos registros.
 *
 * Se deriva ACÁ y no solo en la pantalla porque un campo de solo lectura no es
 * una validación: el formulario se puede saltear con un POST directo. Lo que
 * venga en `code` se descarta.
 */
trait DerivesCodeFromName
{
    protected function mergeCodeFromName(): void
    {
        if (blank($this->input('name'))) {
            // Sin nombre no hay código que derivar: que falle la regla de
            // `name`, que dice lo que de verdad pasó, en vez de un error sobre
            // un campo que el usuario no puede llenar.
            return;
        }

        $this->merge(['code' => Str::slug((string) $this->input('name'), '_')]);
    }
}
