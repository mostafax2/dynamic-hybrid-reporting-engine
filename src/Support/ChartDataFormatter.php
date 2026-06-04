<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Support;

/**
 * Converts raw ExecutionResult rows into a Chart.js-compatible config object.
 *
 * Only formats the data — does not embed Chart.js itself.
 * The view uses a <canvas data-chart="..."> + optional JS to activate charts.
 */
final class ChartDataFormatter
{
    private const PALETTE = [
        'rgba(0,119,168,0.82)',
        'rgba(124,58,237,0.82)',
        'rgba(5,150,105,0.82)',
        'rgba(196,106,0,0.82)',
        'rgba(220,38,38,0.82)',
        'rgba(6,182,212,0.82)',
        'rgba(245,158,11,0.82)',
        'rgba(16,185,129,0.82)',
    ];

    public function format(
        array  $rows,
        string $labelColumn,
        string $valueColumn,
        string $chartType = 'bar',
        ?string $label    = null,
    ): array {
        $labels = array_column($rows, $labelColumn);
        $values = array_column($rows, $valueColumn);
        $colors = $this->colors(count($values), $chartType);

        $dataset = [
            'label'           => $label ?? $valueColumn,
            'data'            => array_map('floatval', $values),
            'backgroundColor' => $chartType === 'line' ? $colors[0] : $colors,
            'borderColor'     => $chartType === 'line' ? $colors[0] : array_map(
                fn(string $c) => str_replace('0.82', '1', $c),
                $colors,
            ),
            'borderWidth'     => $chartType === 'line' ? 2 : 1,
            'fill'            => $chartType === 'area',
            'tension'         => 0.3,
        ];

        return [
            'type'    => $chartType === 'area' ? 'line' : $chartType,
            'data'    => ['labels' => $labels, 'datasets' => [$dataset]],
            'options' => [
                'responsive'          => true,
                'maintainAspectRatio' => false,
                'plugins'             => [
                    'legend'  => ['display' => $chartType === 'pie' || $chartType === 'doughnut'],
                    'tooltip' => ['mode' => 'index'],
                ],
                'scales' => in_array($chartType, ['pie', 'doughnut']) ? new \stdClass() : [
                    'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                ],
            ],
        ];
    }

    /** Pick the first meaningful aggregation columns as label+value pair. */
    public function autoDetectColumns(array $definition, array $firstRow): array
    {
        if (empty($firstRow)) {
            return ['label' => null, 'value' => null];
        }

        $columns    = array_keys($firstRow);
        $aggregations = $definition['aggregations'] ?? [];
        $groupBy      = $definition['group_by'] ?? $definition['groupBy'] ?? [];

        $valueColumn = null;
        foreach ($aggregations as $agg) {
            if (isset($agg['alias']) && in_array($agg['alias'], $columns, true)) {
                $valueColumn = $agg['alias'];
                break;
            }
        }

        $labelColumn = null;
        foreach ($groupBy as $field) {
            if (in_array($field, $columns, true)) {
                $labelColumn = $field;
                break;
            }
        }

        if ($labelColumn === null && isset($columns[0])) {
            $labelColumn = $columns[0];
        }
        if ($valueColumn === null && isset($columns[1])) {
            $valueColumn = $columns[1];
        }

        return ['label' => $labelColumn, 'value' => $valueColumn];
    }

    private function colors(int $count, string $type): array
    {
        if ($type === 'line') {
            return [self::PALETTE[0]];
        }
        $colors = [];
        for ($i = 0; $i < $count; $i++) {
            $colors[] = self::PALETTE[$i % count(self::PALETTE)];
        }
        return $colors;
    }
}
