<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * InstrumentFile — el archivo crudo que emite un equipo de medición, tal como
 * salió, antes de interpretarlo.
 *
 * Se conserva el original y no solo lo importado: si el mapeo del formato estaba
 * mal, se corrige el formato y se vuelve a leer el mismo archivo. El `sha256`
 * permite detectar que se subió dos veces el mismo y, sobre todo, demostrar que
 * el archivo guardado es el que emitió el equipo.
 *
 * `status` acompaña el ciclo de lectura y `parse_error` guarda por qué falló,
 * que es lo que en el sistema viejo no quedaba en ningún lado: la importación
 * corría, no enlazaba, y nadie se enteraba hasta ver la celda vacía en el
 * informe.
 */
class InstrumentFile extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant;

    protected string $auditModule = 'instrument_files';

    protected $table = 'instrument_files';

    /** Subido, todavía sin interpretar. */
    public const STATUS_UPLOADED = 'uploaded';

    /** Leído y volcado a filas de la hoja. */
    public const STATUS_PARSED = 'parsed';

    /** No se pudo interpretar; el motivo queda en `parse_error`. */
    public const STATUS_FAILED = 'failed';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_UPLOADED,
        self::STATUS_PARSED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'slug', 'worksheet_id', 'original_name', 'path', 'mime', 'size', 'sha256',
        'instrument_format_id', 'status', 'parse_error', 'rows_parsed',
        'tenant_id', 'created_by',
    ];

    protected $casts = [
        'size'        => 'integer',
        'rows_parsed' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                do {
                    $slug = Str::random(22);
                } while (static::withTrashed()->where('slug', $slug)->exists());
                $model->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function worksheet(): BelongsTo
    {
        return $this->belongsTo(Worksheet::class);
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(InstrumentFormat::class, 'instrument_format_id');
    }

    /** Filas de hoja que salieron de este archivo. */
    public function rows(): HasMany
    {
        return $this->hasMany(WorksheetRow::class, 'instrument_file_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('instrument_files.status', $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('instrument_files.status', self::STATUS_UPLOADED);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('instrument_files.status', self::STATUS_FAILED);
    }

    public function isParsed(): bool
    {
        return $this->status === self::STATUS_PARSED;
    }

    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
