<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReportModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table      = 'dhr_reports';
    protected $primaryKey = 'id';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'id',
        'name',
        'description',
        'definition',
        'is_public',
        'tenant_id',
        'created_by',
        'tags',
        'is_cached',
        'cache_ttl',
    ];

    protected $casts = [
        'definition' => 'array',
        'tags'       => 'array',
        'is_public'  => 'boolean',
        'is_cached'  => 'boolean',
        'cache_ttl'  => 'integer',
    ];

    public function executions(): HasMany
    {
        return $this->hasMany(ReportExecutionModel::class, 'report_id', 'id');
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(WidgetModel::class, 'report_id', 'id');
    }

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}
