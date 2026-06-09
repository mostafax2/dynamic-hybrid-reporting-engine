<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\Builders;

use Mostafax\ReportingEngine\Contracts\QueryBuilderInterface;
use Mostafax\ReportingEngine\Core\DSL\AggregationField;
use Mostafax\ReportingEngine\Core\DSL\FilterCondition;
use Mostafax\ReportingEngine\Core\DSL\FilterGroup;
use Mostafax\ReportingEngine\Core\DSL\GroupByField;
use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;
use Mostafax\ReportingEngine\Core\Formula\MongoFormulaTranspiler;

/**
 * Translates a QueryDefinition into a MongoDB aggregation pipeline array.
 *
 * Returned value: array<int, array<string, mixed>>  (pipeline stages)
 *
 * GroupByField types handled:
 *   column     → '$fieldName'  (standard Mongo field ref)
 *   date_trunc → $dateTrunc / $dateToString expression (no raw string injection)
 *   expression → MongoFormulaTranspiler (allowlisted token set)
 */
final class MongoAggregationBuilder implements QueryBuilderInterface
{
    private readonly MongoFormulaTranspiler $formulaTranspiler;

    public function __construct()
    {
        $this->formulaTranspiler = new MongoFormulaTranspiler();
    }

    /** @return array<int, array<string,mixed>> */
    public function build(QueryDefinition $definition): array
    {
        $pipeline = [];

        $matchStage = $this->buildMatchStage($definition);
        if (!empty($matchStage)) {
            $pipeline[] = ['$match' => $matchStage];
        }

        if (!empty($definition->computed)) {
            $pipeline[] = ['$addFields' => $this->buildComputedFields($definition)];
        }

        if ($definition->isAggregation()) {
            $pipeline[] = ['$group' => $this->buildGroupStage($definition)];
            $projectStage = $this->buildPostGroupProject($definition);
            if (!empty($projectStage)) {
                $pipeline[] = ['$project' => $projectStage];
            }
        } else {
            $projectStage = $this->buildSimpleProject($definition);
            if (!empty($projectStage)) {
                $pipeline[] = ['$project' => $projectStage];
            }
        }

        if (!empty($definition->orderBy)) {
            $pipeline[] = ['$sort' => $this->buildSortStage($definition)];
        }

        $pipeline[] = [
            '$facet' => [
                'data' => [
                    ['$skip'  => $definition->pagination->offset],
                    ['$limit' => $definition->pagination->perPage],
                ],
                'totalCount' => [
                    ['$count' => 'count'],
                ],
            ],
        ];

        return $pipeline;
    }

    /**
     * Builds the pipeline WITHOUT $facet — used for streaming export.
     *
     * @return array<int, array<string,mixed>>
     */
    public function buildForExport(QueryDefinition $definition): array
    {
        $pipeline = [];

        $matchStage = $this->buildMatchStage($definition);
        if (!empty($matchStage)) {
            $pipeline[] = ['$match' => $matchStage];
        }

        if ($definition->isAggregation()) {
            $pipeline[] = ['$group' => $this->buildGroupStage($definition)];
            $postProject = $this->buildPostGroupProject($definition);
            if (!empty($postProject)) {
                $pipeline[] = ['$project' => $postProject];
            }
        } else {
            $simpleProject = $this->buildSimpleProject($definition);
            if (!empty($simpleProject)) {
                $pipeline[] = ['$project' => $simpleProject];
            }
        }

        if (!empty($definition->orderBy)) {
            $pipeline[] = ['$sort' => $this->buildSortStage($definition)];
        }

        return $pipeline;
    }

    // ── Stage builders ────────────────────────────────────────────────────────

    private function buildComputedFields(QueryDefinition $definition): array
    {
        $allowedColumns = array_merge(
            array_map(fn($f) => $f->alias ?? $f->column, $definition->fields),
            array_map(fn($a) => $a->alias, $definition->aggregations),
        );

        $addFields = [];
        foreach ($definition->computed as $cf) {
            $addFields[$cf->alias] = $this->formulaTranspiler->transpile($cf->expression, $allowedColumns);
        }
        return $addFields;
    }

    private function buildMatchStage(QueryDefinition $definition): array
    {
        $match = [];

        if ($definition->tenantId !== null) {
            $tenantColumn = function_exists('config')
                ? config('reporting-engine.multi_tenancy.tenant_column', 'tenant_id')
                : 'tenant_id';
            $match[$tenantColumn] = $definition->tenantId;
        }

        if ($definition->filters !== null && !$definition->filters->isEmpty()) {
            $match = array_merge($match, $this->buildFilterMatch($definition->filters));
        }

        if ($definition->rlsFilters !== null && !$definition->rlsFilters->isEmpty()) {
            $match = array_merge($match, $this->buildFilterMatch($definition->rlsFilters));
        }

        return $match;
    }

    private function buildFilterMatch(FilterGroup $group): array
    {
        $operator   = $group->operator === 'OR' ? '$or' : '$and';
        $conditions = [];

        foreach ($group->conditions as $condition) {
            if ($condition instanceof FilterGroup) {
                $conditions[] = $this->buildFilterMatch($condition);
            } elseif ($condition instanceof FilterCondition) {
                $conditions[] = $this->buildFilterCondition($condition);
            }
        }

        if (count($conditions) === 1 && $operator === '$and') {
            return $conditions[0];
        }

        return [$operator => $conditions];
    }

