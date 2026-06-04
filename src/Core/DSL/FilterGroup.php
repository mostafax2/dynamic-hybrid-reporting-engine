<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\DSL;

final readonly class FilterGroup
{
    /** @param array<FilterCondition|FilterGroup> $conditions */
    public function __construct(
        public string $operator,
        public array  $conditions,
    ) {
        if (!in_array(strtoupper($this->operator), ['AND', 'OR'], true)) {
            throw new \InvalidArgumentException("FilterGroup operator must be AND or OR, got: {$this->operator}");
        }
    }

    public static function fromArray(array $data): self
    {
        $operator   = strtoupper((string) ($data['operator'] ?? 'AND'));
        $conditions = [];

        foreach ($data['conditions'] ?? [] as $item) {
            $conditions[] = isset($item['conditions'])
                ? self::fromArray($item)
                : FilterCondition::fromArray($item);
        }

        return new self(operator: $operator, conditions: $conditions);
    }

    public function isEmpty(): bool
    {
        return empty($this->conditions);
    }
}
