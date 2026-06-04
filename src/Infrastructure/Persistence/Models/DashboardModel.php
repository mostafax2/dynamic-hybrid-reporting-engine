<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DashboardModel extends Model
{
    use SoftDeletes;

    protected $table      = 'dhr_dashboards';
    protected $primaryKey = 'id';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'id',
        'name',
        'description',
        'tenant_id',
        'created_by',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function widgets(): HasMany
    {
        return $this->hasMany(WidgetModel::class, 'dashboard_id', 'id')
                    ->orderBy('position_y')
                    ->orderBy('position_x');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
