<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Contracts;

use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;

interface QueryBuilderInterface
{
    /**
     * Translate a QueryDefinition into the native query representation.
     *
     * MySQL  → Illuminate\Database\Query\Builder
     * Mongo  → array  (aggregation pipeline stages)
     */
    public function build(QueryDefinition $definition): mixed;
}
