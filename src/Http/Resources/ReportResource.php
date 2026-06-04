<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Mostafax\ReportingEngine\Application\DTO\ReportDTO;

/**
 * @mixin ReportDTO
 */
final class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ReportDTO $dto */
        $dto = $this->resource;

        return [
            'id'          => $dto->id,
            'name'        => $dto->name,
            'description' => $dto->description,
            'definition'  => $dto->definition,
            'is_public'   => $dto->isPublic,
            'tags'        => $dto->tags,
            'is_cached'   => $dto->isCached,
            'cache_ttl'   => $dto->cacheTtl,
            'created_by'  => $dto->createdBy,
            'created_at'  => $dto->createdAt,
            'updated_at'  => $dto->updatedAt,
        ];
    }
}
