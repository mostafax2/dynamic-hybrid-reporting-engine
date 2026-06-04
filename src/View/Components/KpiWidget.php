<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\View\Components;

use Illuminate\Contracts\View\View;

/**
 * Renders aggregation results as KPI cards — one card per aggregation alias.
 *
 * Usage:
 *   <x-kpi-widget report="revenue-summary" />
 *   <x-kpi-widget report="revenue-summary" :cols="4" />
 */
final class KpiWidget extends BaseReportComponent
{
    public function __construct(
        \Mostafax\ReportingEngine\Application\Services\ExecutionService $execution,
        \Mostafax\ReportingEngine\Contracts\ReportRepositoryInterface    $repository,
        string  $report,
        ?string $title   = null,
        ?string $theme   = null,
        int     $perPage = 1,    // KPI: we only need 1 aggregated row
        int     $page    = 1,
        bool    $showTitle = true,
        public readonly int $cols = 4,
        public readonly ?string $color = null,
    ) {
        parent::__construct($execution, $repository, $report, $title, $theme, $perPage, $page, $showTitle);
    }

    public function render(): View|\Closure
    {
        $reportEntity = $this->resolveReport();
        $result       = $reportEntity ? $this->runReport() : null;

        // KPI: use the first row's values mapped by column name
        $kpis = [];
        if ($result && !empty($result->data)) {
            $row          = $result->data[0];
            $aggregations = $reportEntity?->definition['aggregations'] ?? [];

            foreach ($row as $key => $value) {
                // Find a friendly label from the aggregation definition
                $agg   = collect($aggregations)->firstWhere('alias', $key);
                $label = $agg
                    ? ucwords(str_replace('_', ' ', $agg['alias']))
                    : ucwords(str_replace('_', ' ', $key));

                $kpis[] = ['label' => $label, 'value' => $value, 'key' => $key];
            }
        }

        return view('reporting-engine::components.kpi-widget', [
            'report'        => $reportEntity,
            'kpis'          => $kpis,
            'cols'          => $this->cols,
            'color'         => $this->color,
            'theme'         => $this->resolvedTheme(),
            'isRtl'         => $this->isRtl(),
            'widgetId'      => $this->widgetId(),
            'resolvedTitle' => $this->title ?? $reportEntity?->name,
        ]);
    }
}
