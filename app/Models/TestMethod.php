<?php

namespace App\Models;

use App\Traits\BelongsToTenantOrGlobal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TestMethod — cómo se mide un parámetro.
 *
 * Existe para deshacer una duplicación del sistema anterior: allá `rig` y
 * `rigep` eran dos parámetros distintos, y `f25`, `f90` y `f100` otros tres. En
 * realidad son DOS parámetros medidos de varias maneras:
 *
 *   Rigidez dieléctrica  → ASTM D877 (electrodos planos, 2.54 mm)
 *                        → ASTM D1816 (semiesféricos, 1.0 o 2.0 mm)
 *   Factor de potencia   → a 25 °C, a 90 °C, a 100 °C
 *
 * Con esto el informe muestra UNA fila por parámetro con el método al lado, en
 * vez de dos o tres filas que compiten; y el límite se busca por (parámetro,
 * método), así que la separación de electrodos deja de ser un supuesto.
 *
 * `conditions` guarda lo que CAMBIA el valor esperado (el gap, la temperatura).
 * El sistema anterior no lo registraba, y por eso hoy no se sabe con qué
 * separación se midieron los históricos de rigidez.
 */
class TestMethod extends Model
{
    use SoftDeletes;
    use BelongsToTenantOrGlobal;

    protected $table = 'test_methods';
    protected $guarded = [];

    protected $casts = [
        'conditions' => 'array',
        'is_active'  => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function analyte(): BelongsTo
    {
        return $this->belongsTo(Analyte::class);
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(Standard::class);
    }

    /** Una condición del ensayo: `$method->condition('gap_mm')`. */
    public function condition(string $key, mixed $default = null): mixed
    {
        return $this->conditions[$key] ?? $default;
    }
}
