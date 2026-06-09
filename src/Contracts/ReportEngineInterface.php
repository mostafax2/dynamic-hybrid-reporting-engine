<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Contracts;

use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;
use Mostafax\ReportingEngine\Core\Validation\DslValidationException;
use Mostafax\ReportingEngine\Domain\Execution\ExecutionResult;

interface ReportEngineInterface
{
    /**
     * Execute a DSL definition supplied as a raw array or JSON string.
     *
     * @param  string[] $userRoles
     * @throws DslValidationException
     */
    public function run(array|string $rawDsl, array $userRoles = []): ExecutionResult;

    /**
     * Execute a pre-built QueryDefinition (used by async jobs).
     *
     * @throws DslValidationException
     */
    public function execute(QueryDefinition $definition): ExecutionResult;

    /**
     * Parse → validate → sanitize → ACL filter, return ready-to-execute definition.
     *
     * @param  string[] $userRoles
     * @throws DslValidationException
     */
    public function prepare(array|string $rawDsl, array $userRoles = []): QueryDefinition;
}
