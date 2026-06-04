<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Contracts;

use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;
use Mostafax\ReportingEngine\Domain\Execution\ExecutionResult;

interface DataSourceInterface
{
    /**
     * Execute a SELECT / find query and return paginated rows.
     */
    public function query(QueryDefinition $definition): ExecutionResult;

    /**
     * Execute an aggregation pipeline and return grouped results.
     */
    public function aggregate(QueryDefinition $definition): ExecutionResult;

    /**
     * Return total row count for pagination metadata (no LIMIT applied).
     */
    public function count(QueryDefinition $definition): int;

    /**
     * Whether this adapter can handle the given source type string (e.g. 'mysql').
     */
    public function supports(string $sourceType): bool;
}
