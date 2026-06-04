<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WidgetModel extends Model
{
    protected $table    = 'dhr_widgets';
    public    $incrementing = false;
    protected $keyType  = 'string';

    protected $fillable = [
        'id',
        'dashboard_id',
        'report_id',
        'title',
        'type',
        'config',
        'position_x',
        'position_y',
        'width',
        'height',
    ];

    protected $casts = [
        'config'     => 'array',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'width'      => 'integer',
        'height'     => 'integer',
    ];

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(DashboardModel::class, 'dashboard_id', 'id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ReportModel::class, 'report_id', 'id');
    }
}
