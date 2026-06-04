<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Mostafax\ReportingEngine\Core\Engine\ReportEngine;
use Mostafax\ReportingEngine\Domain\Execution\ExecutionResult;

/**
 * @method static ExecutionResult run(array|string $rawDsl, array $userRoles = [])
 * @method static \Mostafax\ReportingEngine\Core\DSL\QueryDefinition prepare(array|string $rawDsl, array $userRoles = [])
 * @method static ExecutionResult execute(\Mostafax\ReportingEngine\Core\DSL\QueryDefinition $definition)
 *
 * @see ReportEngine
 */
final class ReportingEngine extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ReportEngine::class;
    }
}
