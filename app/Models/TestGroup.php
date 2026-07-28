<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Grupo de pruebas: Físico Químico · Cromatografía · Otros. */
class TestGroup extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditModule = 'test_groups';
    protected $table = 'test_groups';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function tests(): HasMany
    {
        return $this->hasMany(TestDefinition::class)->orderBy('sort_order');
    }

    public function getRouteKeyName(): string { return 'slug'; }
}
