<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\View\Components;

use Illuminate\Contracts\View\View;
use Mostafax\ReportingEngine\Support\FilterFormBuilder;

/**
 * Renders a filter form for the given report.
 *
 * Submits via GET to the current URL appending dhr_filter_* query parameters.
 * The ReportWidget reads these params via FilterFormBuilder::buildFilterOverrides().
 *
 * Usage:
 *   <x-report-filter report="sales-report" />
 *   <x-report-filter report="sales-report" :inline="true" />
 */
final class ReportFilter extends BaseReportComponent
{
    public function __construct(
        \Mostafax\ReportingEngine\Application\Services\ExecutionService $execution,
        \Mostafax\ReportingEngine\Contracts\ReportRepositoryInterface    $repository,
        private FilterFormBuilder $filterBuilder,
        string  $report,
        ?string $title   = null,
        ?string $theme   = null,
        bool    $showTitle = false,
        public readonly bool $inline = false,
    ) {
        parent::__construct($execution, $repository, $report, $title, $theme, 1, 1, $showTitle);
    }

    public function render(): View|\Closure
    {
        $reportEntity = $this->resolveReport();
        $fields       = $reportEntity ? $this->filterBuilder->build($reportEntity->definition) : [];

        return view('reporting-engine::components.report-filter', [
            'report'   => $reportEntity,
            'fields'   => $fields,
            'inline'   => $this->inline,
            'action'   => request()->url(),
            'theme'    => $this->resolvedTheme(),
            'isRtl'    => $this->isRtl(),
            'widgetId' => $this->widgetId(),
        ]);
    }
}
