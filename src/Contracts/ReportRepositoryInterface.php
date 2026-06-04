<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Mostafax\ReportingEngine\Domain\Report\Entities\Report;

interface ReportRepositoryInterface
{
    public function findById(string $id): ?Report;

    /** @return LengthAwarePaginator<Report> */
    public function findAll(int $perPage = 15): LengthAwarePaginator;

    /** @return LengthAwarePaginator<Report> */
    public function findByTenant(string $tenantId, int $perPage = 15): LengthAwarePaginator;

    public function save(Report $report): Report;

    public function delete(string $id): bool;
}
