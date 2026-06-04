<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\DataSources;

use Illuminate\Support\Facades\DB;
use Mostafax\ReportingEngine\Contracts\DataSourceInterface;
use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;
use Mostafax\ReportingEngine\Domain\Execution\ExecutionMetadata;
use Mostafax\ReportingEngine\Domain\Execution\ExecutionResult;
use Mostafax\ReportingEngine\Infrastructure\Builders\MySQLQueryBuilder;

final class MySQLDataSource implements DataSourceInterface
{
    public function __construct(
        private readonly MySQLQueryBuilder $builder,
    ) {}

    public function supports(string $sourceType): bool
    {
        return $sourceType === 'mysql';
    }

    public function query(QueryDefinition $definition): ExecutionResult
    {
        return $this->fetch($definition);
    }

    public function aggregate(QueryDefinition $definition): ExecutionResult
    {
        return $this->fetch($definition);
    }

    private function fetch(QueryDefinition $definition): ExecutionResult
    {
        $startTime = hrtime(true);
        $memBefore = memory_get_usage(true);

        $query = $this->builder->build($definition);
        $total = $this->count($definition);

        $rows = $query
            ->offset($definition->pagination->offset)
            ->limit($definition->pagination->perPage)
            ->get()
            ->map(fn($row) => (array) $row)
            ->toArray();

        return $this->buildResult($rows, $total, $definition->source, $startTime, $memBefore);
    }

    public function count(QueryDefinition $definition): int
    {
        $query = $this->builder->build($definition);

        // For aggregation queries the outer row count equals the number of groups
        if ($definition->isAggregation()) {
            return DB::connection($definition->connection)
                ->table(DB::raw("({$query->toSql()}) as __count_sub"))
                ->mergeBindings($query)
                ->count();
        }

        return $query->count();
    }

    private function buildResult(
        array  $rows,
        int    $total,
        string $source,
        int    $startTime,
        int    $memBefore,
    ): ExecutionResult {
        $executionMs = (hrtime(true) - $startTime) / 1_000_000;
        $memUsed     = memory_get_usage(true) - $memBefore;

        $metadata = new ExecutionMetadata(
            executionTimeMs:  $executionMs,
            rowCount:         count($rows),
            memoryUsageBytes: max(0, $memUsed),
            source:           $source,
            cacheHit:         false,
            executedAt:       new \DateTimeImmutable(),
        );

        return new ExecutionResult(data: $rows, total: $total, metadata: $metadata);
    }
}
