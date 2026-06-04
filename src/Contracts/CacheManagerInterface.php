<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Contracts;

use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;
use Mostafax\ReportingEngine\Domain\Execution\ExecutionResult;

interface CacheManagerInterface
{
    public function get(QueryDefinition $definition): ?ExecutionResult;

    public function put(QueryDefinition $definition, ExecutionResult $result): void;

    public function forget(QueryDefinition $definition): void;

    public function forgetByReportId(string $reportId): void;

    public function buildKey(QueryDefinition $definition): string;
}
