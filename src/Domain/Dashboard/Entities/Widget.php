<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Domain\Dashboard\Entities;

use Mostafax\ReportingEngine\Domain\Dashboard\ValueObjects\WidgetType;

final class Widget
{
    public function __construct(
        public readonly string     $id,
        public string              $title,
        public WidgetType          $type,
        public string              $reportId,
        public array               $config,
        public int                 $positionX,
        public int                 $positionY,
        public int                 $width,
        public int                 $height,
        public \DateTimeImmutable  $createdAt,
    ) {}

    public static function create(
        string    $title,
        WidgetType $type,
        string    $reportId,
        array     $config    = [],
        int       $positionX = 0,
        int       $positionY = 0,
        int       $width     = 6,
        int       $height    = 4,
    ): self {
        return new self(
            id:        self::generateId(),
            title:     $title,
            type:      $type,
            reportId:  $reportId,
            config:    $config,
            positionX: $positionX,
            positionY: $positionY,
            width:     $width,
            height:    $height,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function resize(int $width, int $height): void
    {
        $this->width  = max(1, $width);
        $this->height = max(1, $height);
    }

    public function move(int $x, int $y): void
    {
        $this->positionX = max(0, $x);
        $this->positionY = max(0, $y);
    }

    private static function generateId(): string
    {
        return 'wgt_' . bin2hex(random_bytes(8));
    }
}
