<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Domain\Report\Exceptions;

use RuntimeException;

final class ReportNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct("Report [{$id}] not found.", 404);
    }
}
