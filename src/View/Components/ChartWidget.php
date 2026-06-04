<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\View\Components;

use Illuminate\Contracts\View\View;
use Mostafax\ReportingEngine\Support\ChartDataFormatter;

/**
 * Renders report data as a Chart.js chart with SSR table fallback.
 *
 * The <canvas> is only shown when Chart.js is available in the host app.
 * The table fallback is always rendered for SEO and no-JS environments.
 *
 * Usage:
 *   <x-chart-widget report="monthly-sales" chart-type="bar" />
 *   <x-chart-widget report="revenue-by-category" chart-type="pie" label-column="category" value-column="total" />
 */
final class ChartWidget extends BaseReportComponent
{
    public function __construct(
        \Mostafax\ReportingEngine\Application\Services\ExecutionService $execution,
        \Mostafax\ReportingEngine\Contracts\ReportRepositoryInterface    $repository,
        private ChartDataFormatter $formatter,
        string  $report,
        ?string $title        = null,
        ?string $theme        = null,
        int     $perPage      = 500,
        int     $page         = 1,
        bool    $showTitle    = true,
        public readonly string  $chartType    = 'bar',
        public readonly ?string $labelColumn  = null,
        public readonly ?string $valueColumn  = null,
        public readonly int     $height       = 300,
    ) {
        parent::__construct($execution, $repository, $report, $title, $theme, $perPage, $page, $showTitle);
    }

    public function render(): View|\Closure
    {
        $reportEntity = $this->resolveReport();
        $result       = $reportEntity ? $this->runReport() : null;

        $chartConfig = null;
        if ($result && !empty($result->data)) {
            $detected    = $this->formatter->autoDetectColumns(
                $reportEntity?->definition ?? [],
                $result->data[0],
            );
            $labelCol    = $this->labelColumn ?? $detected['label'];
            $valueCol    = $this->valueColumn ?? $detected['value'];

            if ($labelCol && $valueCol) {
                $chartConfig = $this->formatter->format(
                    $result->data,
                    $labelCol,
                    $valueCol,
                    $this->chartType,
                    $reportEntity?->name,
                );
            }
        }

        return view('reporting-engine::components.chart-widget', [
            'report'        => $reportEntity,
            'result'        => $result,
            'chartConfig'   => $chartConfig ? json_encode($chartConfig, JSON_UNESCAPED_UNICODE) : null,
            'height'        => $this->height,
            'theme'         => $this->resolvedTheme(),
            'isRtl'         => $this->isRtl(),
            'widgetId'      => $this->widgetId(),
            'resolvedTitle' => $this->title ?? $reportEntity?->name,
        ]);
    }
}
