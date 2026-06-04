# Dynamic Hybrid Reporting Engine

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-10%2F11%2F12%2F13-red)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

A Laravel package that lets you define any report as a **JSON DSL** and execute it against **MySQL or MongoDB** through a single unified API — with caching, field-level ACL, multi-tenancy, and CSV/XLSX/JSON exports built in.

📖 **Full Documentation:** [mostafax2.github.io/dynamic-hybrid-reporting-engine](https://mostafax2.github.io/dynamic-hybrid-reporting-engine/)

---

## Installation

```bash
composer require mostafax/dynamic-hybrid-reporting-engine
```

Publish config and run migrations:

```bash
php artisan vendor:publish --tag=reporting-engine-config
php artisan vendor:publish --tag=reporting-engine-migrations
php artisan migrate
```

Optional — for XLSX export:

```bash
composer require phpoffice/phpspreadsheet
```

---

## Quick Start

### Via Facade

```php
use Mostafax\ReportingEngine\Support\Facades\ReportingEngine;

$result = ReportingEngine::run([
    'source'       => 'mysql',
    'table'        => 'orders',
    'fields'       => [
        ['column' => 'id',            'alias' => 'order_id'],
        ['column' => 'customer_name'],
    ],
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
| `table` | `string` | ✅ | Table name (MySQL) or collection (MongoDB) |
| `fields` | `array` | — | `[{column, alias?}]` |
| `aggregations` | `array` | — | `[{function, column, alias}]` |
| `filters` | `object` | — | Nested `{operator, conditions[]}` — AND/OR |
| `group_by` | `array` | — | `["field1", "field2"]` |
| `order_by` | `array` | — | `[{column, direction}]` |
| `pagination` | `object` | — | `{page, per_page}` |
| `joins` | `array` | — | MySQL only — `[{type, table, first, operator, second}]` |

**Allowed operators:** `=` `!=` `>` `>=` `<` `<=` `like` `not_like` `in` `not_in` `between` `null` `not_null`

**Allowed aggregations:** `sum` `count` `avg` `min` `max` `count_distinct`

---

## REST API

All endpoints are prefixed with the value of `reporting-engine.routes.prefix` (default: `api/reporting`).

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/reporting/run` | Ad-hoc DSL execution (not saved) |
| `GET` | `/api/reporting` | List saved reports |
| `POST` | `/api/reporting` | Create and save a report |
| `GET` | `/api/reporting/{id}` | Fetch a saved report |
| `PUT` | `/api/reporting/{id}` | Update a saved report |
| `DELETE` | `/api/reporting/{id}` | Delete a report |
| `POST` | `/api/reporting/{id}/run` | Execute a saved report |
| `GET` | `/api/reporting/{id}/export?format=csv` | Stream export — `csv`, `xlsx`, or `json` |

### Example — Save and Run

```bash
# Save a report
curl -X POST /api/reporting \
  -H "Content-Type: application/json" \
  -d '{"name":"Revenue","definition":{"source":"mysql","table":"orders",...}}'

# Run it
curl -X POST /api/reporting/{id}/run \
  -d '{"page":1,"per_page":50}'

# Export as Excel
curl -O /api/reporting/{id}/export?format=xlsx
```

---

## MongoDB DSL

Same structure — just change `"source"` to `"mongodb"` and use the collection name in `"table"`:

```json
{
  "source": "mongodb",
  "table":  "analytics",
  "aggregations": [
    {"function": "count_distinct", "column": "userId", "alias": "unique_users"},
    {"function": "sum",            "column": "revenue", "alias": "total_revenue"}
  ],
  "group_by":   ["country"],
  "order_by":   [{"column": "total_revenue", "direction": "desc"}],
  "pagination": {"page": 1, "per_page": 20}
}
```

The engine builds a `$match → $group → $project → $sort → $facet` pipeline automatically — data + total count in one round-trip.

---

## Configuration

Publish the config file with `vendor:publish`, then edit `config/reporting-engine.php`:

```php
// Key options
'limits' => [
    'max_rows'              => 10_000,
    'max_per_page'          => 500,
    'max_execution_seconds' => 30,
    'max_joins'             => 5,
],

'cache' => [
    'enabled' => true,
    'driver'  => 'redis',
    'ttl'     => 300,       // seconds
],

'multi_tenancy' => [
    'enabled'       => true,
    'tenant_column' => 'tenant_id',
],

'field_acl' => [
    'always_deny' => ['password', 'api_key', 'secret'],
    'role_deny'   => [
        'analyst' => ['ssn', 'credit_card'],
    ],
],
```

---

## Security

The engine runs six independent security controls before any database call:

| Control | Class | Protects Against |
|---------|-------|-----------------|
| Identifier whitelist | `QuerySanitizer` | Column/table name injection |
| PDO bindings | Laravel Query Builder | Value injection in SQL/MongoDB |
| Field ACL | `FieldAccessControl` | Sensitive field exposure |
| Query limits | `QueryValidator` | Resource exhaustion |
| Rate limiting | `QueryLimitMiddleware` | Abuse / DoS |
| Tenant isolation | `ReportEngine` | Cross-tenant data leakage |

---

## Custom Adapters

Implement `DataSourceInterface` and register at boot:

```php
class PostgreSQLDataSource implements DataSourceInterface
{
    public function supports(string $sourceType): bool
    {
        return $sourceType === 'pgsql';
    }

    public function query(QueryDefinition $def): ExecutionResult { ... }
    public function aggregate(QueryDefinition $def): ExecutionResult { ... }
    public function count(QueryDefinition $def): int { ... }
}

// In AppServiceProvider::boot()
$this->app->make(DataSourceResolver::class)
    ->register('pgsql', new PostgreSQLDataSource());
```

---

## Exports

```php
use Mostafax\ReportingEngine\Application\Services\ExportService;

// Stream a file download directly — no temp file, no memory spike
return $exportService->exportById(
    reportId:  $id,
    format:    'xlsx',      // csv | xlsx | json
    userRoles: $userRoles,
);

// Ad-hoc export
return $exportService->exportAdHoc(
    rawDsl:   $dslArray,
    format:   'csv',
    filename: 'my_report',
);
```

---

## Architecture Overview

```
ReportEngine::run($dsl)
  1. DslParser          → typed QueryDefinition (immutable, hashed)
  2. QueryValidator     → limits + operator whitelist
  3. QuerySanitizer     → identifier injection protection
  4. FieldAccessControl → strip denied fields / throw on denied filters
  5. QueryCacheManager  → Redis GET by definition hash
  6. DataSourceResolver → MySQLDataSource | MongoDataSource | custom
  7. Adapter executes   → ExecutionResult (data + total + metadata)
  8. Cache PUT + ReportExecuted event dispatched
```

Package follows **Clean Architecture** — Domain layer has zero framework dependencies. Infrastructure layer handles all Laravel/DB specifics.

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `dhr_reports` | Saved report definitions |
| `dhr_report_executions` | Execution audit log |
| `dhr_dashboards` | Dashboard configurations |
| `dhr_widgets` | Widget bindings (report → dashboard) |

---

## Requirements

- PHP **8.2+**
- Laravel **10 / 11 / 12 / 13**
- `mongodb/laravel-mongodb` **^4.0\|^5.0** *(for MongoDB source)*
- `phpoffice/phpspreadsheet` **^1.29\|^2.0** *(for XLSX export — optional)*

---

## License

MIT © [Mostafa](mailto:mostafa.m.elbiar2@gmail.com)
