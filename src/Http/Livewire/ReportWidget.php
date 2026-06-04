<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Http\Livewire;

use Illuminate\Contracts\View\View;
use Mostafax\ReportingEngine\Application\Services\ExecutionService;
use Mostafax\ReportingEngine\Contracts\ReportRepositoryInterface;
use Mostafax\ReportingEngine\Support\ThemeDetector;

/**
 * Livewire version of ReportWidget — adds real-time filtering, sorting, and pagination.
 *
 * Requires: livewire/livewire (^2.0|^3.0)
 *
 * Usage:
 *   <livewire:report-widget report="report-id" />
 *   <livewire:report-widget report="report-id" :per-page="25" />
 */
class ReportWidget extends \Livewire\Component
{
    // ── Public reactive properties ────────────────────────────────

    public string $report   = '';
    public int    $page     = 1;
    public int    $perPage  = 10;
    public string $search   = '';
    public string $sortCol  = '';
    public string $sortDir  = 'asc';
    public array  $filters  = [];
    public ?string $theme   = null;
    public bool   $showExport  = false;
    public bool   $showFilters = true;

    // ── Lifecycle ─────────────────────────────────────────────────

    public function mount(
        string  $report,
        int     $perPage    = 10,
        bool    $showExport  = false,
        bool    $showFilters = true,
        ?string $theme       = null,
    ): void {
        $this->report      = $report;
        $this->perPage     = $perPage;
        $this->showExport  = $showExport;
        $this->showFilters = $showFilters;
        $this->theme       = $theme;
    }

    // ── Actions ───────────────────────────────────────────────────

    public function sort(string $column): void
    {
        if ($this->sortCol === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortCol = $column;
            $this->sortDir = 'asc';
        }
        $this->page = 1;
    }

    public function goToPage(int $page): void
    {
        $this->page = $page;
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function updatedFilters(): void
    {
        $this->page = 1;
    }

    public function resetFilters(): void
    {
        $this->filters = [];
        $this->search  = '';
        $this->page    = 1;
    }

    // ── Render ────────────────────────────────────────────────────

    public function render(): View
    {
        /** @var ReportRepositoryInterface $repository */
        $repository = app(ReportRepositoryInterface::class);
        /** @var ExecutionService $execution */
        $execution  = app(ExecutionService::class);

        $reportEntity = $repository->findByIdOrName($this->report);

        $overrides = [
            'pagination' => ['page' => $this->page, 'per_page' => $this->perPage],
        ];

        if (!empty($this->sortCol)) {
            $overrides['order_by'] = [['column' => $this->sortCol, 'direction' => $this->sortDir]];
        }

        if (!empty($this->filters)) {
            $conditions = [];
            foreach ($this->filters as $field => $value) {
                if ($value !== '' && $value !== null) {
                    $conditions[] = ['field' => $field, 'operator' => 'like', 'value' => "%{$value}%"];
                }
            }
            if (!empty($conditions)) {
                $overrides['filters'] = ['operator' => 'AND', 'conditions' => $conditions];
            }
        }

        $result = null;
        $error  = null;

        if ($reportEntity) {
            try {
                $result = $execution->runById(
                    reportId:   $reportEntity->id->value,
                    overrides:  $overrides,
                    userRoles:  $this->userRoles(),
                    executedBy: (string) auth()->id(),
                );
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $columns = (!empty($result?->data)) ? array_keys($result->data[0]) : [];

        return view('reporting-engine::livewire.report-widget', [
            'reportEntity'  => $reportEntity,
            'result'        => $result,
            'columns'       => $columns,
            'error'         => $error,
            'theme'         => ThemeDetector::resolve($this->theme),
            'isRtl'         => ThemeDetector::isRtl(),
            'resolvedTitle' => $reportEntity?->name ?? $this->report,
        ]);
    }

    private function userRoles(): array
    {
        if (!auth()->check()) return [];
        $user = auth()->user();
        if (method_exists($user, 'getRoleNames')) return $user->getRoleNames()->toArray();
        return [];
    }
}
