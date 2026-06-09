<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\DSL;

/**
 * Typed group-by descriptor.
 *
 * Three forms are accepted in the DSL:
 *
 *   Plain string (backward-compat):
 *     "group_by": ["status", "region"]
 *
 *   Column object:
 *     {"type": "column", "column": "status", "alias": "status"}
 *
 *   Date-truncation (generates DATE_FORMAT / $dateTrunc safely — no raw SQL):
 *     {"type": "date_trunc", "column": "created_at", "granularity": "month", "alias": "month"}
 *     granularity: year | quarter | month | week | day | hour
 *
 *   Formula expression (FormulaLexer-validated — no arbitrary SQL):
 *     {"type": "expression", "expression": "YEAR(created_at)", "alias": "year"}
 */
final readonly class GroupByField
{
    public const GRANULARITIES = ['year', 'quarter', 'month', 'week', 'day', 'hour'];

    public function __construct(
        /** @var 'column'|'date_trunc'|'expression' */
        public string  $type,
        public string  $column      = '',
        public ?string $alias       = null,
        public ?string $granularity = null,
        public ?string $expression  = null,
    ) {}

    /** Parse a raw DSL value (string shorthand or array) into a GroupByField. */
    public static function fromRaw(string|array $raw): self
    {
        if (is_string($raw)) {
            return new self(type: 'column', column: $raw);
        }

        $type = strtolower((string) ($raw['type'] ?? 'column'));

        return match ($type) {
            'date_trunc' => self::makeDateTrunc($raw),
            'expression' => self::makeExpression($raw),
            default      => self::makeColumn($raw),
        };
    }

    /** The name used in SELECT / ORDER BY / HAVING to reference this group field. */
    public function outputName(): string
    {
        return $this->alias ?? $this->column;
    }

    // ── factories ────────────────────────────────────────────────────────────

    private static function makeColumn(array $raw): self
    {
        $column = (string) ($raw['column'] ?? throw new \InvalidArgumentException(
            "group_by column type requires 'column' key"
        ));
        return new self(
            type:   'column',
            column: $column,
            alias:  isset($raw['alias']) ? (string) $raw['alias'] : null,
        );
    }

    private static function makeDateTrunc(array $raw): self
    {
        $column      = (string) ($raw['column'] ?? throw new \InvalidArgumentException(
            "date_trunc group_by requires 'column'"
        ));
        $granularity = strtolower((string) ($raw['granularity'] ?? 'month'));

        if (!in_array($granularity, self::GRANULARITIES, true)) {
            throw new \InvalidArgumentException(
                "date_trunc granularity must be one of: " . implode(', ', self::GRANULARITIES)
            );
        }

        return new self(
            type:        'date_trunc',
            column:      $column,
            alias:       isset($raw['alias']) ? (string) $raw['alias'] : "{$column}_{$granularity}",
            granularity: $granularity,
        );
    }

    private static function makeExpression(array $raw): self
    {
        return new self(
            type:       'expression',
            column:     '',
            alias:      (string) ($raw['alias'] ?? throw new \InvalidArgumentException(
                "expression group_by requires 'alias'"
            )),
            expression: (string) ($raw['expression'] ?? throw new \InvalidArgumentException(
                "expression group_by requires 'expression'"
            )),
        );
    }
}
