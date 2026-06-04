<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\DataSources;

use Illuminate\Support\Facades\DB;
use Mostafax\ReportingEngine\Contracts\DataSourceInterface;
use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;
use Mostafax\ReportingEngine\Domain\Execution\ExecutionMetadata;
use Mostafax\ReportingEngine\Domain\Execution\ExecutionResult;
use Mostafax\ReportingEngine\Infrastructure\Builders\MongoAggregationBuilder;

/**
 * MongoDB data source adapter.
 *
 * Requires mongodb/laravel-mongodb (^4.0|^5.0).
 * Uses the $facet aggregation stage to retrieve data + totalCount in one
 * database round-trip, avoiding a separate count() call.
 */
final class MongoDataSource implements DataSourceInterface
{
    public function __construct(
        private readonly MongoAggregationBuilder $builder,
    ) {}

    public function supports(string $sourceType): bool
    {
        return $sourceType === 'mongodb';
    }

    public function query(QueryDefinition $definition): ExecutionResult
    {
        return $this->runPipeline($definition);
    }

    public function aggregate(QueryDefinition $definition): ExecutionResult
    {
        return $this->runPipeline($definition);
    }

    public function count(QueryDefinition $definition): int
    {
        $countPipeline   = $this->builder->buildForExport($definition);
        $countPipeline[] = ['$count' => 'total'];

        $result = $this->runRaw($definition, $countPipeline);
        return (int) ($result[0]['total'] ?? 0);
    }

    private function runPipeline(QueryDefinition $definition): ExecutionResult
    {
        $startTime = hrtime(true);
        $memBefore = memory_get_usage(true);

        // Pipeline includes $facet for data + count in one trip
        $pipeline = $this->builder->build($definition);
        $raw      = $this->runRaw($definition, $pipeline);

        $facetResult = $raw[0] ?? [];

        $dataItems = $facetResult['data'] ?? [];
        if ($dataItems instanceof \Traversable) {
            $dataItems = iterator_to_array($dataItems);
        }

        $rows  = array_map(
            fn($doc) => $this->normalizeDocument((array) $doc),
            $dataItems,
        );
        $total = (int) ($facetResult['totalCount'][0]['count'] ?? 0);

        $executionMs = (hrtime(true) - $startTime) / 1_000_000;

        $metadata = new ExecutionMetadata(
            executionTimeMs:  $executionMs,
            rowCount:         count($rows),
            memoryUsageBytes: max(0, memory_get_usage(true) - $memBefore),
            source:           $definition->source,
            cacheHit:         false,
            executedAt:       new \DateTimeImmutable(),
        );

        return new ExecutionResult(data: $rows, total: $total, metadata: $metadata);
    }

    /** @param array<int,array<string,mixed>> $pipeline */
    private function runRaw(QueryDefinition $definition, array $pipeline): array
    {
        return DB::connection($definition->connection)
            ->table($definition->table)
            ->raw(fn($collection) => $collection->aggregate($pipeline, ['allowDiskUse' => true]))
            ->toArray();
    }

    /**
     * Recursively normalises a MongoDB document:
     * - ObjectId  → string
     * - UTCDateTime → ISO-8601 string
     * - Removes raw _id when not projected
     */
    private function normalizeDocument(array $doc): array
    {
        $normalised = [];
        foreach ($doc as $key => $value) {
            if ($key === '_id') {
                continue; // excluded by $project: {_id: 0}
            }
            $normalised[$key] = $this->normalizeValue($value);
        }
        return $normalised;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \MongoDB\BSON\ObjectId) {
            return (string) $value;
        }
        if ($value instanceof \MongoDB\BSON\UTCDateTime) {
            return $value->toDateTime()->format(\DateTimeInterface::ATOM);
        }
        if ($value instanceof \MongoDB\Model\BSONDocument) {
            return $this->normalizeDocument((array) $value);
        }
        if ($value instanceof \MongoDB\Model\BSONArray || is_array($value)) {
            return array_map(fn($v) => $this->normalizeValue($v), (array) $value);
        }
        return $value;
    }
}
