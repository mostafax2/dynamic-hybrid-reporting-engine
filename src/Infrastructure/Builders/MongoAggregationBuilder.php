<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\Builders;

use Mostafax\ReportingEngine\Contracts\QueryBuilderInterface;
use Mostafax\ReportingEngine\Core\DSL\AggregationField;
use Mostafax\ReportingEngine\Core\DSL\FilterCondition;
use Mostafax\ReportingEngine\Core\DSL\FilterGroup;
use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;

/**
 * Translates a QueryDefinition into a MongoDB aggregation pipeline array.
 *
 * Returned value: array<int, array<string, mixed>>  (pipeline stages)
 */
final class MongoAggregationBuilder implements QueryBuilderInterface
{
    /** @return array<int, array<string,mixed>> */
    public function build(QueryDefinition $definition): array
    {
        $pipeline = [];

        $matchStage = $this->buildMatchStage($definition);
        if (!empty($matchStage)) {
            $pipeline[] = ['$match' => $matchStage];
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

        // Facet stage for total count + paginated data in one round-trip
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

    // ────────────────────────────────────────────────────────────
    // Stage builders
    // ────────────────────────────────────────────────────────────

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
            $filterMatch = $this->buildFilterMatch($definition->filters);
            $match       = array_merge($match, $filterMatch);
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

        // Single AND condition can be merged directly (avoids unnecessary nesting)
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
            'not_like'       => [$condition->field => ['$not'  => ['$regex' => $this->likeToRegex($condition->value), '$options' => 'i']]],
            'null'           => [$condition->field => null],
            'not_null'       => [$condition->field => ['$ne' => null]],
            default          => [$condition->field => $condition->value],
        };
    }

    private function buildGroupStage(QueryDefinition $definition): array
    {
        // _id: composite key for multi-field group, scalar for single, null for full-set aggregation
        if (empty($definition->groupBy)) {
            $groupId = null;
        } elseif (count($definition->groupBy) === 1) {
            $groupId = '$' . $definition->groupBy[0];
        } else {
            $groupId = [];
            foreach ($definition->groupBy as $field) {
                $key           = str_replace(['.', '-'], '_', $field);
                $groupId[$key] = '$' . $field;
            }
        }

        $stage = ['_id' => $groupId];

        foreach ($definition->aggregations as $agg) {
            $stage[$agg->alias] = $this->buildAggExpression($agg);
        }

        // Preserve non-grouped select fields using $first
        foreach ($definition->fields as $field) {
            if (!in_array($field->column, $definition->groupBy, true)) {
                $stage[$field->alias ?? $field->column] = ['$first' => '$' . $field->column];
            }
        }

        return $stage;
    }

    private function buildAggExpression(AggregationField $agg): array
    {
        return match ($agg->function) {
            'sum'            => ['$sum'  => '$' . $agg->column],
            'count'          => ['$sum'  => 1],
            'count_distinct' => ['$addToSet' => '$' . $agg->column], // unwound later
            'avg'            => ['$avg'  => '$' . $agg->column],
            'min'            => ['$min'  => '$' . $agg->column],
            'max'            => ['$max'  => '$' . $agg->column],
            default          => ['$sum'  => '$' . $agg->column],
        };
    }

    private function buildPostGroupProject(QueryDefinition $definition): array
    {
        $project = ['_id' => 0];

        // Re-expose grouped fields from _id
        foreach ($definition->groupBy as $field) {
            $key = str_replace(['.', '-'], '_', $field);
            $project[$field] = count($definition->groupBy) === 1
                ? '$_id'
                : '$_id.' . $key;
        }

        // Expose aggregation aliases — convert $addToSet results to $size for count_distinct
        foreach ($definition->aggregations as $agg) {
            $project[$agg->alias] = $agg->function === 'count_distinct'
                ? ['$size' => '$' . $agg->alias]
                : 1;
        }

        // Expose preserved fields
        foreach ($definition->fields as $field) {
            if (!in_array($field->column, $definition->groupBy, true)) {
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
            $key           = $field->alias ?? $field->column;
            $project[$key] = '$' . $field->column;
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
