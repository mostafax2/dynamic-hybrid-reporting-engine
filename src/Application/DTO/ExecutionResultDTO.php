<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Application\DTO;

use Mostafax\ReportingEngine\Domain\Execution\ExecutionResult;

final readonly class ExecutionResultDTO
{
    public function __construct(
        public array $data,
        public int   $total,
        public int   $page,
        public int   $perPage,
        public int   $lastPage,
        public array $metadata,
    ) {}

    public static function fromResult(ExecutionResult $result, int $page, int $perPage): self
    {
        $lastPage = $perPage > 0 ? (int) ceil($result->total / $perPage) : 1;

        return new self(
            data:     $result->data,
            total:    $result->total,
            page:     $page,
            perPage:  $perPage,
            lastPage: max(1, $lastPage),
            metadata: $result->metadata->toArray(),
        );
    }

    public function toArray(): array
    {
        return [
            'data'     => $this->data,
            'meta'     => [
                'total'      => $this->total,
                'page'       => $this->page,
                'per_page'   => $this->perPage,
                'last_page'  => $this->lastPage,
                'execution'  => $this->metadata,
            ],
        ];
    }
}
