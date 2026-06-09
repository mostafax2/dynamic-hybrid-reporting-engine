<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\DSL;

final readonly class ComputedField
{
    public function __construct(
        public string $alias,
        public string $expression,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            alias:      (string) ($data['alias'] ?? throw new \InvalidArgumentException('Computed field requires an alias')),
            expression: (string) ($data['expression'] ?? throw new \InvalidArgumentException('Computed field requires an expression')),
        );
    }

    /** @return array{alias: string, expression: string} */
    public function toArray(): array
    {
        return [
            'alias'      => $this->alias,
            'expression' => $this->expression,
        ];
    }
}
