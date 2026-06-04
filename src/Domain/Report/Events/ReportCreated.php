<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Domain\Report\Events;

use Mostafax\ReportingEngine\Domain\Report\ValueObjects\ReportId;

final readonly class ReportCreated
{
    public \DateTimeImmutable $occurredAt;

    public function __construct(
        public ReportId $reportId,
        public ?string  $tenantId,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }
}
