<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\Security;

use Mostafax\ReportingEngine\Core\DSL\AggregationField;
use Mostafax\ReportingEngine\Core\DSL\FilterCondition;
use Mostafax\ReportingEngine\Core\DSL\FilterGroup;
use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;
use Mostafax\ReportingEngine\Core\DSL\SelectField;
use Mostafax\ReportingEngine\Core\Validation\DslValidationException;

/**
 * Enforces field-level access control before query execution.
 *
 * Two modes:
 *  1. always_deny  — fields stripped from every query regardless of role
 *  2. role_deny    — additional denied fields per role
 *
 * The caller may inject the current user's roles via withRoles().
 */
final class FieldAccessControl
{
    /** @var string[] always-denied field patterns */
    private array $globalDeny;

    /** @var array<string,string[]> role → denied fields */
    private array $roleDeny;

    /** @var string[] active roles for the current request */
    private array $activeRoles = [];

    public function __construct(array $config = [])
    {
        $this->globalDeny = $config['always_deny'] ?? [];
        $this->roleDeny   = $config['role_deny']   ?? [];
    }

    public function withRoles(string ...$roles): self
    {
        $clone              = clone $this;
        $clone->activeRoles = $roles;
        return $clone;
    }

    /**
     * Strip denied fields from the definition and throw if a denied field
     * appears in a filter that cannot be safely removed.
     *
     * @throws DslValidationException
     */
    public function apply(QueryDefinition $definition): QueryDefinition
    {
        $denied = $this->buildDeniedSet();

        if (empty($denied)) {
            return $definition;
        }

        $this->assertFiltersNotDenied($definition->filters, $denied);

        // Reflect on the QueryDefinition readonly properties — we must reconstruct it
        return new QueryDefinition(
            source:       $definition->source,
            connection:   $definition->connection,
            table:        $definition->table,
            fields:       $this->filterSelectFields($definition->fields, $denied),
            aggregations: $this->filterAggregations($definition->aggregations, $denied),
            filters:      $definition->filters,
            groupBy:      array_values(array_filter($definition->groupBy, fn(string $f) => !$this->isDenied($f, $denied))),
            orderBy:      array_values(array_filter($definition->orderBy, fn($o) => !$this->isDenied($o->column, $denied))),
            pagination:   $definition->pagination,
            joins:        $definition->joins,
            options:      $definition->options,
            reportId:     $definition->reportId,
            tenantId:     $definition->tenantId,
        );
    }

    /** @return SelectField[] */
    private function filterSelectFields(array $fields, array $denied): array
    {
        return array_values(
            array_filter($fields, fn(SelectField $f) => !$this->isDenied($f->column, $denied))
        );
    }

    /** @return AggregationField[] */
    private function filterAggregations(array $aggs, array $denied): array
    {
        return array_values(
            array_filter($aggs, fn(AggregationField $a) => !$this->isDenied($a->column, $denied))
        );
    }

    /** @throws DslValidationException */
    private function assertFiltersNotDenied(?FilterGroup $group, array $denied): void
    {
        if ($group === null) return;

        foreach ($group->conditions as $condition) {
            if ($condition instanceof FilterGroup) {
                $this->assertFiltersNotDenied($condition, $denied);
            } elseif ($condition instanceof FilterCondition && $this->isDenied($condition->field, $denied)) {
                throw new DslValidationException(
                    "Access denied: field '{$condition->field}' may not be used in filters."
                );
            }
        }
    }

    private function isDenied(string $column, array $denied): bool
    {
        $bare = str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;

        foreach ($denied as $pattern) {
            if (str_ends_with($pattern, '*') && str_starts_with($bare, rtrim($pattern, '*'))) {
                return true;
            }
            if (str_starts_with($pattern, '*') && str_ends_with($bare, ltrim($pattern, '*'))) {
                return true;
            }
            if ($bare === $pattern) {
                return true;
            }
        }

        return false;
    }

    private function buildDeniedSet(): array
    {
        $denied = $this->globalDeny;

        foreach ($this->activeRoles as $role) {
            $denied = array_merge($denied, $this->roleDeny[$role] ?? []);
        }

        return array_unique($denied);
    }
}
