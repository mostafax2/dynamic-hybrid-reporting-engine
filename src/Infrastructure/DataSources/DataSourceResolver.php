<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\DataSources;

use Illuminate\Contracts\Container\Container;
use Mostafax\ReportingEngine\Contracts\DataSourceInterface;
use Mostafax\ReportingEngine\Core\Validation\DslValidationException;

/**
 * Resolves the correct DataSourceInterface implementation for a given source type.
 *
 * Adapters are registered in config/reporting-engine.php under `adapters`.
 * Custom adapters can be pushed at runtime via register().
 */
final class DataSourceResolver
{
    /** @var array<string, DataSourceInterface> resolved instances */
    private array $resolved = [];

    /** @var array<string, class-string<DataSourceInterface>> type → FQCN */
    private array $bindings;

    public function __construct(
        private readonly Container $container,
        array $bindings = [],
    ) {
        $this->bindings = $bindings ?: (array) config('reporting-engine.adapters', []);
    }

    /** @throws DslValidationException */
    public function resolve(string $sourceType): DataSourceInterface
    {
        if (isset($this->resolved[$sourceType])) {
            return $this->resolved[$sourceType];
        }

        if (!isset($this->bindings[$sourceType])) {
            throw new DslValidationException(
                "No data source adapter registered for type '{$sourceType}'. "
                . "Registered types: " . implode(', ', array_keys($this->bindings))
            );
        }

        $adapter = $this->container->make($this->bindings[$sourceType]);

        if (!($adapter instanceof DataSourceInterface)) {
            throw new \RuntimeException(
                "Adapter for '{$sourceType}' must implement DataSourceInterface"
            );
        }

        $this->resolved[$sourceType] = $adapter;

        return $adapter;
    }

    public function register(string $sourceType, DataSourceInterface $adapter): void
    {
        $this->bindings[$sourceType] = get_class($adapter);
        $this->resolved[$sourceType] = $adapter;
    }

    /** @return string[] */
    public function supportedTypes(): array
    {
        return array_keys($this->bindings);
    }
}
