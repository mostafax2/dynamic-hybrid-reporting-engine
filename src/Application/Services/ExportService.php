<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Application\Services;

use Mostafax\ReportingEngine\Contracts\ReportRepositoryInterface;
use Mostafax\ReportingEngine\Core\Engine\ReportEngine;
use Mostafax\ReportingEngine\Domain\Report\Exceptions\ReportNotFoundException;
use Mostafax\ReportingEngine\Exporters\ExporterFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportService
{
    public function __construct(
        private readonly ReportEngine             $engine,
        private readonly ReportRepositoryInterface $repository,
        private readonly ExporterFactory          $exporterFactory,
    ) {}

    /**
     * Stream a full (unpaginated) export of a saved report.
     *
     * @param  string[] $userRoles
     * @throws ReportNotFoundException
     */
    public function exportById(
        string $reportId,
        string $format,
        array  $userRoles = [],
    ): StreamedResponse {
        $report = $this->repository->findById($reportId);

        if ($report === null) {
            throw new ReportNotFoundException($reportId);
        }

        $definition            = $report->definition;
        $definition['report_id'] = $reportId;
        $definition['tenant_id'] = $report->tenantId;

        // For exports we disable pagination and pull all rows in chunks
        $definition['pagination'] = [
            'page'     => 1,
            'per_page' => config('reporting-engine.export.max_export_rows', 100_000),
        ];

        // Bypass cache for full exports
        $definition['options']['cache_ttl'] = 0;

        $preparedDefinition = $this->engine->prepare($definition, $userRoles);
        $result             = $this->engine->execute($preparedDefinition);

        $exporter = $this->exporterFactory->make($format);
        $filename = $this->sanitizeFilename($report->name);

        return $exporter->stream($result->data, $filename);
    }

    /**
     * Stream an ad-hoc DSL export without saving the report first.
     *
     * @param  string[] $userRoles
     */
    public function exportAdHoc(
        array|string $rawDsl,
        string       $format,
        array        $userRoles = [],
        string       $filename  = 'report',
    ): StreamedResponse {
        $definition                   = is_string($rawDsl) ? json_decode($rawDsl, true) : $rawDsl;
        $definition['pagination']     = ['page' => 1, 'per_page' => config('reporting-engine.export.max_export_rows', 100_000)];
        $definition['options']['cache_ttl'] = 0;

        $preparedDefinition = $this->engine->prepare($definition, $userRoles);
        $result             = $this->engine->execute($preparedDefinition);

        $exporter = $this->exporterFactory->make($format);

        return $exporter->stream($result->data, $this->sanitizeFilename($filename));
    }

    private function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name) . '_' . date('Ymd_His');
    }
}
