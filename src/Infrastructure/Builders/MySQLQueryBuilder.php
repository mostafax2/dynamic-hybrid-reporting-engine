<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\Builders;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Mostafax\ReportingEngine\Contracts\QueryBuilderInterface;
use Mostafax\ReportingEngine\Core\DSL\AggregationField;
use Mostafax\ReportingEngine\Core\DSL\FilterCondition;
use Mostafax\ReportingEngine\Core\DSL\FilterGroup;
use Mostafax\ReportingEngine\Core\DSL\GroupByField;
use Mostafax\ReportingEngine\Core\DSL\JoinClause;
use Mostafax\ReportingEngine\Core\DSL\OrderByField;
use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;
use Mostafax\ReportingEngine\Core\DSL\SelectField;
use Mostafax\ReportingEngine\Core\DSL\WindowFunction;
use Mostafax\ReportingEngine\Core\Formula\MySQLFormulaTranspiler;

/**
 * Translates a QueryDefinition into a Laravel Query Builder instance.
 *
 * No query is executed here — the caller decides whether to fetch,
 * count, paginate, or inspect the built query.
 *
 * Features:
 *   - Joins (inner / left / right / cross)
 *   - Aggregations (SUM, COUNT, AVG, MIN, MAX, COUNT DISTINCT)
 *   - Group-by: plain column, date_trunc (DATE_FORMAT), FormulaLexer expression
 *   - Window functions (ROW_NUMBER, RANK, SUM OVER, LAG, LEAD…)
 *   - HAVING clause (on aggregation aliases)
 *   - Computed fields via MySQLFormulaTranspiler
 *   - Filters with nested AND/OR groups
 *   - Tenant isolation
 */
final class MySQLQueryBuilder implements QueryBuilderInterface
{
    private readonly MySQLFormulaTranspiler $formulaTranspiler;

    public function __construct()
    {
        $this->formulaTranspiler = new MySQLFormulaTranspiler();
    }

    public function build(QueryDefinition $definition): Builder
    {
        $query = DB::connection($definition->connection)->table($definition->table);

        $this->applyJoins($query, $definition->joins);
        $this->applySelects($query, $definition->fields, $definition->aggregations, $definition->computed, $definition->groupBy, $definition->windows);
        $this->applyFilters($query, $definition->filters, $definition->tenantId);

        if ($definition->rlsFilters !== null) {
            $this->applyFilters($query, $definition->rlsFilters, null);
        }

        $this->applyGroupBy($query, $definition->groupBy);
        $this->applyHaving($query, $definition->having);
        $this->applyOrderBy($query, $definition->orderBy);

        return $query;
    }

    // ── Joins ─────────────────────────────────────────────────────────────────

    /** @param JoinClause[] $joins */
    private function applyJoins(Builder $query, array $joins): void
    {
        foreach ($joins as $join) {
            $tableExpr = $join->alias ? "{$join->table} as {$join->alias}" : $join->table;
            match (strtolower($join->type)) {
                'left'  => $query->leftJoin($tableExpr, $join->first, $join->operator, $join->second),
                'right' => $query->rightJoin($tableExpr, $join->first, $join->operator, $join->second),
                'cross' => $query->crossJoin($tableExpr),
                default => $query->join($tableExpr, $join->first, $join->operator, $join->second),
            };
        }
    }

    // ── SELECT ────────────────────────────────────────────────────────────────

