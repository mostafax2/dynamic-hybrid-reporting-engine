<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Domain\Dashboard\ValueObjects;

enum WidgetType: string
{
    case Table      = 'table';
    case BarChart   = 'bar_chart';
    case LineChart  = 'line_chart';
    case PieChart   = 'pie_chart';
    case KpiCard    = 'kpi_card';
    case AreaChart  = 'area_chart';
    case HeatMap    = 'heatmap';

    public function supportsMultiSeries(): bool
    {
        return in_array($this, [self::BarChart, self::LineChart, self::AreaChart], true);
    }
}
