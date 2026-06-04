<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\DSL;

final readonly class FilterCondition
{
    public function __construct(
        public string $field,
        public string $operator,
        public mixed  $value,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            field:    (string) ($data['field'] ?? $data['column'] ?? throw new \InvalidArgumentException('Filter condition requires a field')),
            operator: strtolower((string) ($data['operator'] ?? $data['op'] ?? '=')),
            value:    $data['value'] ?? null,
        );
    }
}
