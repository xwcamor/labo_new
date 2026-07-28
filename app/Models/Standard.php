<?php

namespace App\Models;

use App\Traits\BelongsToTenantOrGlobal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Standard — una norma.
 *
 * Hay TRES clases de norma y el sistema anterior las mezclaba, que es lo que
 * producía informes internamente incoherentes:
 *
 *   method      con qué se midió          "ASTM D1816, 2.0 mm"
 *   acceptance  contra qué se compara     "IEEE C57.106"
 *   diagnosis   cómo se interpreta        "IEC 60599"  (de TrafoDex)
 *
 * El caso concreto: el informe imprimía "ASTM D877" como método y al lado un
 * límite sacado de la tabla de D1816. D877 fija 2.54 mm de separación entre
 * electrodos y D1816 admite 1 o 2 mm — los kV no son comparables entre sí, así
 * que ese informe decía dos cosas que no encajaban.
 *
 * Van en la misma tabla porque son la misma clase de objeto (un documento
 * normativo con su edición), pero se usan en lugares distintos y nunca se
 * sustituyen: `kind` es lo que lo hace explícito.
 */
class Standard extends Model
{
    use SoftDeletes;
    use BelongsToTenantOrGlobal;

    public const KIND_METHOD = 'method';
    public const KIND_ACCEPTANCE = 'acceptance';
    public const KIND_DIAGNOSIS = 'diagnosis';

    public const KINDS = [self::KIND_METHOD, self::KIND_ACCEPTANCE, self::KIND_DIAGNOSIS];

    protected $table = 'standards';
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function methods(): HasMany
    {
        return $this->hasMany(TestMethod::class);
    }

    public function specSets(): HasMany
    {
        return $this->hasMany(SpecSet::class);
    }

    public function scopeKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    /** "ASTM D1816-2019", o solo el código si no declara edición. */
    public function label(): string
    {
        return $this->edition ? "{$this->code}-{$this->edition}" : (string) $this->code;
    }

    /**
     * ¿Esta norma ya fue reemplazada?
     *
     * No se usa para bloquear nada: un ensayo viejo se evaluó con la norma que
     * regía ese día y así tiene que seguir. Sirve para avisar en pantalla que
     * hay muestras pendientes usando una edición superada.
     */
    public function isSuperseded(): bool
    {
        return $this->superseded_by_id !== null;
    }
}
