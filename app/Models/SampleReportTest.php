<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Si un ensayo pedido sale impreso en ESTE informe.
 *
 * Es el `*_display` del sistema anterior, que era una columna por prueba del
 * catálogo —treinta columnas en una sola fila— y obligaba a migrar la tabla
 * cada vez que el laboratorio agregaba un ensayo.
 */
class SampleReportTest extends Model
{
    protected $table = 'sample_report_tests';
    protected $guarded = [];
    protected $casts = ['is_visible' => 'boolean'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(SampleReport::class, 'sample_report_id');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(SampleTest::class, 'sample_test_id');
    }
}