    private function buildFilterCondition(FilterCondition $condition): array
    {
        return match ($condition->operator) {
            '='              => [$condition->field => $condition->value],
            '!=', '<>'       => [$condition->field => ['$ne'  => $condition->value]],
            '>'              => [$condition->field => ['$gt'  => $condition->value]],
            '>='             => [$condition->field => ['$gte' => $condition->value]],
            '<'              => [$condition->field => ['$lt'  => $condition->value]],
            '<='             => [$condition->field => ['$lte' => $condition->value]],
            'in'             => [$condition->field => ['$in'  => (array) $condition->value]],
            'nin', 'not_in'  => [$condition->field => ['$nin' => (array) $condition->value]],
            'between'        => [$condition->field => ['$gte' => $condition->value[0], '$lte' => $condition->value[1]]],
            'like'           => [$condition->field => ['$regex' => $this->likeToRegex($condition->value), '$options' => 'i']],
            'not_like'       => [$condition->field => ['$not' => ['$regex' => $this->likeToRegex($condition->value), '$options' => 'i']]],
            'null'           => [$condition->field => null],
            'not_null'       => [$condition->field => ['$ne' => null]],
            default          => [$condition->field => $condition->value],
        };
    }

    // ── Group stage ───────────────────────────────────────────────────────────

    /**
     * @param GroupByField[] $groupBy
     */
    private function buildGroupStage(QueryDefinition $definition): array
    {
        $groupBy = $definition->groupBy;

        $groupId = $this->buildGroupId($groupBy);

        $stage = ['_id' => $groupId];

        foreach ($definition->aggregations as $agg) {
            $stage[$agg->alias] = $this->buildAggExpression($agg);
        }

        // Preserve non-grouped select fields using $first
        $groupedColumns = array_map(fn(GroupByField $f) => $f->column, $groupBy);
        foreach ($definition->fields as $field) {
            if (!in_array($field->column, $groupedColumns, true)) {
                $stage[$field->alias ?? $field->column] = ['$first' => '$' . $field->column];
            }
        }

        return $stage;
    }

    /**
     * Build the _id expression for $group stage from GroupByField[].
     *
     * @param GroupByField[] $groupBy
     */
    private function buildGroupId(array $groupBy): mixed
    {
        if (empty($groupBy)) {
            return null;
        }

        if (count($groupBy) === 1) {
            return $this->compileGroupByFieldExpr($groupBy[0]);
        }

        $id = [];
        foreach ($groupBy as $field) {
            $key       = str_replace(['.', '-'], '_', $field->outputName());
            $id[$key]  = $this->compileGroupByFieldExpr($field);
        }
        return $id;
    }

    /**
     * Compile a single GroupByField into a MongoDB expression for $group._id.
     */
    private function compileGroupByFieldExpr(GroupByField $field): mixed
    {
        return match ($field->type) {
            'date_trunc' => $this->compileDateTruncExpr($field),
            'expression' => ['$literal' => $field->expression], // FormulaTranspiler for Mongo not yet supported
            default      => '$' . $field->column,
        };
    }

    /**
     * Compile a date_trunc GroupByField into a MongoDB $dateTrunc expression.
     * Available from MongoDB 5.0+; falls back to $dateToString for older versions.
     */
    private function compileDateTruncExpr(GroupByField $field): array
    {
        $dateRef = '$' . $field->column;

        $unitMap = [
            'year'    => 'year',
            'quarter' => 'quarter',
            'month'   => 'month',
            'week'    => 'week',
            'day'     => 'day',
            'hour'    => 'hour',
        ];

        $unit = $unitMap[$field->granularity ?? ''] ?? 'day';

        return [
            '$dateTrunc' => [
                'date' => ['$toDate' => $dateRef],
                'unit' => $unit,
            ],
        ];
    }

    private function buildAggExpression(AggregationField $agg): array
    {
        return match ($agg->function) {
            'sum'            => ['$sum'      => '$' . $agg->column],
            'count'          => ['$sum'      => 1],
            'count_distinct' => ['$addToSet' => '$' . $agg->column],
            'avg'            => ['$avg'      => '$' . $agg->column],
            'min'            => ['$min'      => '$' . $agg->column],
            'max'            => ['$max'      => '$' . $agg->column],
            default          => ['$sum'      => '$' . $agg->column],
        };
    }

    // ── Project stages ────────────────────────────────────────────────────────

    private function buildPostGroupProject(QueryDefinition $definition): array
    {
        $project = ['_id' => 0];
        $groupBy = $definition->groupBy;
        $isMulti = count($groupBy) > 1;

        foreach ($groupBy as $field) {
            $key             = str_replace(['.', '-'], '_', $field->outputName());
            $project[$field->outputName()] = $isMulti ? '$_id.' . $key : '$_id';
        }

        foreach ($definition->aggregations as $agg) {
            $project[$agg->alias] = $agg->function === 'count_distinct'
                ? ['$size' => '$' . $agg->alias]
                : 1;
        }

        $groupedColumns = array_map(fn(GroupByField $f) => $f->column, $groupBy);
        foreach ($definition->fields as $field) {
            if (!in_array($field->column, $groupedColumns, true)) {
                $project[$field->alias ?? $field->column] = 1;
            }
        }

        return $project;
    }

    private function buildSimpleProject(QueryDefinition $definition): array
    {
        if (empty($definition->fields)) {
            return [];
        }

        $project = ['_id' => 0];
        foreach ($definition->fields as $field) {
            $project[$field->alias ?? $field->column] = '$' . $field->column;
        }
        return $project;
    }

    private function buildSortStage(QueryDefinition $definition): array
    {
        $sort = [];
        foreach ($definition->orderBy as $order) {
            $sort[$order->column] = $order->direction === 'asc' ? 1 : -1;
        }
        return $sort;
    }

    private function likeToRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');
        return str_replace(['%', '_'], ['.*', '.'], $escaped);
    }
}
