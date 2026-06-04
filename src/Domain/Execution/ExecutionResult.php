<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Domain\Execution;

final class ExecutionResult
{
    /**
     * @param  array<array<string,mixed>> $data    Normalised row array
     * @param  int                        $total   Total rows (without LIMIT) for pagination
     */
    public function __construct(
        public readonly array             $data,
        public readonly int               $total,
        public readonly ExecutionMetadata $metadata,
    ) {}

    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    public function withCacheHit(): self
    {
        $meta = new ExecutionMetadata(
            executionTimeMs:  $this->metadata->executionTimeMs,
            rowCount:         $this->metadata->rowCount,
            memoryUsageBytes: $this->metadata->memoryUsageBytes,
            source:           $this->metadata->source,
            cacheHit:         true,
            executedAt:       $this->metadata->executedAt,
            queryHash:        $this->metadata->queryHash,
        );

        return new self($this->data, $this->total, $meta);
    }

    public function toArray(): array
    {
        return [
            'data'     => $this->data,
            'total'    => $this->total,
            'metadata' => $this->metadata->toArray(),
        ];
    }
}
