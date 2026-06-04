<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\Persistence\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Mostafax\ReportingEngine\Contracts\ReportRepositoryInterface;
use Mostafax\ReportingEngine\Domain\Report\Entities\Report;
use Mostafax\ReportingEngine\Domain\Report\ValueObjects\ReportId;
use Mostafax\ReportingEngine\Infrastructure\Persistence\Models\ReportModel;

final class EloquentReportRepository implements ReportRepositoryInterface
{
    public function findById(string $id): ?Report
    {
        $model = ReportModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(int $perPage = 15): LengthAwarePaginator
    {
        return ReportModel::latest()
            ->paginate($perPage)
            ->through(fn(ReportModel $m) => $this->toDomain($m));
    }

    public function findByTenant(string $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return ReportModel::forTenant($tenantId)
            ->latest()
            ->paginate($perPage)
            ->through(fn(ReportModel $m) => $this->toDomain($m));
    }

    public function save(Report $report): Report
    {
        $model = ReportModel::updateOrCreate(
            ['id' => (string) $report->id],
            [
                'name'        => $report->name,
                'description' => $report->description,
                'definition'  => $report->definition,
                'is_public'   => $report->isPublic,
                'tenant_id'   => $report->tenantId,
                'created_by'  => $report->createdBy,
                'tags'        => $report->tags,
                'is_cached'   => $report->isCached,
                'cache_ttl'   => $report->cacheTtl,
                'updated_at'  => $report->updatedAt,
                'created_at'  => $report->createdAt,
            ],
        );

        // Dispatch accumulated domain events via Laravel event bus
        foreach ($report->pullDomainEvents() as $event) {
            event($event);
        }

        return $this->toDomain($model->fresh());
    }

    public function delete(string $id): bool
    {
        return (bool) ReportModel::find($id)?->delete();
    }

    private function toDomain(ReportModel $model): Report
    {
        return Report::reconstitute(
            id:          $model->id,
            name:        $model->name,
            description: $model->description ?? '',
            definition:  (array) $model->definition,
            isPublic:    (bool) $model->is_public,
            tenantId:    $model->tenant_id,
            createdBy:   $model->created_by,
            createdAt:   new \DateTimeImmutable($model->created_at->toIso8601String()),
            updatedAt:   new \DateTimeImmutable($model->updated_at->toIso8601String()),
            tags:        (array) ($model->tags ?? []),
            isCached:    (bool) $model->is_cached,
            cacheTtl:    (int) $model->cache_ttl,
        );
    }
}
