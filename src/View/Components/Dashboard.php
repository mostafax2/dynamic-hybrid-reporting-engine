<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Mostafax\ReportingEngine\Application\Services\ExecutionService;
use Mostafax\ReportingEngine\Contracts\ReportRepositoryInterface;
use Mostafax\ReportingEngine\Infrastructure\Persistence\Models\DashboardModel;
use Mostafax\ReportingEngine\Support\ThemeDetector;

/**
 * Renders a complete saved Dashboard with all its Widgets.
 *
 * Each widget's `type` determines which component is used:
 *   table     → ReportWidget
 *   kpi_card  → KpiWidget
 *   bar_chart / line_chart / pie_chart → ChartWidget
 *
 * Usage:
 *   <x-dashboard slug="ceo-dashboard" />
 *   <x-dashboard slug="ceo-dashboard" :cols="2" theme="tailwind" />
 */
final class Dashboard extends Component
{
    public function __construct(
        private readonly ExecutionService          $execution,
        private readonly ReportRepositoryInterface $repository,
        public readonly string  $slug,
        public readonly ?string $theme    = null,
        public readonly int     $cols     = 2,
        public readonly bool    $showTitle = true,
    ) {}

    public function render(): View|\Closure
    {
        $dashboard = DashboardModel::with('widgets.report')
            ->where(fn($q) => $q->where('id', $this->slug)->orWhere('name', $this->slug))
            ->first();

        $widgets = [];
        if ($dashboard) {
            foreach ($dashboard->widgets()->orderBy('position_y')->orderBy('position_x')->get() as $widget) {
                $widgets[] = [
                    'id'        => $widget->id,
                    'title'     => $widget->title,
                    'type'      => $widget->type,
                    'report_id' => $widget->report_id,
                    'config'    => $widget->config ?? [],
                    'width'     => $widget->width,
                    'height'    => $widget->height,
                ];
            }
        }

        return view('reporting-engine::components.dashboard', [
            'dashboard'     => $dashboard,
            'widgets'       => $widgets,
            'cols'          => $this->cols,
            'theme'         => ThemeDetector::resolve($this->theme),
            'isRtl'         => ThemeDetector::isRtl(),
        ]);
    }
}
