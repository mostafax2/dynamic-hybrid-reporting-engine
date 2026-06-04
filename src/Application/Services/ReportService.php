<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Application\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Mostafax\ReportingEngine\Application\DTO\CreateReportDTO;
use Mostafax\ReportingEngine\Application\DTO\ReportDTO;
use Mostafax\ReportingEngine\Contracts\CacheManagerInterface;
use Mostafax\ReportingEngine\Contracts\ReportRepositoryInterface;
use Mostafax\ReportingEngine\Core\DSL\DslParser;
use Mostafax\ReportingEngine\Core\Validation\QueryValidator;
use Mostafax\ReportingEngine\Domain\Report\Entities\Report;
use Mostafax\ReportingEngine\Domain\Report\Exceptions\ReportNotFoundException;

final class ReportService
{
    public function __construct(
        private readonly ReportRepositoryInterface $repository,
        private readonly DslParser                $parser,
        private readonly QueryValidator           $validator,
        private readonly CacheManagerInterface    $cache,
    ) {}

    public function create(CreateReportDTO $dto): ReportDTO
    {
        // Validate the embedded DSL definition before persisting
        $definition = $this->parser->parse($dto->definition);
        $this->validator->validate($definition);

        $report = Report::create(
            name:        $dto->name,
            description: $dto->description,
            definition:  $dto->definition,
            isPublic:    $dto->isPublic,
            tenantId:    $dto->tenantId,
            createdBy:   $dto->createdBy,
            tags:        $dto->tags,
            isCached:    $dto->isCached,
            cacheTtl:    $dto->cacheTtl,
        );

        return ReportDTO::fromDomain($this->repository->save($report));
    }

    public function update(string $id, CreateReportDTO $dto): ReportDTO
    {
        $report = $this->findOrFail($id);

        // Validate new definition before persisting
        $definition = $this->parser->parse($dto->definition);
        $this->validator->validate($definition);

        $report->update($dto->name, $dto->description, $dto->definition, $dto->tags);
        $report->setCacheTtl($dto->isCached ? $dto->cacheTtl : 0);

        // Invalidate cached results for this report
        $this->cache->forgetByReportId($id);

        return ReportDTO::fromDomain($this->repository->save($report));
    }

    public function findById(string $id): ReportDTO
    {
        return ReportDTO::fromDomain($this->findOrFail($id));
    }

    /** @return LengthAwarePaginator<ReportDTO> */
    public function paginate(int $perPage = 15, ?string $tenantId = null): LengthAwarePaginator
    {
        if ($tenantId !== null) {
            return $this->repository->findByTenant($tenantId, $perPage)
                ->through(fn(Report $r) => ReportDTO::fromDomain($r));
        }

        return $this->repository->findAll($perPage)
            ->through(fn(Report $r) => ReportDTO::fromDomain($r));
    }

    public function delete(string $id): void
    {
        $this->findOrFail($id);
        $this->repository->delete($id);
        $this->cache->forgetByReportId($id);
    }

    private function findOrFail(string $id): Report
    {
        $report = $this->repository->findById($id);

        if ($report === null) {
            throw new ReportNotFoundException($id);
        }

        return $report;
    }
}
