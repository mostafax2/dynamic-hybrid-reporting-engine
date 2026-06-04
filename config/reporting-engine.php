<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Data Source Connection
    |--------------------------------------------------------------------------
    | The default database connection used when no explicit connection is given
    | in a report DSL. Map source type keys to Laravel connection names.
    */
    'connections' => [
        'mysql'   => env('DB_CONNECTION', 'mysql'),
        'mongodb' => env('MONGODB_CONNECTION', 'mongodb'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Execution Limits
    |--------------------------------------------------------------------------
    | Hard limits applied to every query before execution. These prevent
    | runaway queries from exhausting database or application resources.
    */
    'limits' => [
        'max_rows'              => (int) env('REPORTING_MAX_ROWS', 10_000),
        'max_per_page'          => (int) env('REPORTING_MAX_PER_PAGE', 500),
        'default_per_page'      => (int) env('REPORTING_DEFAULT_PER_PAGE', 25),
        'max_execution_seconds' => (int) env('REPORTING_MAX_EXEC_SECONDS', 30),
        'max_joins'             => (int) env('REPORTING_MAX_JOINS', 5),
        'max_conditions'        => (int) env('REPORTING_MAX_CONDITIONS', 20),
        'max_aggregations'      => (int) env('REPORTING_MAX_AGGREGATIONS', 10),
        'max_group_by_fields'   => (int) env('REPORTING_MAX_GROUP_BY', 5),
        'max_order_by_fields'   => (int) env('REPORTING_MAX_ORDER_BY', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Result Cache
    |--------------------------------------------------------------------------
    | Caching is strongly recommended for production. Set ttl to 0 to disable.
    | The cache key prefix is combined with the DSL hash for isolation between
    | tenants and report definitions.
    */
    'cache' => [
        'enabled' => (bool) env('REPORTING_CACHE_ENABLED', true),
        'driver'  => env('REPORTING_CACHE_DRIVER', 'redis'),
        'ttl'     => (int) env('REPORTING_CACHE_TTL', 300),
        'prefix'  => env('REPORTING_CACHE_PREFIX', 'dhr_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    | When enabled, the engine appends a tenant filter to every query using
    | the configured column name and the value resolved from the request.
    | The resolver callable receives the current Request and returns the
    | tenant identifier string, or null to skip tenant isolation.
    */
    'multi_tenancy' => [
        'enabled'        => (bool) env('REPORTING_MULTI_TENANT', false),
        'tenant_column'  => env('REPORTING_TENANT_COLUMN', 'tenant_id'),
        'resolver'       => null, // callable|string — set in AppServiceProvider
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Access Control
    |--------------------------------------------------------------------------
    | A map of guard name → list of field patterns that are globally denied.
    | Supports wildcard prefix/suffix (e.g. "*_secret", "password*").
    | The engine strips denied fields from every query before execution.
    */
    'field_acl' => [
        'always_deny' => [
            'password',
            'password_hash',
            'remember_token',
            'secret',
            'api_key',
            'api_secret',
            'access_token',
            'refresh_token',
        ],
        'role_deny'   => [
            // 'analyst' => ['ssn', 'credit_card'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Operators
    |--------------------------------------------------------------------------
    | Whitelist of filter operators accepted by the DSL parser. Any operator
    | not in this list is rejected during validation.
    */
    'allowed_operators' => [
        '=', '!=', '<>', '>', '>=', '<', '<=',
        'like', 'not_like',
        'in', 'nin', 'not_in',
        'between',
        'null', 'not_null',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Aggregation Functions
    |--------------------------------------------------------------------------
    */
    'allowed_aggregations' => [
        'sum', 'count', 'avg', 'min', 'max',
        'count_distinct', 'group_concat',
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Settings
    |--------------------------------------------------------------------------
    */
    'export' => [
        'disk'           => env('REPORTING_EXPORT_DISK', 'local'),
        'path'           => env('REPORTING_EXPORT_PATH', 'reports/exports'),
        'chunk_size'     => (int) env('REPORTING_EXPORT_CHUNK', 1_000),
        'max_export_rows'=> (int) env('REPORTING_MAX_EXPORT_ROWS', 100_000),
        'formats'        => ['json', 'csv', 'xlsx'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Async Execution (Laravel Queues)
    |--------------------------------------------------------------------------
    | Long-running reports can be dispatched to a queue. The execution_model
    | stores status and result reference for polling.
    */
    'async' => [
        'enabled'    => (bool) env('REPORTING_ASYNC_ENABLED', false),
        'queue'      => env('REPORTING_QUEUE', 'reports'),
        'connection' => env('REPORTING_QUEUE_CONNECTION', null),
        'timeout'    => (int) env('REPORTING_JOB_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Blade / UI Integration
    |--------------------------------------------------------------------------
    | theme         : 'bootstrap' | 'tailwind' | 'none' | 'auto'
    |                 'auto' detects Tailwind via tailwind.config.js presence.
    | chart_library : 'chartjs' (default) — include Chart.js in your layout;
    |                 the widget canvas activates when it is detected.
    | rtl_locales   : locales that require RTL text direction.
    | dark_mode     : 'auto' | 'light' | 'dark'
    */
    'blade' => [
        'theme'         => env('REPORTING_BLADE_THEME', 'auto'),
        'chart_library' => env('REPORTING_CHART_LIBRARY', 'chartjs'),
        'rtl_locales'   => ['ar', 'fa', 'he', 'ur'],
        'dark_mode'     => env('REPORTING_DARK_MODE', 'auto'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'enabled'      => (bool) env('REPORTING_RATE_LIMIT', true),
        'max_attempts' => (int) env('REPORTING_RATE_MAX', 60),
        'decay_seconds'=> (int) env('REPORTING_RATE_DECAY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registered Data Source Adapters
    |--------------------------------------------------------------------------
    | Add custom adapters here. Key is the DSL `source` string, value is the
    | fully-qualified class implementing DataSourceInterface.
    */
    'adapters' => [
        'mysql'   => \Mostafax\ReportingEngine\Infrastructure\DataSources\MySQLDataSource::class,
        'mongodb' => \Mostafax\ReportingEngine\Infrastructure\DataSources\MongoDataSource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled'    => true,
        'prefix'     => env('REPORTING_ROUTE_PREFIX', 'api/reporting'),
        'middleware' => ['api', 'auth:sanctum'],
    ],

];
