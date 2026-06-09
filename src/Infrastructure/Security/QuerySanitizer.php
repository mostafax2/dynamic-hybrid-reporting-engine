<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\Security;

use Mostafax\ReportingEngine\Core\DSL\FilterCondition;
use Mostafax\ReportingEngine\Core\DSL\FilterGroup;
use Mostafax\ReportingEngine\Core\DSL\GroupByField;
use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;
use Mostafax\ReportingEngine\Core\DSL\WindowFunction;
use Mostafax\ReportingEngine\Core\Formula\FormulaLexer;
use Mostafax\ReportingEngine\Core\Formula\FormulaParseException;
use Mostafax\ReportingEngine\Core\Validation\DslValidationException;

/**
 * Guards against identifier injection in column/table names.
 *
 * Values are never interpolated into raw SQL — they are always passed as
 * PDO bindings by the query builder. This sanitizer focuses on the
 * structural identifiers (table names, column names) that cannot be
 * parameterised.
 *
 * group_by validation strategy (per type):
 *   column     → assertIdentifier on column name
 *   date_trunc → assertIdentifier on column; granularity is an enum (safe)
 *   expression → FormulaLexer tokenise() allowlist — no arbitrary SQL allowed
 */
final class QuerySanitizer
{
    private const IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_.]*$/';

    /** @throws DslValidationException */
    public function sanitize(QueryDefinition $definition): QueryDefinition
    {
        $this->assertIdentifier($definition->table, 'table');

        foreach ($definition->fields as $field) {
            $this->assertIdentifier($field->column, 'fields.column');
        }

        foreach ($definition->aggregations as $agg) {
            $this->assertIdentifier($agg->column, 'aggregations.column');
            $this->assertIdentifier($agg->alias,  'aggregations.alias');
        }

        foreach ($definition->groupBy as $groupByField) {
            $this->sanitizeGroupByField($groupByField);
        }

        foreach ($definition->orderBy as $order) {
            $this->assertIdentifier($order->column, 'order_by.column');
        }

        foreach ($definition->joins as $join) {
            $this->assertIdentifier($join->table, 'joins.table');
            $this->assertIdentifier($join->first, 'joins.first');
            if ($join->second !== null) {
                $this->assertIdentifier($join->second, 'joins.second');
            }
            if ($join->alias !== null) {
                $this->assertIdentifier($join->alias, 'joins.alias');
            }
        }

        if ($definition->filters !== null) {
            $this->sanitizeGroup($definition->filters);
        }

        if ($definition->rlsFilters !== null) {
            $this->sanitizeGroup($definition->rlsFilters);
        }

        if ($definition->having !== null) {
            $this->sanitizeHavingGroup($definition->having);
        }

        foreach ($definition->windows as $window) {
            $this->sanitizeWindowFunction($window);
        }

        // Validate formula expressions via the lexer character allowlist
        $formulaLexer = new FormulaLexer();
        foreach ($definition->computed as $cf) {
            $this->assertIdentifier($cf->alias, 'computed.alias');
            try {
                $formulaLexer->tokenise($cf->expression);
            } catch (FormulaParseException $e) {
                throw new DslValidationException(
                    "Security: invalid formula expression for alias '{$cf->alias}': {$e->getMessage()}"
                );
            }
        }

        return $definition;
    }

    // ── Group-by validation ───────────────────────────────────────────────────

    private function sanitizeGroupByField(GroupByField $field): void
    {
        match ($field->type) {
            'column'     => $this->assertIdentifier($field->column, 'group_by.column'),
            'date_trunc' => $this->assertIdentifier($field->column, 'group_by.date_trunc.column'),
            'expression' => $this->sanitizeGroupByExpression($field),
            default      => throw new DslValidationException(
                "Security: unknown group_by type '{$field->type}'"
            ),
        };

        // Alias must also be a safe identifier when present
        if ($field->alias !== null) {
            $this->assertIdentifier($field->alias, 'group_by.alias');
        }
    }

    private function sanitizeGroupByExpression(GroupByField $field): void
    {
        if ($field->expression === null || $field->expression === '') {
            throw new DslValidationException(
                "Security: expression group_by requires a non-empty 'expression'"
            );
        }

        $lexer = new FormulaLexer();
        try {
            $lexer->tokenise($field->expression);
        } catch (FormulaParseException $e) {
            throw new DslValidationException(
                "Security: invalid group_by expression '{$field->expression}': {$e->getMessage()}"
            );
        }
    }

    // ── HAVING validation (aggregation aliases — plain identifiers) ───────────

    private function sanitizeHavingGroup(FilterGroup $group): void
    {
        foreach ($group->conditions as $condition) {
            if ($condition instanceof FilterGroup) {
                $this->sanitizeHavingGroup($condition);
            } elseif ($condition instanceof FilterCondition) {
                // HAVING fields reference aggregation aliases — assertIdentifier is correct
                $this->assertIdentifier($condition->field, 'having.field');
            }
        }
    }

    // ── Window function validation ────────────────────────────────────────────

    private function sanitizeWindowFunction(WindowFunction $window): void
    {
        $this->assertIdentifier($window->alias, 'windows.alias');

        foreach ($window->partitionBy as $col) {
            $this->assertIdentifier($col, 'windows.partition_by');
        }

        foreach ($window->orderBy as $order) {
            $this->assertIdentifier($order->column, 'windows.order_by.column');
        }

        if ($window->column !== null) {
            $this->assertIdentifier($window->column, 'windows.column');
        }
    }

    // ── Filter validation ─────────────────────────────────────────────────────

    private function sanitizeGroup(FilterGroup $group): void
    {
        foreach ($group->conditions as $condition) {
            if ($condition instanceof FilterGroup) {
                $this->sanitizeGroup($condition);
            } elseif ($condition instanceof FilterCondition) {
                $this->assertIdentifier($condition->field, 'filters.field');
            }
        }
    }

    // ── Core identifier check ─────────────────────────────────────────────────

    /** @throws DslValidationException */
    private function assertIdentifier(string $value, string $context): void
    {
        if (!preg_match(self::IDENTIFIER_PATTERN, $value)) {
            throw new DslValidationException(
                "Security: unsafe identifier in '{$context}': '{$value}'. "
                . "Only letters, numbers, underscores, and dots are allowed."
            );
        }
    }
}
