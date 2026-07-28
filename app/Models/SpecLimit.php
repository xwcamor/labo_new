<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SpecLimit — el límite de un parámetro dentro de un cuadro.
 *
 * En el sistema anterior esto era la cadena "0.20 - máximo" guardada en una
 * columna del informe: para pintar un semáforo había que parsearla, y para
 * cambiarla había que editar el código.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA BANDA DE AVISO                                                        │
 * └──────────────────────────────────────────────────────────────────────────┘
 * `warn_min`/`warn_max` son opcionales y no venían del sistema anterior, que
 * pasaba de "cumple" a "no cumple" sin escalón. Un laboratorio quiere ver el
 * aceite ACERCÁNDOSE al límite, no enterarse cuando ya salió de norma.
 */
class SpecLimit extends Model
{
    public const OP_MAX = '<=';
    public const OP_MIN = '>=';
    public const OP_BETWEEN = 'between';
    public const OP_TEXT = 'text';

    /** El resultado cumple. */
    public const IN_SPEC = 'in_spec';

    /** Cumple, pero está pegado al límite. */
    public const NEAR_LIMIT = 'near_limit';

    /** Fuera de norma. */
    public const OUT_OF_SPEC = 'out_of_spec';

    protected $table = 'spec_limits';
    protected $guarded = [];

    protected $casts = [
        'min_value' => 'decimal:8',
        'max_value' => 'decimal:8',
        'warn_min'  => 'decimal:8',
        'warn_max'  => 'decimal:8',
    ];

    public function specSet(): BelongsTo
    {
        return $this->belongsTo(SpecSet::class);
    }

    public function analyte(): BelongsTo
    {
        return $this->belongsTo(Analyte::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(TestMethod::class, 'test_method_id');
    }

    /**
     * El veredicto de un valor contra este límite.
     *
     * Devuelve null cuando el límite no puede juzgar ese valor: un límite de
     * texto no evalúa un número, y un límite sin cotas no evalúa nada. Null es
     * "sin criterio" y NO es "cumple" — la diferencia importa, porque rellenar
     * con "cumple" lo que no se pudo evaluar es fabricar una afirmación.
     */
    public function verdict(float $value): ?string
    {
        if ($this->operator === self::OP_TEXT) {
            return null;
        }

        $min = $this->min_value !== null ? (float) $this->min_value : null;
        $max = $this->max_value !== null ? (float) $this->max_value : null;

        if ($min === null && $max === null) {
            return null;
        }

        if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
            return self::OUT_OF_SPEC;
        }

        $warnMin = $this->warn_min !== null ? (float) $this->warn_min : null;
        $warnMax = $this->warn_max !== null ? (float) $this->warn_max : null;

        if (($warnMin !== null && $value < $warnMin) || ($warnMax !== null && $value > $warnMax)) {
            return self::NEAR_LIMIT;
        }

        return self::IN_SPEC;
    }

    /** El veredicto de un valor cualitativo ("Brillante y Claro"). */
    public function verdictForText(?string $value): ?string
    {
        if ($this->operator !== self::OP_TEXT || blank($this->text_value) || blank($value)) {
            return null;
        }

        // Se comparan sin acentos ni mayúsculas: el sistema anterior guardaba
        // el mismo resultado escrito de varias formas ("B Y C" y "Brillante y
        // Claro" son el mismo aceite).
        $normalizar = fn (string $s) => mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)));

        return $normalizar($value) === $normalizar($this->text_value)
            ? self::IN_SPEC
            : self::OUT_OF_SPEC;
    }

    /** "0.20 máximo" — el texto que va al informe si el cuadro no trae uno. */
    public function describe(): string
    {
        if (filled($this->display)) {
            return $this->display;
        }

        $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');

        return match ($this->operator) {
            self::OP_TEXT    => (string) $this->text_value,
            self::OP_MIN     => $n($this->min_value) . ' mínimo',
            self::OP_BETWEEN => $n($this->min_value) . ' – ' . $n($this->max_value),
            default          => $n($this->max_value) . ' máximo',
        };
    }
}
