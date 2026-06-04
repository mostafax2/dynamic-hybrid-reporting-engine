<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Domain\Report\Entities;

use Mostafax\ReportingEngine\Domain\Report\Events\ReportCreated;
use Mostafax\ReportingEngine\Domain\Report\ValueObjects\ReportId;

/**
 * Aggregate root — represents a saved report definition.
 *
 * Domain events are accumulated here and flushed by the repository or
 * application service after persistence.
 */
final class Report
{
    /** @var object[] */
    private array $domainEvents = [];

    private function __construct(
        public readonly ReportId $id,
        public string            $name,
        public string            $description,
        public array             $definition,
        public bool              $isPublic,
        public ?string           $tenantId,
        public ?string           $createdBy,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public array             $tags     = [],
        public bool              $isCached = true,
        public int               $cacheTtl = 300,
    ) {}

    public static function create(
        string  $name,
        string  $description,
        array   $definition,
        bool    $isPublic   = false,
        ?string $tenantId   = null,
        ?string $createdBy  = null,
        array   $tags       = [],
        bool    $isCached   = true,
        int     $cacheTtl   = 300,
    ): self {
        $now    = new \DateTimeImmutable();
        $report = new self(
            id:          ReportId::generate(),
            name:        $name,
            description: $description,
            definition:  $definition,
            isPublic:    $isPublic,
            tenantId:    $tenantId,
            createdBy:   $createdBy,
            createdAt:   $now,
            updatedAt:   $now,
            tags:        $tags,
            isCached:    $isCached,
            cacheTtl:    $cacheTtl,
        );

        $report->record(new ReportCreated($report->id, $tenantId));

        return $report;
    }

    public static function reconstitute(
        string             $id,
        string             $name,
        string             $description,
        array              $definition,
        bool               $isPublic,
        ?string            $tenantId,
        ?string            $createdBy,
        \DateTimeImmutable  $createdAt,
        \DateTimeImmutable  $updatedAt,
        array              $tags     = [],
        bool               $isCached = true,
        int                $cacheTtl = 300,
    ): self {
        return new self(
            id:          ReportId::from($id),
            name:        $name,
            description: $description,
            definition:  $definition,
            isPublic:    $isPublic,
            tenantId:    $tenantId,
            createdBy:   $createdBy,
            createdAt:   $createdAt,
            updatedAt:   $updatedAt,
            tags:        $tags,
            isCached:    $isCached,
            cacheTtl:    $cacheTtl,
        );
    }

    public function update(string $name, string $description, array $definition, array $tags = []): void
    {
        $this->name        = $name;
        $this->description = $description;
        $this->definition  = $definition;
        $this->tags        = $tags;
        $this->updatedAt   = new \DateTimeImmutable();
    }

    public function makePublic(): void
    {
        $this->isPublic  = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function makePrivate(): void
    {
        $this->isPublic  = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setCacheTtl(int $ttl): void
    {
        $this->cacheTtl  = max(0, $ttl);
        $this->isCached  = $ttl > 0;
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function record(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    /** @return object[] */
    public function pullDomainEvents(): array
    {
        $events             = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}
