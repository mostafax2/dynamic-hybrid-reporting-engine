<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\Security;

use Mostafax\ReportingEngine\Core\DSL\FilterCondition;
use Mostafax\ReportingEngine\Core\DSL\FilterGroup;
use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;
use Mostafax\ReportingEngine\Core\DSL\SelectField;
use Mostafax\ReportingEngine\Core\DSL\AggregationField;
use Mostafax\ReportingEngine\Core\Validation\DslValidationException;

/**
 * Guards against identifier injection in column/table names.
 *
 * Values are never interpolated into raw SQL — they are always passed as
 * PDO bindings by the query builder. This sanitizer focuses on the
 * structural identifiers (table names, column names) that cannot be
 * parameterised.
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
        }

        foreach ($definition->groupBy as $col) {
            $this->assertIdentifier($col, 'group_by');
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
        }

        if ($definition->filters !== null) {
            $this->sanitizeGroup($definition->filters);
        }

        return $definition;
    }

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
