<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Domain\Report\Events;

use Mostafax\ReportingEngine\Domain\Execution\ExecutionMetadata;

final readonly class ReportExecuted
{
    public \DateTimeImmutable $occurredAt;

    public function __construct(
        public string            $reportId,
        public ExecutionMetadata $metadata,
        public ?string           $tenantId,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }
}
