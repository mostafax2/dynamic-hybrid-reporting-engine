<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\DSL;

/**
 * Immutable, fully-typed representation of a report DSL query.
 * This is the central value object passed through every layer of the engine.
 */
final readonly class QueryDefinition
{
    /**
     * @param  SelectField[]      $fields
     * @param  AggregationField[] $aggregations
     * @param  string[]           $groupBy
     * @param  OrderByField[]     $orderBy
     * @param  JoinClause[]       $joins
     * @param  array<string,mixed>$options
     */
    public function __construct(
        public string       $source,
        public string       $connection,
        public string       $table,
        public array        $fields        = [],
        public array        $aggregations  = [],
        public ?FilterGroup $filters       = null,
        public array        $groupBy       = [],
        public array        $orderBy       = [],
        public Pagination   $pagination    = new Pagination(),
        public array        $joins         = [],
        public array        $options       = [],
        public ?string      $reportId      = null,
        public ?string      $tenantId      = null,
    ) {}

    public function isAggregation(): bool
    {
        return !empty($this->aggregations) || !empty($this->groupBy);
    }

    public function hasTenantFilter(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Stable hash of this definition (used as cache key).
     */
    public function hash(): string
    {
        return md5(serialize([
            $this->source,
            $this->connection,
            $this->table,
            $this->fields,
            $this->aggregations,
            $this->filters,
            $this->groupBy,
            $this->orderBy,
            $this->pagination,
            $this->joins,
            $this->options,
            $this->tenantId,
        ]));
    }
}
