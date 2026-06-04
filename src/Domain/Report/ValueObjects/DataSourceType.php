<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Domain\Report\ValueObjects;

enum DataSourceType: string
{
    case MySQL   = 'mysql';
    case MongoDB = 'mongodb';

    public function label(): string
    {
        return match($this) {
            self::MySQL   => 'MySQL (Relational)',
            self::MongoDB => 'MongoDB (Document)',
        };
    }

    public function isRelational(): bool
    {
        return $this === self::MySQL;
    }

    public function isDocument(): bool
    {
        return $this === self::MongoDB;
    }
}
