<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\Validation;

use RuntimeException;

final class DslValidationException extends RuntimeException
{
    /** @param array<string,string[]> $errors */
    public function __construct(
        string $message,
        private readonly array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 422, $previous);
    }

    /** @return array<string,string[]> */
    public function errors(): array
    {
        return $this->errors;
    }
}
