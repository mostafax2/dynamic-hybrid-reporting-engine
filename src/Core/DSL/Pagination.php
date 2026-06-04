<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\DSL;

final readonly class Pagination
{
    public int $offset;

    public function __construct(
        public int $page    = 1,
        public int $perPage = 25,
    ) {
        if ($this->page < 1) {
            throw new \InvalidArgumentException('Pagination page must be >= 1');
        }
        if ($this->perPage < 1) {
            throw new \InvalidArgumentException('Pagination perPage must be >= 1');
        }
        $this->offset = ($this->page - 1) * $this->perPage;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            page:    (int) ($data['page'] ?? 1),
            perPage: (int) ($data['per_page'] ?? $data['perPage'] ?? 25),
        );
    }
}
