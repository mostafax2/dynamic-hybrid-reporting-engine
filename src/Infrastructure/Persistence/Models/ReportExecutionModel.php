<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReportExecutionModel extends Model
{
    protected $table    = 'dhr_report_executions';
    public    $timestamps = false;

    protected $fillable = [
        'report_id',
        'tenant_id',
        'executed_by',
        'execution_time_ms',
        'row_count',
        'memory_bytes',
        'cache_hit',
        'source',
        'query_hash',
        'status',
        'error_message',
        'executed_at',
    ];

    protected $casts = [
        'execution_time_ms' => 'float',
        'row_count'         => 'integer',
        'memory_bytes'      => 'integer',
        'cache_hit'         => 'boolean',
        'executed_at'       => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ReportModel::class, 'report_id', 'id');
    }
}
