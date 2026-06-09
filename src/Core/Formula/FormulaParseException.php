<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\Formula;

final class FormulaParseException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $position,
        public readonly string $expression,
    ) {
        parent::__construct($message);
    }
}
