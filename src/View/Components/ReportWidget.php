<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\View\Components;

use Illuminate\Contracts\View\View;
use Mostafax\ReportingEngine\Application\Services\ExecutionService;
use Mostafax\ReportingEngine\Contracts\ReportRepositoryInterface;
use Mostafax\ReportingEngine\Support\FilterFormBuilder;

/**
 * Full report widget: header + table + optional filter + optional export.
 *
 * Usage:
 *   <x-report-widget report="report-id-or-name" />
 *   <x-report-widget report="sales" :per-page="25" :show-export="true" />
 */
final class ReportWidget extends BaseReportComponent
{
    public function __construct(
        ExecutionService          $execution,
        ReportRepositoryInterface $repository,
        private FilterFormBuilder $filterBuilder,
        string  $report,
        ?string $title       = null,
        ?string $theme       = null,
        int     $perPage     = 10,
        int     $page        = 1,
        bool    $showTitle   = true,
        public readonly bool $showExport  = false,
        public readonly bool $showFilters = false,
    ) {
        parent::__construct($execution, $repository, $report, $title, $theme, $perPage, $page, $showTitle);
    }

    public function render(): View|\Closure
    {
        $reportEntity   = $this->resolveReport();
        $filterOverrides = $this->showFilters ? $this->filterBuilder->buildFilterOverrides() : [];
        $result          = $reportEntity ? $this->runReport($filterOverrides) : null;
        $filterFields    = ($this->showFilters && $reportEntity)
            ? $this->filterBuilder->build($reportEntity->definition)
            : [];

        return view('reporting-engine::components.report-widget', [
            'report'       => $reportEntity,
            'result'       => $result,
            'filterFields' => $filterFields,
            'theme'        => $this->resolvedTheme(),
            'isRtl'        => $this->isRtl(),
            'widgetId'     => $this->widgetId(),
            'resolvedTitle'=> $this->title ?? $reportEntity?->name,
        ]);
    }
}
