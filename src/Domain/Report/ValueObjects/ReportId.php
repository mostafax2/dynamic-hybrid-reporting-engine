<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Domain\Report\ValueObjects;

use Ramsey\Uuid\Uuid;

final readonly class ReportId
{
    private function __construct(public readonly string $value) {}

    public static function generate(): self
    {
        return new self(class_exists(Uuid::class) ? Uuid::uuid4()->toString() : self::fallbackUuid());
    }

    public static function from(string $value): self
    {
        if (empty(trim($value))) {
            throw new \InvalidArgumentException('ReportId cannot be empty');
        }
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function fallbackUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        );
    }
}
