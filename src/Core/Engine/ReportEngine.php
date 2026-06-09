<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\Engine;

use Illuminate\Contracts\Events\Dispatcher;
use Mostafax\ReportingEngine\Application\Services\RowLevelSecurityService;
use Mostafax\ReportingEngine\Contracts\CacheManagerInterface;
use Mostafax\ReportingEngine\Core\DSL\DslParser;
use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;
use Mostafax\ReportingEngine\Core\Validation\DslValidationException;
use Mostafax\ReportingEngine\Core\Validation\QueryValidator;
use Mostafax\ReportingEngine\Domain\Execution\ExecutionResult;
use Mostafax\ReportingEngine\Domain\Report\Events\ReportExecuted;
use Mostafax\ReportingEngine\Infrastructure\DataSources\DataSourceResolver;
use Mostafax\ReportingEngine\Infrastructure\Security\FieldAccessControl;
use Mostafax\ReportingEngine\Contracts\ReportEngineInterface;
use Mostafax\ReportingEngine\Infrastructure\Security\QuerySanitizer;

/**
 * Central orchestrator for the Dynamic Hybrid Reporting Engine.
 *
 * Execution flow:
 *   1. Parse raw DSL  (DslParser)
 *   2. Validate DSL   (QueryValidator)
 *   3. Sanitize       (QuerySanitizer)   – identifier safety
 *   4. ACL            (FieldAccessControl) – field-level access
 *   5. Cache probe    (CacheManagerInterface)
 *   6. Resolve adapter & execute
 *   7. Cache store
 *   8. Dispatch event
 */
final class ReportEngine implements ReportEngineInterface
{
    public function __construct(
        private readonly DslParser                  $parser,
        private readonly QueryValidator             $validator,
        private readonly QuerySanitizer             $sanitizer,
        private readonly FieldAccessControl         $acl,
        private readonly DataSourceResolver         $resolver,
        private readonly CacheManagerInterface      $cache,
        private readonly Dispatcher                 $events,
        private readonly ?RowLevelSecurityService   $rls = null,
    ) {}

    /**
     * Execute a DSL definition supplied as a raw array or JSON string.
     *
     * @param  array|string            $rawDsl
     * @param  string[]                $userRoles   – active roles for ACL
     * @throws DslValidationException
     */
    public function run(array|string $rawDsl, array $userRoles = []): ExecutionResult
    {
        $definition = $this->prepare($rawDsl, $userRoles);
        return $this->execute($definition);
    }

    /**
     * Execute a pre-built QueryDefinition (used by async jobs).
     *
     * @throws DslValidationException
     */
    public function execute(QueryDefinition $definition): ExecutionResult
    {
        // 1. Cache probe
        $cached = $this->cache->get($definition);
        if ($cached !== null) {
            $this->dispatchEvent($definition, $cached);
            return $cached;
        }

        // 2. Resolve adapter
        $adapter = $this->resolver->resolve($definition->source);

        // 3. Execute — route to aggregate() or query() depending on DSL intent
        $result = $definition->isAggregation()
            ? $adapter->aggregate($definition)
            : $adapter->query($definition);

        // 4. Cache store
        $this->cache->put($definition, $result);

        // 5. Dispatch post-execution event for listeners (logging, audit, etc.)
        $this->dispatchEvent($definition, $result);

        return $result;
    }

    /**
     * Parse → validate → sanitize → ACL filter, return ready-to-execute definition.
     *
     * @param  string[] $userRoles
     * @throws DslValidationException
     */
    public function prepare(array|string $rawDsl, array $userRoles = []): QueryDefinition
    {
        $definition = $this->parser->parse($rawDsl);

        $this->validator->validate($definition);

        $definition = $this->sanitizer->sanitize($definition);

        if (!empty($userRoles)) {
            $definition = $this->acl->withRoles(...$userRoles)->apply($definition);
            // Step 4b: Row-level security — AND-merges role-based WHERE policies
            if ($this->rls !== null) {
                $definition = $this->rls->apply($definition, $userRoles);
            }
        }

        return $definition;
    }

    private function dispatchEvent(QueryDefinition $definition, ExecutionResult $result): void
    {
        $this->events->dispatch(new ReportExecuted(
            reportId: $definition->reportId ?? 'ad-hoc',
            metadata: $result->metadata,
            tenantId: $definition->tenantId,
        ));
    }
}
