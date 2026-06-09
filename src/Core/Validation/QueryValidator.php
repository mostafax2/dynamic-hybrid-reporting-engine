<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\Validation;

use Mostafax\ReportingEngine\Core\DSL\FilterCondition;
use Mostafax\ReportingEngine\Core\DSL\FilterGroup;
use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;

/**
 * Validates a QueryDefinition against configured limits and allowed values.
 *
 * Returns a list of violation messages; an empty array means the query is valid.
 * Throw DslValidationException to halt execution on the first failure.
 */
final class QueryValidator
{
    /** @var array<string,string[]> */
    private array $errors = [];

    public function __construct(
        private readonly array $config,
    ) {}

    /** @throws DslValidationException */
    public function validate(QueryDefinition $definition): void
    {
        $this->errors = [];

        $this->validateSource($definition);
        $this->validateTable($definition);
        $this->validatePagination($definition);
        $this->validateJoins($definition);
        $this->validateAggregations($definition);
        $this->validateGroupBy($definition);
        $this->validateOrderBy($definition);
        $this->validateFilters($definition);

        if (!empty($this->errors)) {
            throw new DslValidationException(
                'Report DSL validation failed: ' . implode('; ', array_merge(...array_values($this->errors))),
                $this->errors,
            );
        }
    }

    private function validateSource(QueryDefinition $definition): void
    {
        $allowed = array_keys($this->config['adapters'] ?? []);

        if (!in_array($definition->source, $allowed, true)) {
            $this->errors['source'][] = "Unknown source '{$definition->source}'. Allowed: " . implode(', ', $allowed);
        }
    }

    private function validateTable(QueryDefinition $definition): void
    {
        if (empty($definition->table)) {
            $this->errors['table'][] = 'Table / collection name is required.';
            return;
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $definition->table)) {
            $this->errors['table'][] = "Table name '{$definition->table}' contains invalid characters.";
        }
    }

    private function validatePagination(QueryDefinition $definition): void
    {
        $max = (int) ($this->config['limits']['max_per_page'] ?? 500);

        if ($definition->pagination->perPage > $max) {
            $this->errors['pagination'][] = "per_page may not exceed {$max}.";
        }
    }

    private function validateJoins(QueryDefinition $definition): void
    {
        $maxJoins = (int) ($this->config['limits']['max_joins'] ?? 5);

        if (count($definition->joins) > $maxJoins) {
            $this->errors['joins'][] = "Maximum {$maxJoins} joins allowed.";
        }

        if (!empty($definition->joins) && $definition->source !== 'mysql') {
            $this->errors['joins'][] = "JOIN clauses are only supported for mysql data sources.";
        }
    }

    private function validateAggregations(QueryDefinition $definition): void
    {
        $maxAgg     = (int) ($this->config['limits']['max_aggregations'] ?? 10);
        $allowedFns = $this->config['allowed_aggregations'] ?? [];

        if (count($definition->aggregations) > $maxAgg) {
            $this->errors['aggregations'][] = "Maximum {$maxAgg} aggregation fields allowed.";
        }

        foreach ($definition->aggregations as $agg) {
            if (!in_array($agg->function, $allowedFns, true)) {
                $this->errors['aggregations'][] = "Aggregation function '{$agg->function}' is not permitted.";
            }
        }
    }

    private function validateGroupBy(QueryDefinition $definition): void
    {
        if (empty($definition->groupBy)) {
            return;
        }

        $max = (int) ($this->config['limits']['max_group_by_fields'] ?? 5);

        if (count($definition->groupBy) > $max) {
            $this->errors['group_by'][] = "Maximum {$max} GROUP BY fields allowed.";
        }

        // Detect duplicates by output name (works with GroupByField objects)
        $names      = array_map(fn($f) => $f->outputName(), $definition->groupBy);
        $duplicates = array_keys(array_filter(array_count_values($names), fn(int $n) => $n > 1));
        foreach ($duplicates as $dup) {
            $this->errors['group_by'][] = "Duplicate GROUP BY field output name: '{$dup}'.";
        }
    }

    private function validateOrderBy(QueryDefinition $definition): void
    {
        $max = (int) ($this->config['limits']['max_order_by_fields'] ?? 5);

        if (count($definition->orderBy) > $max) {
            $this->errors['order_by'][] = "Maximum {$max} ORDER BY fields allowed.";
        }
    }

    private function validateFilters(QueryDefinition $definition): void
    {
        if ($definition->filters === null) {
            return;
        }

        $count = $this->countConditions($definition->filters);
        $max   = (int) ($this->config['limits']['max_conditions'] ?? 20);

        if ($count > $max) {
            $this->errors['filters'][] = "Maximum {$max} filter conditions allowed.";
        }

        $this->validateOperators($definition->filters);
    }

    private function validateOperators(FilterGroup $group): void
    {
        $allowed = $this->config['allowed_operators'] ?? [];

        foreach ($group->conditions as $condition) {
            if ($condition instanceof FilterGroup) {
                $this->validateOperators($condition);
            } elseif ($condition instanceof FilterCondition) {
                if (!in_array($condition->operator, $allowed, true)) {
                    $this->errors['filters'][] = "Operator '{$condition->operator}' is not permitted.";
                }
            }
        }
    }

    private function countConditions(FilterGroup $group): int
    {
        $count = 0;
        foreach ($group->conditions as $condition) {
            if ($condition instanceof FilterGroup) {
                $count += $this->countConditions($condition);
            } else {
                $count++;
            }
        }
        return $count;
    }
}
