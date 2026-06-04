# Dynamic Hybrid Reporting Engine

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-10%2F11%2F12%2F13-red)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

A Laravel package that lets you define any report as a **JSON DSL** and execute it against **MySQL or MongoDB** through a single unified API — with Redis caching, field-level ACL, multi-tenancy, CSV/XLSX/JSON exports, and native Blade components built in.

📖 **Full Documentation:** [mostafax2.github.io/dynamic-hybrid-reporting-engine](https://mostafax2.github.io/dynamic-hybrid-reporting-engine/)

---

## Installation

```bash
composer require mostafax/dynamic-hybrid-reporting-engine
composer require predis/predis           # required for Redis cache
```

```bash
php artisan vendor:publish --tag=reporting-engine-config
php artisan vendor:publish --tag=reporting-engine-migrations
php artisan migrate
```

Optional extras:

```bash
composer require phpoffice/phpspreadsheet   # XLSX export
composer require livewire/livewire          # real-time Blade widgets
```

---

## Quick Start

### 1. Run the demo seeder

```bash
php artisan db:seed --class=ReportingEngineDemoSeeder
```

Seeds **80 MySQL orders** + **120 MongoDB events** + **5 saved report definitions**.  
Then visit `/reporting-demo` to see all components live.

---

### 2. Blade Components

Embed reports directly in any Blade template — zero JavaScript required:

```blade
{{-- KPI cards with switchable visual theme --}}
<x-reporting-engine::kpi-widget
    report="revenue-by-status"
    :cols="3"
    color="#0077A8"
    variant="flat"
/>

{{-- Table with filters + export --}}
<x-reporting-engine::report-filter report="orders" :inline="true" />
<x-reporting-engine::report-widget
    report="orders"
    :show-export="true"
    :per-page="15"
/>

{{-- Bar / line / pie chart (Chart.js, SSR table fallback) --}}
<x-reporting-engine::chart-widget
    report="revenue-by-status"
    chart-type="bar"
    label-column="status"
    value-column="total_revenue"
/>

{{-- Full dashboard from saved config --}}
<x-dashboard slug="ceo-dashboard" />

{{-- Export buttons only --}}
<x-reporting-engine::report-export
    report="orders"
    :formats="['csv', 'xlsx', 'json']"
/>
```

#### Livewire (real-time filtering, sorting, pagination)

```blade
<livewire:report-widget
    report="orders"
    :per-page="15"
    :show-filters="true"
    :show-export="true"
/>
```

---

### 3. Facade (PHP)

```php
use Mostafax\ReportingEngine\Support\Facades\ReportingEngine;

// Execute an ad-hoc DSL (not saved)
$result = ReportingEngine::run([
    'source'       => 'mysql',
    'table'        => 'orders',
    'aggregations' => [
        ['function' => 'sum',   'column' => 'amount', 'alias' => 'revenue'],
        ['function' => 'count', 'column' => 'id',     'alias' => 'total'],
    ],
    'filters' => [
        'operator'   => 'AND',
        'conditions' => [
            ['field' => 'status', 'operator' => '=', 'value' => 'completed'],
        ],
    ],
    'group_by'   => ['status'],
    'order_by'   => [['column' => 'revenue', 'direction' => 'desc']],
    'pagination' => ['page' => 1, 'per_page' => 25],
]);

$result->data;                       // array of rows
$result->total;                      // total count without LIMIT
$result->metadata->executionTimeMs;  // e.g. 12.4
$result->metadata->cacheHit;         // true if served from Redis
```

---

## DSL Reference

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `source` | `string` | ✅ | `mysql` or `mongodb` |
| `table` | `string` | ✅ | Table (MySQL) or collection (MongoDB) |
| `fields` | `array` | — | `[{column, alias?}]` |
| `aggregations` | `array` | — | `[{function, column, alias}]` |
| `filters` | `object` | — | `{operator: AND/OR, conditions[]}` — nestable |
| `group_by` | `array` | — | `["field1", "field2"]` |
| `order_by` | `array` | — | `[{column, direction: asc/desc}]` |
| `pagination` | `object` | — | `{page, per_page}` |
| `joins` | `array` | — | MySQL only — `[{type, table, first, operator, second}]` |

**Allowed operators:** `=` `!=` `>` `>=` `<` `<=` `like` `not_like` `in` `not_in` `between` `null` `not_null`

**Allowed aggregations:** `sum` `count` `avg` `min` `max` `count_distinct`

---

## MongoDB DSL

Same structure — just change `"source"` to `"mongodb"` and add `"connection"`:

```json
{
  "source":     "mongodb",
  "connection": "mongodb",
  "table":      "analytics",
  "aggregations": [
    {"function": "count_distinct", "column": "userId",  "alias": "unique_users"},
    {"function": "sum",            "column": "revenue", "alias": "total_revenue"}
  ],
  "group_by":   ["channel"],
  "order_by":   [{"column": "total_revenue", "direction": "desc"}],
  "pagination": {"page": 1, "per_page": 20}
}
```

The engine builds a `$match → $group → $project → $sort → $facet` pipeline automatically — data + total count in one round-trip.

---

## REST API

All endpoints are prefixed with `config('reporting-engine.routes.prefix')` (default: `api/reporting`).

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/reporting/run` | Ad-hoc DSL execution (not saved) |
| `GET` | `/api/reporting` | List saved reports (paginated) |
| `POST` | `/api/reporting` | Create and save a report |
| `GET` | `/api/reporting/{id}` | Fetch a saved report definition |
| `PUT` | `/api/reporting/{id}` | Update a saved report |
| `DELETE` | `/api/reporting/{id}` | Delete a report |
| `POST` | `/api/reporting/{id}/run` | Execute a saved report |
| `GET` | `/api/reporting/{id}/export?format=csv` | Stream export — `csv`, `xlsx`, or `json` |

---

## Blade Component Reference

| Component | Key Props | Description |
|-----------|-----------|-------------|
| `x-reporting-engine::kpi-widget` | `report`, `cols`, `color`, `variant` | Aggregation metric cards |
| `x-reporting-engine::report-widget` | `report`, `per-page`, `show-export`, `show-filters` | Full report table widget |
| `x-reporting-engine::chart-widget` | `report`, `chart-type`, `label-column`, `value-column`, `height` | Chart.js with SSR fallback |
| `x-dashboard` | `slug`, `cols`, `theme` | Full saved dashboard |
| `x-reporting-engine::report-filter` | `report`, `inline` | Auto-generated filter form |
| `x-reporting-engine::report-export` | `report`, `formats` | Download buttons |
| `livewire:report-widget` | `report`, `per-page`, `show-filters`, `show-export` | Real-time Livewire widget |

### KPI Widget Variants

Pass `variant="…"` to switch the visual theme of KPI cards:

| Variant | Description |
|---------|-------------|
| `flat` *(default)* | White card with 3 px colored top border |
| `gradient` | Soft tinted background from accent color (`color-mix`) |
| `dark` | Near-black `#111827` card, glowing accent number |
| `glass` | Frosted-glass cards on a gradient backdrop |
| `minimal` | No card box — oversized number with bottom-border rule |
| `bold` | Solid accent-color fill, white text |

```blade
<x-reporting-engine::kpi-widget
    report="revenue-by-status"
    :cols="3"
    color="#7C3AED"
    variant="glass"
/>
```

The `color` prop sets a CSS custom property (`--kpi-color`) that all variants inherit, so switching variant preserves the brand color. In the `/reporting-demo` page a live switcher lets you toggle all variants and the color picker without a page reload.

### CSS Framework Theme

Set once in config or per-component:

```php
// config/reporting-engine.php
'blade' => ['theme' => 'bootstrap'],  // 'bootstrap' | 'tailwind' | 'auto'
```

```blade
<x-reporting-engine::report-widget report="id" theme="tailwind" />
```

RTL activates automatically for `ar`, `fa`, `he`, `ur` locales.

---

## Configuration

```php
// config/reporting-engine.php (published via vendor:publish)

'limits' => [
    'max_rows'              => 10_000,
    'max_per_page'          => 500,
    'max_execution_seconds' => 30,
    'max_joins'             => 5,
],

'cache' => [
    'enabled' => true,
    'driver'  => 'redis',   // requires predis/predis or phpredis extension
    'ttl'     => 300,       // seconds; 0 = disabled
],

'multi_tenancy' => [
    'enabled'       => false,
    'tenant_column' => 'tenant_id',
],

'field_acl' => [
    'always_deny' => ['password', 'api_key', 'secret'],
    'role_deny'   => [
        'analyst' => ['ssn', 'credit_card'],
    ],
],

'blade' => [
    'theme'       => 'auto',    // auto-detects tailwind.config.js
    'rtl_locales' => ['ar', 'fa', 'he', 'ur'],
],
```

---

## Custom Adapters

```php
// Implement DataSourceInterface
class PostgreSQLDataSource implements DataSourceInterface
{
    public function supports(string $sourceType): bool  { return $sourceType === 'pgsql'; }
    public function query(QueryDefinition $def): ExecutionResult     { ... }
    public function aggregate(QueryDefinition $def): ExecutionResult { ... }
    public function count(QueryDefinition $def): int                 { ... }
}

// Register in AppServiceProvider::boot()
$this->app->make(DataSourceResolver::class)
    ->register('pgsql', new PostgreSQLDataSource());
```

---

## Exports

```php
use Mostafax\ReportingEngine\Application\Services\ExportService;

// Stream file download — no temp file, no memory spike
return $exportService->exportById(
    reportId:  $id,
    format:    'xlsx',      // csv | xlsx | json
    userRoles: $userRoles,
);

// Register a custom format
$factory->register('parquet', new ParquetExporter());
```

---

## Security Model

| Layer | Mechanism | Protects Against |
|-------|-----------|-----------------|
| Identifier whitelist | `QuerySanitizer` (Regex) | Column/table name injection |
| PDO bindings | Laravel Query Builder | Value injection in SQL |
| Field ACL | `FieldAccessControl` | Sensitive field exposure |
| Query limits | `QueryValidator` | Resource exhaustion |
| Rate limiting | `QueryLimitMiddleware` | Abuse / DoS |
| Tenant isolation | `ReportEngine` | Cross-tenant data leakage |

---

## Architecture Overview

```
ReportEngine::run($dsl)
  1. DslParser          → typed QueryDefinition (immutable, MD5-hashed)
  2. QueryValidator     → limits + operator whitelist
  3. QuerySanitizer     → identifier injection protection
  4. FieldAccessControl → strip denied fields / throw on denied filters
  5. QueryCacheManager  → Redis GET by definition hash (tenant-scoped)
  6. DataSourceResolver → MySQLDataSource | MongoDataSource | custom
  7. Adapter executes   → ExecutionResult (data + total + metadata)
  8. Cache PUT + ReportExecuted event dispatched
```

**MySQL adapter** uses Laravel Query Builder with a subquery-wrapped count so `ORDER BY alias` on aggregations works correctly across all versions.

**MongoDB adapter** builds a `$facet` pipeline that returns data + `totalCount` in a single round-trip; all `BSONArray`/`BSONDocument`/`ObjectId`/`UTCDateTime` values are normalised to plain PHP types automatically.

**Clean Architecture:** Domain layer (entities, value objects, events) has zero framework dependencies. Infrastructure handles all Laravel/DB specifics.

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `dhr_reports` | Saved report definitions (DSL + metadata) |
| `dhr_report_executions` | Execution audit log (ms, rows, memory, cache hit) |
| `dhr_dashboards` | Dashboard configurations |
| `dhr_widgets` | Widget bindings (report → dashboard position) |

---

## Demo Data

The `ReportingEngineDemoSeeder` creates everything needed to run `/reporting-demo`:

| Resource | Details |
|----------|---------|
| `demo_orders` (MySQL) | 80 rows — customer, status, region, amount, quantity |
| `demo_events` (MongoDB) | 120 documents — channel, action, revenue, user_id |
| `demo-revenue-by-status` | MySQL GROUP BY status with SUM/COUNT/AVG |
| `demo-all-orders` | Paginated order list with filters |
| `demo-top-customers` | Revenue per customer with GROUP BY |
| `demo-mongo-revenue-by-channel` | MongoDB aggregation by marketing channel |
| `demo-mongo-events-log` | Paginated MongoDB event log |

---

## Requirements

- PHP **8.2+**
- Laravel **10 / 11 / 12 / 13**
- `predis/predis` **^2.0|^3.0** *(Redis cache — required)*
- `mongodb/laravel-mongodb` **^4.0|^5.0** *(MongoDB source — optional)*
- `phpoffice/phpspreadsheet` **^1.29|^2.0** *(XLSX export — optional)*
- `livewire/livewire` **^2.0|^3.0** *(real-time widgets — optional)*

---

## License

MIT © [Mostafa](mailto:mostafa.m.elbiar2@gmail.com)
