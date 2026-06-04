<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\View\Components;

use Illuminate\View\Component;
use Mostafax\ReportingEngine\Application\DTO\ExecutionResultDTO;
use Mostafax\ReportingEngine\Application\Services\ExecutionService;
use Mostafax\ReportingEngine\Contracts\ReportRepositoryInterface;
use Mostafax\ReportingEngine\Domain\Report\Entities\Report;
use Mostafax\ReportingEngine\Support\ThemeDetector;

abstract class BaseReportComponent extends Component
{
    public function __construct(
        protected readonly ExecutionService           $execution,
        protected readonly ReportRepositoryInterface  $repository,
        public readonly string                        $report,
        public readonly ?string                       $title    = null,
        public readonly ?string                       $theme    = null,
        public readonly int                           $perPage  = 10,
        public readonly int                           $page     = 1,
        public readonly bool                          $showTitle = true,
    ) {}

    // ── Internal helpers ──────────────────────────────────────────

    protected function resolveReport(): ?Report
    {
        return $this->repository->findByIdOrName($this->report);
    }

    protected function runReport(array $overrides = []): ?ExecutionResultDTO
    {
        try {
            $page = max(1, request()->integer('dhr_page', $this->page));

            $overrides = array_replace_recursive(
                ['pagination' => ['page' => $page, 'per_page' => $this->perPage]],
                $overrides,
            );

            return $this->execution->runById(
                reportId:   $this->report,
                overrides:  $overrides,
                userRoles:  $this->userRoles(),
                executedBy: (string) auth()->id(),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolvedTheme(): string
    {
        return ThemeDetector::resolve($this->theme);
    }

    protected function isRtl(): bool
    {
        return ThemeDetector::isRtl();
    }

    protected function userRoles(): array
    {
        if (!auth()->check()) {
            return [];
        }

        $user = auth()->user();

        // Spatie laravel-permission
        if (method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()->toArray();
        }

        // Generic roles relationship
        if (isset($user->roles) && $user->roles instanceof \Illuminate\Database\Eloquent\Collection) {
            return $user->roles->pluck('name')->toArray();
        }

        return [];
    }

    protected function widgetId(): string
    {
        return 'dhr_' . substr(md5($this->report . static::class), 0, 8);
    }
}
