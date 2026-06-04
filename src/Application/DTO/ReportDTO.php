<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Application\DTO;

use Mostafax\ReportingEngine\Domain\Report\Entities\Report;

final readonly class ReportDTO
{
    public function __construct(
        public string  $id,
        public string  $name,
        public string  $description,
        public array   $definition,
        public bool    $isPublic,
        public ?string $tenantId,
        public ?string $createdBy,
        public string  $createdAt,
        public string  $updatedAt,
        public array   $tags,
        public bool    $isCached,
        public int     $cacheTtl,
    ) {}

    public static function fromDomain(Report $report): self
    {
        return new self(
            id:          (string) $report->id,
            name:        $report->name,
            description: $report->description,
            definition:  $report->definition,
            isPublic:    $report->isPublic,
            tenantId:    $report->tenantId,
            createdBy:   $report->createdBy,
            createdAt:   $report->createdAt->format(\DateTimeInterface::ATOM),
            updatedAt:   $report->updatedAt->format(\DateTimeInterface::ATOM),
            tags:        $report->tags,
            isCached:    $report->isCached,
            cacheTtl:    $report->cacheTtl,
        );
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'definition'  => $this->definition,
            'is_public'   => $this->isPublic,
            'tenant_id'   => $this->tenantId,
            'created_by'  => $this->createdBy,
            'tags'        => $this->tags,
            'is_cached'   => $this->isCached,
            'cache_ttl'   => $this->cacheTtl,
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
        ];
    }
}
