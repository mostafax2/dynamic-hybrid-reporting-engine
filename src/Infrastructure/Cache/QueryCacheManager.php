<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Mostafax\ReportingEngine\Contracts\CacheManagerInterface;
use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;
use Mostafax\ReportingEngine\Domain\Execution\ExecutionResult;

final class QueryCacheManager implements CacheManagerInterface
{
    private bool   $enabled;
    private int    $defaultTtl;
    private string $prefix;

    public function __construct(
        private readonly CacheRepository $cache,
        array $config = [],
    ) {
        $this->enabled    = (bool)  ($config['enabled']  ?? true);
        $this->defaultTtl = (int)   ($config['ttl']      ?? 300);
        $this->prefix     = (string)($config['prefix']   ?? 'dhr_');
    }

    public function get(QueryDefinition $definition): ?ExecutionResult
    {
        if (!$this->enabled) {
            return null;
        }

        $cached = $this->cache->get($this->buildKey($definition));

        if ($cached instanceof ExecutionResult) {
            return $cached->withCacheHit();
        }

        return null;
    }

    public function put(QueryDefinition $definition, ExecutionResult $result): void
    {
        if (!$this->enabled) {
            return;
        }

        $ttl = $this->resolveTtl($definition);
        if ($ttl <= 0) {
            return;
        }

        $this->cache->put($this->buildKey($definition), $result, $ttl);
    }

    public function forget(QueryDefinition $definition): void
    {
        $this->cache->forget($this->buildKey($definition));
    }

    public function forgetByReportId(string $reportId): void
    {
        // Tag-based invalidation when using a taggable store (Redis)
        if (method_exists($this->cache->getStore(), 'tags')) {
            $this->cache->tags([$this->tagForReport($reportId)])->flush();
        }
    }

    public function buildKey(QueryDefinition $definition): string
    {
        $tenantPrefix = $definition->tenantId ? "t{$definition->tenantId}_" : '';
        return $this->prefix . $tenantPrefix . $definition->hash();
    }

    private function resolveTtl(QueryDefinition $definition): int
    {
        if (!empty($definition->options['cache_ttl'])) {
            return (int) $definition->options['cache_ttl'];
        }
        return $this->defaultTtl;
    }

    private function tagForReport(string $reportId): string
    {
        return $this->prefix . 'report_' . $reportId;
    }
}
