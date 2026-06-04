<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Domain\Dashboard\Entities;

final class Dashboard
{
    /** @var Widget[] */
    private array $widgets = [];

    public function __construct(
        public readonly string    $id,
        public string             $name,
        public string             $description,
        public ?string            $tenantId,
        public ?string            $createdBy,
        public bool               $isPublic,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        string  $name,
        string  $description = '',
        ?string $tenantId    = null,
        ?string $createdBy   = null,
        bool    $isPublic    = false,
    ): self {
        $now = new \DateTimeImmutable();
        return new self(
            id:          'dsh_' . bin2hex(random_bytes(8)),
            name:        $name,
            description: $description,
            tenantId:    $tenantId,
            createdBy:   $createdBy,
            isPublic:    $isPublic,
            createdAt:   $now,
            updatedAt:   $now,
        );
    }

    public function addWidget(Widget $widget): void
    {
        $this->widgets[$widget->id] = $widget;
        $this->touch();
    }

    public function removeWidget(string $widgetId): void
    {
        unset($this->widgets[$widgetId]);
        $this->touch();
    }

    /** @return Widget[] */
    public function widgets(): array
    {
        return array_values($this->widgets);
    }

    public function widgetCount(): int
    {
        return count($this->widgets);
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
