<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Mostafax\ReportingEngine\Application\Services\ExecutionService;
use Mostafax\ReportingEngine\Application\Services\ExportService;
use Mostafax\ReportingEngine\Application\Services\ReportService;
use Mostafax\ReportingEngine\Contracts\CacheManagerInterface;
use Mostafax\ReportingEngine\Contracts\ReportRepositoryInterface;
use Mostafax\ReportingEngine\Core\DSL\DslParser;
use Mostafax\ReportingEngine\Core\Engine\ReportEngine;
use Mostafax\ReportingEngine\Core\Validation\QueryValidator;
use Mostafax\ReportingEngine\Exporters\CsvExporter;
use Mostafax\ReportingEngine\Exporters\ExcelExporter;
use Mostafax\ReportingEngine\Exporters\ExporterFactory;
use Mostafax\ReportingEngine\Exporters\JsonExporter;
use Mostafax\ReportingEngine\Infrastructure\Builders\MongoAggregationBuilder;
use Mostafax\ReportingEngine\Infrastructure\Builders\MySQLQueryBuilder;
use Mostafax\ReportingEngine\Infrastructure\Cache\QueryCacheManager;
use Mostafax\ReportingEngine\Infrastructure\DataSources\DataSourceResolver;
use Mostafax\ReportingEngine\Infrastructure\DataSources\MongoDataSource;
use Mostafax\ReportingEngine\Infrastructure\DataSources\MySQLDataSource;
use Mostafax\ReportingEngine\Infrastructure\Persistence\Repositories\EloquentReportRepository;
use Mostafax\ReportingEngine\Infrastructure\Security\FieldAccessControl;
use Mostafax\ReportingEngine\Infrastructure\Security\QuerySanitizer;

final class ReportingEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/reporting-engine.php',
            'reporting-engine',
        );

        $this->registerCore();
        $this->registerInfrastructure();
        $this->registerApplication();
        $this->registerExporters();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishConfig();
            $this->publishMigrations();
        }

        $this->registerRoutes();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    // ── Registration ─────────────────────────────────────────────

    private function registerCore(): void
    {
        $this->app->singleton(DslParser::class);
        $this->app->singleton(QuerySanitizer::class);

        $this->app->singleton(QueryValidator::class, function ($app) {
            return new QueryValidator(
                config: config('reporting-engine', []),
            );
        });

        $this->app->singleton(FieldAccessControl::class, function ($app) {
            return new FieldAccessControl(
                config: config('reporting-engine.field_acl', []),
            );
        });

        $this->app->singleton(ReportEngine::class, function ($app) {
            return new ReportEngine(
                parser:    $app->make(DslParser::class),
                validator: $app->make(QueryValidator::class),
                sanitizer: $app->make(QuerySanitizer::class),
                acl:       $app->make(FieldAccessControl::class),
                resolver:  $app->make(DataSourceResolver::class),
                cache:     $app->make(CacheManagerInterface::class),
                events:    $app->make(Dispatcher::class),
            );
        });
    }

    private function registerInfrastructure(): void
    {
        // Data source builders
        $this->app->singleton(MySQLQueryBuilder::class);
        $this->app->singleton(MongoAggregationBuilder::class);

        // Data source adapters
        $this->app->singleton(MySQLDataSource::class, function ($app) {
            return new MySQLDataSource($app->make(MySQLQueryBuilder::class));
        });

        $this->app->singleton(MongoDataSource::class, function ($app) {
            return new MongoDataSource($app->make(MongoAggregationBuilder::class));
        });

        // Resolver
        $this->app->singleton(DataSourceResolver::class, function ($app) {
            return new DataSourceResolver(
                container: $app,
                bindings:  config('reporting-engine.adapters', []),
            );
        });

        // Cache manager
        $this->app->singleton(CacheManagerInterface::class, function ($app) {
            $cacheConfig = config('reporting-engine.cache', []);
            $driver      = $cacheConfig['driver'] ?? 'redis';

            $store = $app->make('cache')->store($driver);

            return new QueryCacheManager(
                cache:  $store,
                config: $cacheConfig,
            );
        });

        // Repository
        $this->app->singleton(ReportRepositoryInterface::class, EloquentReportRepository::class);
    }

    private function registerApplication(): void
    {
        $this->app->singleton(ReportService::class, function ($app) {
            return new ReportService(
                repository: $app->make(ReportRepositoryInterface::class),
                parser:     $app->make(DslParser::class),
                validator:  $app->make(QueryValidator::class),
                cache:      $app->make(CacheManagerInterface::class),
            );
        });

        $this->app->singleton(ExecutionService::class, function ($app) {
            return new ExecutionService(
                engine:     $app->make(ReportEngine::class),
                repository: $app->make(ReportRepositoryInterface::class),
            );
        });

        $this->app->singleton(ExportService::class, function ($app) {
            return new ExportService(
                engine:          $app->make(ReportEngine::class),
                repository:      $app->make(ReportRepositoryInterface::class),
                exporterFactory: $app->make(ExporterFactory::class),
            );
        });
    }

    private function registerExporters(): void
    {
        $this->app->singleton(CsvExporter::class);
        $this->app->singleton(JsonExporter::class);
        $this->app->singleton(ExcelExporter::class);

        $this->app->singleton(ExporterFactory::class, function ($app) {
            return new ExporterFactory(
                csv:   $app->make(CsvExporter::class),
                json:  $app->make(JsonExporter::class),
                excel: $app->make(ExcelExporter::class),
            );
        });
    }

    // ── Boot helpers ─────────────────────────────────────────────

    private function registerRoutes(): void
    {
        if (!config('reporting-engine.routes.enabled', true)) {
            return;
        }

        $this->app['router']->group([
            'prefix'     => config('reporting-engine.routes.prefix', 'api/reporting'),
            'middleware' => config('reporting-engine.routes.middleware', ['api']),
            'as'         => 'reporting.',
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        });
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/reporting-engine.php' => config_path('reporting-engine.php'),
        ], 'reporting-engine-config');
    }

    private function publishMigrations(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'reporting-engine-migrations');
    }
}
