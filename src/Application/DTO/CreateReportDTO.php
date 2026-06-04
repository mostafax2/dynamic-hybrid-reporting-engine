<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Application\DTO;

final readonly class CreateReportDTO
{
    public function __construct(
        public string  $name,
        public string  $description,
        public array   $definition,
        public bool    $isPublic   = false,
        public ?string $tenantId   = null,
        public ?string $createdBy  = null,
        public array   $tags       = [],
        public bool    $isCached   = true,
        public int     $cacheTtl   = 300,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:        (string) ($data['name'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            definition:  (array)  ($data['definition'] ?? []),
            isPublic:    (bool)   ($data['is_public'] ?? false),
            tenantId:    isset($data['tenant_id'])  ? (string) $data['tenant_id']  : null,
            createdBy:   isset($data['created_by']) ? (string) $data['created_by'] : null,
            tags:        (array)  ($data['tags'] ?? []),
            isCached:    (bool)   ($data['is_cached'] ?? true),
            cacheTtl:    (int)    ($data['cache_ttl'] ?? 300),
        );
    }
}
