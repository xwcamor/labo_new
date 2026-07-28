<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El párrafo de opinión de una familia de ensayos, para una muestra.
 *
 * Es la única parte del informe que NO es una medición: es lo que el
 * laboratorio interpreta. Por eso se guarda —en vez de recalcularse al
 * imprimir— y por eso lleva `is_edited`: ante una auditoría hay que poder
 * distinguir la frase que compuso el motor de la que escribió una persona.
 */
class SampleDiagnosis extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'sample_id', 'family', 'body', 'is_edited',
        'generated_at', 'edited_by', 'tenant_id',
    ];

    protected $casts = [
        'is_edited'    => 'boolean',
        'generated_at' => 'datetime',
    ];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
