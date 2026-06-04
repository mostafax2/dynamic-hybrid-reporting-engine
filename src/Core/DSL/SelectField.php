<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\DSL;

final readonly class SelectField
{
    public function __construct(
        public string  $column,
        public ?string $alias = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            column: (string) ($data['column'] ?? $data['field'] ?? throw new \InvalidArgumentException('SelectField requires a column name')),
            alias:  isset($data['alias']) ? (string) $data['alias'] : null,
        );
    }

    public function resolvedName(): string
    {
        return $this->alias ?? $this->column;
    }
}
