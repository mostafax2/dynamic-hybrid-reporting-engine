<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\DSL;

final readonly class OrderByField
{
    public function __construct(
        public string $column,
        public string $direction,
    ) {
        if (!in_array(strtolower($this->direction), ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException("OrderByField direction must be asc or desc, got: {$this->direction}");
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            column:    (string) ($data['column'] ?? $data['field'] ?? throw new \InvalidArgumentException('OrderBy requires a column name')),
            direction: strtolower((string) ($data['direction'] ?? $data['order'] ?? 'asc')),
        );
    }
}