    /**
     * @param SelectField[]      $fields
     * @param AggregationField[] $aggregations
     * @param array              $computed
     * @param GroupByField[]     $groupBy
     * @param WindowFunction[]   $windows
     */
    private function applySelects(
        Builder $query,
        array   $fields,
        array   $aggregations,
        array   $computed,
        array   $groupBy,
        array   $windows,
    ): void {
        $selects        = [];
        $allowedColumns = array_merge(
            array_map(fn(SelectField $f) => $f->alias ?? $f->column, $fields),
            array_map(fn(AggregationField $a) => $a->alias, $aggregations),
        );

        // Plain fields
        foreach ($fields as $field) {
            $col       = $this->quoteColumn($field->column);
            $selects[] = $field->alias
                ? DB::raw("{$col} as `{$field->alias}`")
                : DB::raw($col);
        }

        // Aggregation fields
        foreach ($aggregations as $agg) {
            $selects[] = DB::raw($this->compileAggregation($agg));
        }

        // Computed fields (FormulaLexer-validated expressions)
        foreach ($computed as $cf) {
            $sql       = $this->formulaTranspiler->transpile($cf->expression, $allowedColumns);
            $selects[] = DB::raw("({$sql}) as `{$cf->alias}`");
        }

        // GroupBy SELECT additions: date_trunc and expression types need a SELECT alias
        foreach ($groupBy as $gf) {
            if ($gf->type === 'date_trunc' || $gf->type === 'expression') {
                $compiled  = $this->compileGroupByField($gf);
                $alias     = $gf->alias ?? $gf->column;
                $selects[] = DB::raw("{$compiled} as `{$alias}`");
            }
        }

        // Window functions
        foreach ($windows as $wf) {
            $selects[] = DB::raw($this->compileWindowFunction($wf) . " as `{$wf->alias}`");
        }

        if (!empty($selects)) {
            $query->select($selects);
        }
    }

    private function compileAggregation(AggregationField $agg): string
    {
        $col = $this->quoteColumn($agg->column);

        if ($agg->function === 'count_distinct') {
            return "COUNT(DISTINCT {$col}) as `{$agg->alias}`";
        }

        $fn = strtoupper($agg->function);
        return "{$fn}({$col}) as `{$agg->alias}`";
    }

    // ── GROUP BY ──────────────────────────────────────────────────────────────

    /** @param GroupByField[] $groupBy */
    private function applyGroupBy(Builder $query, array $groupBy): void
    {
        foreach ($groupBy as $field) {
            if ($field->type === 'column') {
                // Plain column: use groupBy() so the builder handles quoting
                $query->groupBy($field->column);
            } else {
                // date_trunc / expression: use groupByRaw() with a pre-compiled expression
                $query->groupByRaw($this->compileGroupByField($field));
            }
        }
    }

    private function compileGroupByField(GroupByField $field): string
    {
        return match ($field->type) {
            'column'     => $this->quoteColumn($field->column),
            'date_trunc' => $this->compileDateTrunc($field),
            'expression' => "({$this->formulaTranspiler->transpile($field->expression ?? '')})",
            default      => $this->quoteColumn($field->column),
        };
    }

    /** Compile a date_trunc GroupByField to a safe DATE_FORMAT() expression. */
    private function compileDateTrunc(GroupByField $field): string
    {
        $col = $this->quoteColumn($field->column);

        $formats = [
            'year'    => "DATE_FORMAT({$col}, '%Y-01-01')",
            'quarter' => "MAKEDATE(YEAR({$col}), 1) + INTERVAL (QUARTER({$col}) - 1) QUARTER",
            'month'   => "DATE_FORMAT({$col}, '%Y-%m-01')",
            'week'    => "DATE_FORMAT({$col}, '%x-%v')",
            'day'     => "DATE({$col})",
            'hour'    => "DATE_FORMAT({$col}, '%Y-%m-%d %H:00:00')",
        ];

        return $formats[$field->granularity ?? ''] ?? "DATE({$col})";
    }

    // ── HAVING ────────────────────────────────────────────────────────────────

    private function applyHaving(Builder $query, ?FilterGroup $having): void
    {
        if ($having === null || $having->isEmpty()) {
            return;
        }

        $query->having(DB::raw('1'), '=', DB::raw('1')); // open HAVING clause
        $this->applyHavingGroup($query, $having);
    }

    private function applyHavingGroup(Builder $query, FilterGroup $group): void
    {
        foreach ($group->conditions as $condition) {
            if ($condition instanceof FilterGroup) {
                $this->applyHavingGroup($query, $condition);
            } elseif ($condition instanceof FilterCondition) {
                $this->applyHavingCondition($query, $condition, $group->operator);
            }
        }
    }

