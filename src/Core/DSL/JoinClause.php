<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\DSL;

final readonly class JoinClause
{
    private const TYPES = ['inner', 'left', 'right', 'cross'];

    public function __construct(
        public string  $type,
        public string  $table,
        public string  $first,
        public string  $operator,
        public ?string $second = null,
        public ?string $alias = null,
    ) {
        if (!in_array(strtolower($this->type), self::TYPES, true)) {
            throw new \InvalidArgumentException("Unknown join type: {$this->type}");
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type:     strtolower((string) ($data['type'] ?? 'inner')),
            table:    (string) ($data['table'] ?? throw new \InvalidArgumentException('Join requires a table name')),
            first:    (string) ($data['first'] ?? throw new \InvalidArgumentException('Join requires first column')),
            operator: (string) ($data['operator'] ?? '='),
            second:   isset($data['second']) ? (string) $data['second'] : null,
            alias:    isset($data['alias']) ? (string) $data['alias'] : null,
        );
    }
}
