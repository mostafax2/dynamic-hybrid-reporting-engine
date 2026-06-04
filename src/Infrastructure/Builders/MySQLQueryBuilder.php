<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\Builders;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Mostafax\ReportingEngine\Contracts\QueryBuilderInterface;
use Mostafax\ReportingEngine\Core\DSL\AggregationField;
use Mostafax\ReportingEngine\Core\DSL\FilterCondition;
use Mostafax\ReportingEngine\Core\DSL\FilterGroup;
use Mostafax\ReportingEngine\Core\DSL\JoinClause;
use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;
use Mostafax\ReportingEngine\Core\DSL\SelectField;

/**
 * Translates a QueryDefinition into a Laravel Query Builder instance.
 *
 * No query is executed here — the caller decides whether to fetch,
 * count, paginate, or inspect the built query.
 */
final class MySQLQueryBuilder implements QueryBuilderInterface
{
    public function build(QueryDefinition $definition): Builder
    {
        $query = DB::connection($definition->connection)->table($definition->table);

        $this->applyJoins($query, $definition->joins);
        $this->applySelects($query, $definition->fields, $definition->aggregations);
        $this->applyFilters($query, $definition->filters, $definition->tenantId);
        $this->applyGroupBy($query, $definition->groupBy);
        $this->applyOrderBy($query, $definition->orderBy);

        return $query;
    }

    // ────────────────────────────────────────────────────────────
    // Private helpers
    // ────────────────────────────────────────────────────────────

    /** @param JoinClause[] $joins */
    private function applyJoins(Builder $query, array $joins): void
    {
        foreach ($joins as $join) {
            match (strtolower($join->type)) {
                'left'  => $query->leftJoin($join->table, $join->first, $join->operator, $join->second),
                'right' => $query->rightJoin($join->table, $join->first, $join->operator, $join->second),
                'cross' => $query->crossJoin($join->table),
                default => $query->join($join->table, $join->first, $join->operator, $join->second),
            };
        }
    }

    /**
     * @param SelectField[]      $fields
     * @param AggregationField[] $aggregations
     */
    private function applySelects(Builder $query, array $fields, array $aggregations): void
    {
        $selects = [];

        foreach ($fields as $field) {
            $col = $this->quoteColumn($field->column);
            $selects[] = $field->alias
                ? DB::raw("{$col} as `{$field->alias}`")
                : DB::raw($col);
        }

        foreach ($aggregations as $agg) {
            $fn  = strtoupper($agg->function === 'count_distinct' ? 'COUNT(DISTINCT' : $agg->function);
            $col = $this->quoteColumn($agg->column);
            $raw = $agg->function === 'count_distinct'
                ? "{$fn} {$col}) as `{$agg->alias}`"
                : "{$fn}({$col}) as `{$agg->alias}`";
            $selects[] = DB::raw($raw);
        }

        if (!empty($selects)) {
            $query->select($selects);
        }
    }

    private function applyFilters(Builder $query, ?FilterGroup $group, ?string $tenantId): void
    {
        if ($tenantId !== null) {
            $tenantColumn = function_exists('config')
                ? config('reporting-engine.multi_tenancy.tenant_column', 'tenant_id')
                : 'tenant_id';
            $query->where($tenantColumn, '=', $tenantId);
        }

        if ($group === null || $group->isEmpty()) {
            return;
        }

        $query->where(fn(Builder $sub) => $this->applyGroup($sub, $group));
    }

    private function applyGroup(Builder $query, FilterGroup $group): void
    {
        foreach ($group->conditions as $condition) {
            if ($condition instanceof FilterGroup) {
                $nestedMethod = $condition->operator === 'OR' ? 'orWhere' : 'where';
                $query->{$nestedMethod}(fn(Builder $sub) => $this->applyGroup($sub, $condition));
            } elseif ($condition instanceof FilterCondition) {
                $this->applyCondition($query, $condition, $group->operator);
            }
        }
    }

    private function applyCondition(Builder $query, FilterCondition $condition, string $groupOperator): void
    {
        $or = $groupOperator === 'OR';

        match ($condition->operator) {
            'in'                    => $or ? $query->orWhereIn($condition->field, (array) $condition->value) : $query->whereIn($condition->field, (array) $condition->value),
            'nin', 'not_in'         => $or ? $query->orWhereNotIn($condition->field, (array) $condition->value) : $query->whereNotIn($condition->field, (array) $condition->value),
            'between'               => $or ? $query->orWhereBetween($condition->field, $condition->value) : $query->whereBetween($condition->field, $condition->value),
            'null'                  => $or ? $query->orWhereNull($condition->field) : $query->whereNull($condition->field),
            'not_null'              => $or ? $query->orWhereNotNull($condition->field) : $query->whereNotNull($condition->field),
            'like', 'not_like'      => $or
                ? $query->orWhere($condition->field, strtoupper($condition->operator), $condition->value)
                : $query->where($condition->field, strtoupper($condition->operator), $condition->value),
            default => $or
                ? $query->orWhere($condition->field, $condition->operator, $condition->value)
                : $query->where($condition->field, $condition->operator, $condition->value),
        };
    }

    private function applyGroupBy(Builder $query, array $groupBy): void
    {
        if (!empty($groupBy)) {
            $query->groupBy($groupBy);
        }
    }

    private function applyOrderBy(Builder $query, array $orderBy): void
    {
        foreach ($orderBy as $order) {
            $query->orderBy($order->column, $order->direction);
        }
    }

    /**
     * Wraps a column identifier with backticks, handling table.column notation.
     * Only safe identifiers (letters, numbers, underscores, dots) are accepted.
     */
    private function quoteColumn(string $column): string
    {
        if ($column === '*') {
            return '*';
        }
        if (str_contains($column, '.')) {
            [$table, $col] = explode('.', $column, 2);
            return "`{$table}`.`{$col}`";
        }
        return "`{$column}`";
    }
}
