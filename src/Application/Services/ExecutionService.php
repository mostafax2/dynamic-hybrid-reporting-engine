<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Application\Services;

use Illuminate\Contracts\Auth\Guard;
use Mostafax\ReportingEngine\Application\DTO\ExecutionResultDTO;
use Mostafax\ReportingEngine\Contracts\ReportRepositoryInterface;
use Mostafax\ReportingEngine\Core\Engine\ReportEngine;
use Mostafax\ReportingEngine\Domain\Report\Exceptions\ReportNotFoundException;
use Mostafax\ReportingEngine\Infrastructure\Persistence\Models\ReportExecutionModel;

/**
 * Orchestrates report execution and execution-history persistence.
 */
final class ExecutionService
{
    public function __construct(
        private readonly ReportEngine             $engine,
        private readonly ReportRepositoryInterface $repository,
    ) {}

    /**
     * Execute a saved report by its ID and return a paginated DTO.
     *
     * @param  string[] $userRoles
     * @throws ReportNotFoundException
     */
    public function runById(
        string  $reportId,
        array   $overrides  = [],
        array   $userRoles  = [],
        ?string $executedBy = null,
    ): ExecutionResultDTO {
        $report = $this->repository->findById($reportId);

        if ($report === null) {
            throw new ReportNotFoundException($reportId);
        }

        // Merge saved definition with runtime overrides (e.g. pagination, extra filters)
        $definition = array_replace_recursive($report->definition, $overrides);
        $definition['report_id'] = $reportId;
        $definition['tenant_id'] = $definition['tenant_id'] ?? $report->tenantId;

        // Override cache TTL from saved report setting
        if (!$report->isCached) {
            $definition['options']['cache_ttl'] = 0;
        } elseif (!isset($definition['options']['cache_ttl'])) {
            $definition['options']['cache_ttl'] = $report->cacheTtl;
        }

        $result = $this->engine->run($definition, $userRoles);

        $this->recordExecution($reportId, $result, $executedBy, $report->tenantId);

        $pagination = $definition['pagination'] ?? [];

        return ExecutionResultDTO::fromResult(
            $result,
            (int) ($pagination['page'] ?? 1),
            (int) ($pagination['per_page'] ?? config('reporting-engine.limits.default_per_page', 25)),
        );
    }

    /**
     * Execute an ad-hoc DSL (not saved to the database).
     *
     * @param  string[] $userRoles
     */
    public function runAdHoc(
        array|string $rawDsl,
        array        $userRoles = [],
    ): ExecutionResultDTO {
        $preparedDefinition = $this->engine->prepare($rawDsl, $userRoles);
        $result             = $this->engine->execute($preparedDefinition);

        return ExecutionResultDTO::fromResult(
            $result,
            $preparedDefinition->pagination->page,
            $preparedDefinition->pagination->perPage,
        );
    }

    private function recordExecution(
        string         $reportId,
        \Mostafax\ReportingEngine\Domain\Execution\ExecutionResult $result,
        ?string        $executedBy,
        ?string        $tenantId,
    ): void {
        ReportExecutionModel::create([
            'report_id'         => $reportId,
            'tenant_id'         => $tenantId,
            'executed_by'       => $executedBy,
            'execution_time_ms' => $result->metadata->executionTimeMs,
            'row_count'         => $result->metadata->rowCount,
            'memory_bytes'      => $result->metadata->memoryUsageBytes,
            'cache_hit'         => $result->metadata->cacheHit,
            'source'            => $result->metadata->source,
            'query_hash'        => $result->metadata->queryHash,
            'status'            => 'success',
            'executed_at'       => $result->metadata->executedAt,
        ]);
    }
}
