<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Mostafax\ReportingEngine\Support\ThemeDetector;

/**
 * Renders export download buttons (CSV, XLSX, JSON) for a saved report.
 *
 * Generates links to GET /api/reporting/{id}/export?format=*.
 * The API prefix is taken from config('reporting-engine.routes.prefix').
 *
 * Usage:
 *   <x-report-export report="report-id-or-name" />
 *   <x-report-export report="sales" :formats="['csv', 'xlsx']" />
 */
final class ReportExport extends Component
{
    public function __construct(
        private \Mostafax\ReportingEngine\Contracts\ReportRepositoryInterface $repository,
        public readonly string  $report,
        public readonly ?string $theme   = null,
        /** @var string[] */
        public readonly array   $formats = ['csv', 'xlsx', 'json'],
        public readonly string  $size    = 'sm',   // sm | md
    ) {}

    public function render(): View|\Closure
    {
        $reportEntity = $this->repository->findByIdOrName($this->report);
        $prefix       = rtrim(config('reporting-engine.routes.prefix', 'api/reporting'), '/');

        $links = [];
        foreach ($this->formats as $format) {
            $links[$format] = "/{$prefix}/{$reportEntity?->id}/export?format={$format}";
        }

        $icons = [
            'csv'  => '📄',
            'xlsx' => '📊',
            'json' => '📋',
            'pdf'  => '📑',
        ];

        return view('reporting-engine::components.report-export', [
            'report'   => $reportEntity,
            'links'    => $links,
            'icons'    => $icons,
            'size'     => $this->size,
            'theme'    => ThemeDetector::resolve($this->theme),
            'isRtl'    => ThemeDetector::isRtl(),
        ]);
    }
}
