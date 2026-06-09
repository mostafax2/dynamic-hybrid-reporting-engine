<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Mostafax\ReportingEngine\Application\Services\ExecutionService;
use Mostafax\ReportingEngine\Application\Services\ExportService;
use Mostafax\ReportingEngine\Application\Services\ReportService;
use Mostafax\ReportingEngine\Contracts\CacheManagerInterface;
use Mostafax\ReportingEngine\Contracts\ReportRepositoryInterface;
use Mostafax\ReportingEngine\Core\DSL\DslParser;
use Mostafax\ReportingEngine\Contracts\ReportEngineInterface;
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
use Mostafax\ReportingEngine\Support\ChartDataFormatter;
use Mostafax\ReportingEngine\Support\FilterFormBuilder;
use Mostafax\ReportingEngine\View\Components\ChartWidget;
use Mostafax\ReportingEngine\View\Components\Dashboard;
use Mostafax\ReportingEngine\View\Components\KpiWidget;
use Mostafax\ReportingEngine\View\Components\ReportExport;
use Mostafax\ReportingEngine\View\Components\ReportFilter;
use Mostafax\ReportingEngine\View\Components\ReportWidget;

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
        $this->registerBladeSupport();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishConfig();
            $this->publishMigrations();
            $this->publishViews();
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'reporting-engine');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->registerRoutes();
        $this->registerBladeComponents();
        $this->registerLivewireComponents();
    }

    // ── Registration ─────────────────────────────────────────────

    private function registerCore(): void
    {
        $this->app->singleton(DslParser::class);
        $this->app->singleton(QuerySanitizer::class);

        // $app unused here — config() resolves at call time from the container
        $this->app->singleton(QueryValidator::class, fn() => new QueryValidator(
            config: config('reporting-engine', []),
        ));

        $this->app->singleton(FieldAccessControl::class, fn() => new FieldAccessControl(
            config: config('reporting-engine.field_acl', []),
        ));

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

        $this->app->alias(ReportEngine::class, ReportEngineInterface::class);
    }

    private function registerInfrastructure(): void
    {
        $this->app->singleton(MySQLQueryBuilder::class);
        $this->app->singleton(MongoAggregationBuilder::class);

        $this->app->singleton(MySQLDataSource::class, function ($app) {
            return new MySQLDataSource($app->make(MySQLQueryBuilder::class));
        });

        $this->app->singleton(MongoDataSource::class, function ($app) {
            return new MongoDataSource($app->make(MongoAggregationBuilder::class));
        });

        $this->app->singleton(DataSourceResolver::class, function ($app) {
            return new DataSourceResolver(
                container: $app,
                bindings:  config('reporting-engine.adapters', []),
            );
        });

        $this->app->singleton(CacheManagerInterface::class, function ($app) {
            $cacheConfig = config('reporting-engine.cache', []);
            $driver      = $cacheConfig['driver'] ?? 'redis';

            return new QueryCacheManager(
                cache:  $app->make('cache')->store($driver),
                config: $cacheConfig,
            );
        });

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

    private function registerBladeSupport(): void
    {
        $this->app->singleton(ChartDataFormatter::class);
        $this->app->singleton(FilterFormBuilder::class);
    }

    // ── Boot helpers ─────────────────────────────────────────────

    private function registerRoutes(): void
    {
        if (!config('reporting-engine.routes.enabled', true)) {
            return;
        }

        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app->make('router');
        $router->group([
            'prefix'     => config('reporting-engine.routes.prefix', 'api/reporting'),
            'middleware' => config('reporting-engine.routes.middleware', ['api']),
            'as'         => 'reporting.',
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        });
    }

    private function registerBladeComponents(): void
    {
        Blade::componentNamespace('Mostafax\\ReportingEngine\\View\\Components', 'reporting-engine');

        Blade::component('reporting-engine::report-widget',  ReportWidget::class);
        Blade::component('reporting-engine::kpi-widget',     KpiWidget::class);
        Blade::component('reporting-engine::chart-widget',   ChartWidget::class);
        Blade::component('reporting-engine::dashboard',      Dashboard::class);
        Blade::component('reporting-engine::report-filter',  ReportFilter::class);
        Blade::component('reporting-engine::report-export',  ReportExport::class);
    }

    private function registerLivewireComponents(): void
    {
        if (!class_exists(\Livewire\Component::class)) {
            return;
        }

        \Livewire\Livewire::component(
            'report-widget',
            \Mostafax\ReportingEngine\Http\Livewire\ReportWidget::class,
        );
    }

    // ── Publish helpers ──────────────────────────────────────────

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

    private function publishViews(): void
    {
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/reporting-engine'),
        ], 'reporting-engine-views');
    }
}
