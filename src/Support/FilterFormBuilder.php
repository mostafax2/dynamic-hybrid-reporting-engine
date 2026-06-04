<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Support;

/**
 * Derives a set of form field definitions from a report DSL definition.
 *
 * Only generates fields that are meaningful to expose as user-facing filters.
 * Heuristic: column names containing certain keywords map to input types.
 */
final class FilterFormBuilder
{
    /** @return array<int, array{field:string, label:string, type:string, current:mixed}> */
    public function build(array $definition): array
    {
        $fields     = [];
        $seen       = [];
        $requestAll = request()->all();

        $columns = array_merge(
            array_map(
                fn($f) => is_string($f) ? $f : ($f['column'] ?? null),
                $definition['fields'] ?? [],
            ),
            array_map(
                fn($g) => $g,
                $definition['group_by'] ?? $definition['groupBy'] ?? [],
            ),
        );

        foreach (array_filter($columns) as $column) {
            $bare = str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;

            if (isset($seen[$bare])) {
                continue;
            }
            $seen[$bare] = true;

            $filterKey = 'dhr_filter_' . $bare;

            $fields[] = [
                'field'   => $bare,
                'label'   => $this->humanize($bare),
                'type'    => $this->guessType($bare),
                'current' => $requestAll[$filterKey] ?? null,
                'key'     => $filterKey,
            ];
        }

        return $fields;
    }

    /**
     * Turn the active dhr_filter_* request params back into a DSL filter group
     * that can be passed as an override to ExecutionService::runById.
     */
    public function buildFilterOverrides(): array
    {
        $conditions = [];

        foreach (request()->all() as $key => $value) {
            if (!str_starts_with($key, 'dhr_filter_') || $value === '' || $value === null) {
                continue;
            }

            $field      = substr($key, strlen('dhr_filter_'));
            $conditions[] = ['field' => $field, 'operator' => 'like', 'value' => "%{$value}%"];
        }

        if (empty($conditions)) {
            return [];
        }

        return ['filters' => ['operator' => 'AND', 'conditions' => $conditions]];
    }

    private function guessType(string $column): string
    {
        if (preg_match('/(date|_at|_on|_time|created|updated|timestamp)/', $column)) {
            return 'date';
        }
        if (preg_match('/(amount|total|count|price|qty|quantity|number|num|rate|percent)/', $column)) {
            return 'number';
        }
        if (preg_match('/(status|type|category|kind|state|role|group)/', $column)) {
            return 'text'; // select would need enum values which we don't have
        }
        return 'text';
    }

    private function humanize(string $column): string
    {
        return ucwords(str_replace(['_', '.', '-'], ' ', $column));
    }
}