    private function applyHavingCondition(Builder $query, FilterCondition $condition, string $groupOperator): void
    {
        $col = $condition->field; // aggregation alias — already assertIdentifier validated
        $or  = $groupOperator === 'OR';

        match ($condition->operator) {
            'between'  => $or
                ? $query->orHavingBetween($col, $condition->value)
                : $query->havingBetween($col, $condition->value),
            'null'     => $or
                ? $query->orHavingNull($col)
                : $query->havingNull($col),
            'not_null' => $or
                ? $query->orHavingNotNull($col)
                : $query->havingNotNull($col),
            default    => $or
                ? $query->orHaving($col, $condition->operator, $condition->value)
                : $query->having($col, $condition->operator, $condition->value),
        };
    }

    // ── Window Functions ──────────────────────────────────────────────────────

    private function compileWindowFunction(WindowFunction $wf): string
    {
        $fn = strtoupper($wf->function);

        // Build function call
        $call = $this->compileWindowCall($wf, $fn);

        // Build OVER() clause
        $over = $this->compileOverClause($wf);

        return "{$call} OVER ({$over})";
    }

    private function compileWindowCall(WindowFunction $wf, string $fn): string
    {
        if ($wf->isRanking() || $fn === 'CUME_DIST' || $fn === 'PERCENT_RANK') {
            return "{$fn}()";
        }

        if ($wf->isBucket()) {
            return "{$fn}({$wf->ntile})";
        }

        if ($wf->isLagLead()) {
            $col     = $this->quoteColumn($wf->column ?? '');
            $default = $wf->default !== null ? ', ' . $this->escapeScalar($wf->default) : '';
            return "{$fn}({$col}, {$wf->offset}{$default})";
        }

        // Value functions: SUM, AVG, COUNT, MIN, MAX, FIRST_VALUE, LAST_VALUE
        $col = $this->quoteColumn($wf->column ?? '');
        return "{$fn}({$col})";
    }

    private function compileOverClause(WindowFunction $wf): string
    {
        $parts = [];

        if (!empty($wf->partitionBy)) {
            $cols    = implode(', ', array_map(fn($c) => $this->quoteColumn($c), $wf->partitionBy));
            $parts[] = "PARTITION BY {$cols}";
        }

        if (!empty($wf->orderBy)) {
            $orders  = implode(', ', array_map(
                fn(OrderByField $o) => $this->quoteColumn($o->column) . ' ' . strtoupper($o->direction),
                $wf->orderBy,
            ));
            $parts[] = "ORDER BY {$orders}";
        }

        return implode(' ', $parts);
    }

    // ── Filters ───────────────────────────────────────────────────────────────

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
            'in'               => $or ? $query->orWhereIn($condition->field, (array) $condition->value)    : $query->whereIn($condition->field, (array) $condition->value),
            'nin', 'not_in'    => $or ? $query->orWhereNotIn($condition->field, (array) $condition->value) : $query->whereNotIn($condition->field, (array) $condition->value),
            'between'          => $or ? $query->orWhereBetween($condition->field, $condition->value)        : $query->whereBetween($condition->field, $condition->value),
            'null'             => $or ? $query->orWhereNull($condition->field)                              : $query->whereNull($condition->field),
            'not_null'         => $or ? $query->orWhereNotNull($condition->field)                           : $query->whereNotNull($condition->field),
            'like', 'not_like' => $or
                ? $query->orWhere($condition->field, strtoupper($condition->operator), $condition->value)
                : $query->where($condition->field, strtoupper($condition->operator), $condition->value),
            default            => $or
                ? $query->orWhere($condition->field, $condition->operator, $condition->value)
                : $query->where($condition->field, $condition->operator, $condition->value),
        };
    }

    // ── ORDER BY ──────────────────────────────────────────────────────────────

    private function applyOrderBy(Builder $query, array $orderBy): void
    {
        foreach ($orderBy as $order) {
            $query->orderBy($order->column, $order->direction);
        }
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

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

    /** Escape a scalar value for use in a window function default clause. */
    private function escapeScalar(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        // String: wrap in single quotes, escape internal quotes
        return "'" . addslashes((string) $value) . "'";
    }
}
