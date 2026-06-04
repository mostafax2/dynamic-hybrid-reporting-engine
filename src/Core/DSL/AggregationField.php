<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\DSL;

final readonly class AggregationField
{
    private const ALLOWED = ['sum', 'count', 'avg', 'min', 'max', 'count_distinct', 'group_concat'];

    public function __construct(
        public string $function,
        public string $column,
        public string $alias,
    ) {
        if (!in_array(strtolower($this->function), self::ALLOWED, true)) {
            throw new \InvalidArgumentException("Unknown aggregation function: {$this->function}");
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            function: strtolower((string) ($data['function'] ?? $data['fn'] ?? throw new \InvalidArgumentException('Aggregation requires a function name'))),
            column:   (string) ($data['column'] ?? $data['field'] ?? throw new \InvalidArgumentException('Aggregation requires a column name')),
            alias:    (string) ($data['alias'] ?? throw new \InvalidArgumentException('Aggregation requires an alias')),
        );
    }
}
