<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Domain\Execution;

final readonly class ExecutionMetadata
{
    public function __construct(
        public float             $executionTimeMs,
        public int               $rowCount,
        public int               $memoryUsageBytes,
        public string            $source,
        public bool              $cacheHit,
        public \DateTimeImmutable $executedAt,
        public ?string           $queryHash = null,
    ) {}

    public function toArray(): array
    {
        return [
            'execution_time_ms'   => round($this->executionTimeMs, 2),
            'row_count'           => $this->rowCount,
            'memory_usage_bytes'  => $this->memoryUsageBytes,
            'memory_usage_human'  => $this->humanMemory(),
            'source'              => $this->source,
            'cache_hit'           => $this->cacheHit,
            'executed_at'         => $this->executedAt->format(\DateTimeInterface::ATOM),
            'query_hash'          => $this->queryHash,
        ];
    }

    private function humanMemory(): string
    {
        $bytes = $this->memoryUsageBytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 2) . ' ' . $unit;
            }
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' TB';
    }
}
