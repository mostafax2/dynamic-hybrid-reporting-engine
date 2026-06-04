<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Mostafax\ReportingEngine\Application\DTO\ExecutionResultDTO;

/**
 * @mixin ExecutionResultDTO
 */
final class ExecutionResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ExecutionResultDTO $dto */
        $dto = $this->resource;

        return [
            'data' => $dto->data,
            'meta' => [
                'pagination' => [
                    'total'     => $dto->total,
                    'page'      => $dto->page,
                    'per_page'  => $dto->perPage,
                    'last_page' => $dto->lastPage,
                ],
                'execution'  => $dto->metadata,
            ],
        ];
    }
}
