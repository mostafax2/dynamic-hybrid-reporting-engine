<div align="center">

# Dynamic Hybrid Reporting Engine

**Enterprise reporting infrastructure for Laravel — MySQL + MongoDB, no SQL required**

[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-10%2F11%2F12%2F13-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![Redis](https://img.shields.io/badge/Redis-Required-DC382D?style=flat-square&logo=redis)](https://redis.io)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)
[![Version](https://img.shields.io/badge/Version-1.0.0-blue?style=flat-square)](CHANGELOG.md)

*One JSON DSL. MySQL + MongoDB. Visual no-code builder. Six KPI themes.*  
*Enterprise versioning, sharing, and row-level security — all built in.*

**Author:** Mostafa Elbayyar — [mostafa.m.elbiar2@gmail.com](mailto:mostafa.m.elbiar2@gmail.com)  
**GitHub:** [github.com/mostafax2/dynamic-hybrid-reporting-engine](https://github.com/mostafax2/dynamic-hybrid-reporting-engine)  
**Docs:** [mostafax2.github.io/dynamic-hybrid-reporting-engine](https://mostafax2.github.io/dynamic-hybrid-reporting-engine/)

</div>

---

## Table of Contents

- [Overview](#-overview)
- [Requirements](#-requirements)
- [Quick Install](#-quick-install)
- [Detailed Setup](#-detailed-setup)
- [Visual Report Builder](#-visual-report-builder)
- [DSL Reference](#-dsl-reference)
- [PHP Facade](#-php-facade)
- [Blade Components](#-blade-components)
- [KPI Themes](#-kpi-themes)
- [REST API](#-rest-api)
- [Versioning](#-versioning)
- [Sharing & Permissions](#-sharing--permissions)
- [Row-Level Security](#-row-level-security)
- [Templates](#-templates)
- [Security Model](#-security-model)
- [Custom Adapters](#-custom-adapters)
- [Configuration](#-configuration)
- [Database Tables](#-database-tables)
- [Architecture](#-architecture)
- [FAQ](#-faq)

---

## 🌟 Overview

**Dynamic Hybrid Reporting Engine** is a Laravel package that delivers a complete reporting infrastructure in a single `composer require`. It provides a visual no-code report builder, a unified JSON DSL that works identically for MySQL and MongoDB, six live-switchable KPI card themes, and enterprise-grade features including versioning, RBAC sharing, row-level security, and Redis caching.

### Feature Summary

| Feature | Description |
|---|---|
| 🏗️ **Visual Report Builder** | Full Vue 3 SPA at `/report-builder` — 10 panels, drag-and-drop columns, live preview, no SQL |
| 🔀 **Unified JSON DSL** | One schema for MySQL and MongoDB — fields, filters (nested AND/OR), aggregations, joins with aliases |
| ∑ **Formula Engine** | Computed columns pushed to SQL or MongoDB `$addFields` — full character-level security |
| 🎨 **6 KPI Themes** | `flat · gradient · dark · glass · minimal · bold` — live-switchable with a color picker |
| 🕑 **Versioning** | Auto-snapshot before every save; one-click rollback — nothing is ever lost |
| 👥 **Sharing & RBAC** | Share by user / role / team with `view` / `edit` / `admin` levels and optional expiry |
| 🔒 **Row-Level Security** | Per-role WHERE policies stored in `dhr_rls_policies`, AND-merged into every query automatically |
| 📋 **Templates** | Save any report as a reusable template; system templates ship with the package |
| ⚡ **Redis Cache** | Results cached by MD5 definition hash, tenant-scoped, tag-invalidated on update |
| 📤 **3 Export Formats** | CSV (UTF-8 BOM streaming) · XLSX (PhpSpreadsheet) · JSON — no temp files |
| 🧩 **Blade Components** | KPI cards, charts, tables, filters, export buttons — Bootstrap & Tailwind, RTL-ready |
| 🏢 **Multi-Tenant** | Tenant ID injected into every query; cache keys are per-tenant |

---

## 📋 Requirements

### Required

| Dependency | Version |
|---|---|
| PHP | `8.2+` |
| Laravel | `10 / 11 / 12 / 13` |
| `predis/predis` | `^2.0` or `^3.0` |

### Optional

| Package | Purpose |
|---|---|
| `phpoffice/phpspreadsheet` | XLSX export |
| `livewire/livewire` | Real-time filterable widgets |
| `mongodb/laravel-mongodb` | MongoDB data source |

---

## ⚡ Quick Install

```bash
# 1. Install the package and Redis client
composer require mostafax/dynamic-hybrid-reporting-engine predis/predis

# 2. Publish config and run migrations
php artisan vendor:publish --tag=reporting-engine-config
php artisan vendor:publish --tag=reporting-engine-migrations
php artisan migrate

# 3. Seed demo data (optional)
php artisan db:seed --class=ReportingEngineDemoSeeder
```

**Done!** Open `/reporting-demo` to see live components, `/report-builder` for the visual builder, and `/api/reporting` for the REST API.

---

## 🔧 Detailed Setup

### Step 1 — Install

```bash
composer require mostafax/dynamic-hybrid-reporting-engine predis/predis
```

For local development with a path repository:

```json
{
    "repositories": [
        { "type": "path", "url": "packages/mostafax/reporting-engine" }
    ],
    "require": {
        "mostafax/dynamic-hybrid-reporting-engine": "@dev"
    }
}
```

### Step 2 — Publish Assets

```bash
# Config only
php artisan vendor:publish --tag=reporting-engine-config

# Migrations only
php artisan vendor:publish --tag=reporting-engine-migrations

# Vue builder assets
php artisan vendor:publish --tag=reporting-engine-builder-js
```

### Step 3 — Run Migrations

```bash
php artisan migrate
```

### Step 4 — Environment Variables

```env
# Redis (required)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Route prefix (default: api/reporting)
REPORTING_ROUTE_PREFIX=api/reporting

# Auth middleware (leave empty to disable for dev)
REPORTING_AUTH_MIDDLEWARE=auth:sanctum

# Multi-tenancy (optional)
REPORTING_TENANT_ENABLED=false
REPORTING_TENANT_COLUMN=tenant_id
```

### Step 5 — Build the Vue Builder (optional)

```bash
npm install --legacy-peer-deps
php artisan vendor:publish --tag=reporting-engine-builder-js
npm run build
```

Visit `/report-builder` — the full Vue 3 visual builder is now available.

### Step 6 — Seed Demo Data (optional)

```bash
php artisan db:seed --class=ReportingEngineDemoSeeder
```

Creates:
- `demo_orders` — 80 MySQL rows
- `demo_events` — 120 MongoDB documents
- 5 demo reports saved in `dhr_reports`

---

## 🏗️ Visual Report Builder

A full-page Vue 3 SPA at `/report-builder`:

```
/report-builder          → create a new report
/report-builder/{id}     → edit an existing report
```

Disable auth for development:

```env
REPORTING_AUTH_MIDDLEWARE=
```

### Builder Panels

| Panel | What you can do |
|---|---|
| ① Source | Pick MySQL/MongoDB, connection, table — columns auto-load |
| ② Joins | Visual INNER/LEFT/RIGHT JOIN with alias support |
| ③ Columns | Drag & drop reorder, set label, format, width, visibility |
| ④ Formula ∑ | Write computed columns: `amount * tax_rate` or `IF(status='paid',1,0)` |
| ⑤ Filters | AND/OR tree builder — no SQL required |
| ⑥ Aggregation | GROUP BY + SUM/COUNT/AVG/MIN/MAX |
| ⑦ Sort | Draggable ORDER BY with ASC/DESC toggle |
| ⑧ Preview | Live paginated data preview with formatting |
| ⑨ History | Version list + one-click rollback |
| ⑩ Sharing | Grant view/edit/admin to users, roles, or teams |

### Embed a Saved Report

```blade
{{-- Embed anywhere after saving in the builder --}}
<x-reporting-engine::report-widget
    report="monthly-revenue"
    :show-export="true"
    :per-page="25"
/>

{{-- Or via Facade --}}
$result = ReportingEngine::run('monthly-revenue');

{{-- Or via REST API --}}
POST /api/reporting/monthly-revenue/run
```

---

## 📐 DSL Reference

### Full Schema

| Field | Type | Required | Description |
|---|---|---|---|
| `source` | `string` | ✅ | `mysql` or `mongodb` |
| `connection` | `string` | — | DB connection name (default: source) |
| `table` | `string` | ✅ | Table or MongoDB collection |
| `fields` | `array` | — | `[{column, alias?}]` |
| `computed` | `array` | — | `[{alias, expression, label?, format?}]` |
| `column_meta` | `array` | — | `[{key, label, visible, order, format?, width?, align?}]` |
| `aggregations` | `array` | — | `[{function, column, alias}]` |
| `filters` | `object` | — | `{operator: AND/OR, conditions[]}` — fully nestable |
| `group_by` | `array` | — | `["field1", "field2"]` |
| `order_by` | `array` | — | `[{column, direction}]` |
| `pagination` | `object` | — | `{page, per_page}` |
| `joins` | `array` | — | MySQL only — `[{type, table, alias?, first, operator, second}]` |

**Filter operators:** `=` `!=` `>` `>=` `<` `<=` `like` `not_like` `in` `not_in` `between` `null` `not_null`

**Aggregation functions:** `sum` `count` `avg` `min` `max` `count_distinct`

### MySQL DSL Example

```json
{
  "source": "mysql",
  "table":  "orders",
  "fields": [
    { "column": "id",            "alias": "order_id" },
    { "column": "customer_name" },
    { "column": "amount" }
  ],
  "aggregations": [
    { "function": "sum",   "column": "amount", "alias": "revenue" },
    { "function": "count", "column": "id",     "alias": "orders"  }
  ],
  "filters": {
    "operator": "AND",
    "conditions": [
      { "field": "status", "operator": "=",  "value": "completed" },
      { "field": "amount", "operator": ">=", "value": 100 }
    ]
  },
  "group_by":   ["status"],
  "order_by":   [{ "column": "revenue", "direction": "desc" }],
  "pagination": { "page": 1, "per_page": 25 }
}
```

### MongoDB DSL Example

```json
{
  "source":     "mongodb",
  "connection": "mongodb",
  "table":      "analytics",
  "aggregations": [
    { "function": "sum",            "column": "revenue", "alias": "total_revenue"  },
    { "function": "count_distinct", "column": "userId",  "alias": "unique_users" },
    { "function": "avg",            "column": "revenue", "alias": "avg_revenue"    }
  ],
  "group_by":   ["channel"],
  "order_by":   [{ "column": "total_revenue", "direction": "desc" }],
  "pagination": { "page": 1, "per_page": 20 }
}
```

Engine auto-builds: `$match → $addFields → $group → $project → $sort → $facet`  
Data + `totalCount` in a single round-trip. `BSONArray`/`ObjectId`/`UTCDateTime` normalised automatically.

### Computed Fields (Formula Engine)

```json
{
  "computed": [
    {
      "alias":      "margin",
      "expression": "(amount - cost) / NULLIF(amount, 0) * 100",
      "label":      "Margin %",
      "format":     "percent"
    },
    {
      "alias":      "tax",
      "expression": "amount * 0.15",
      "label":      "Tax Amount",
      "format":     "currency"
    },
    {
      "alias":      "tier",
      "expression": "IF(amount > 1000, 'Premium', 'Standard')",
      "label":      "Customer Tier"
    }
  ]
}
```

**Allowed functions:** `IF` `COALESCE` `NULLIF` `ROUND` `ABS` `CEILING` `FLOOR` `CONCAT` `DATEDIFF` `DATE_FORMAT` `UPPER` `LOWER` `TRIM` `LENGTH` `NOW` `YEAR` `MONTH` `DAY`

**Security:** All expressions pass through `FormulaLexer` (character allowlist) then `MySQLFormulaTranspiler` (backtick-quoted identifiers, function whitelist) before any SQL is built.

### Column Metadata

```json
{
  "column_meta": [
    { "key": "order_id", "label": "Order #",   "visible": true,  "order": 0, "width": "80px",  "align": "left"  },
    { "key": "amount",   "label": "Revenue",   "visible": true,  "order": 1, "format": "currency", "align": "right" },
    { "key": "cost",     "label": "Cost",      "visible": false, "order": 2 },
    { "key": "margin",   "label": "Margin %",  "visible": true,  "order": 3, "format": "percent",  "align": "right" }
  ]
}
```

**`format` values:** `currency` · `percent` · `date` · `datetime` · `number` · `text`  
**`align` values:** `left` · `center` · `right`

### Joins with Aliases

```json
{
  "source": "mysql",
  "table":  "orders",
  "joins": [
    {
      "type":     "left",
      "table":    "customers",
      "alias":    "c",
      "first":    "orders.customer_id",
      "operator": "=",
      "second":   "c.id"
    }
  ],
  "fields": [
    { "column": "orders.id",     "alias": "order_id" },
    { "column": "c.name",        "alias": "customer" },
    { "column": "orders.amount", "alias": "amount"   }
  ]
}
```

---

## 🔧 PHP Facade

```php
use Mostafax\ReportingEngine\Support\Facades\ReportingEngine;

// Run an ad-hoc DSL query
$result = ReportingEngine::run([
    'source'       => 'mysql',
    'table'        => 'orders',
    'fields'       => [
        ['column' => 'id',     'alias' => 'order_id'],
        ['column' => 'status'],
        ['column' => 'amount'],
    ],
    'computed' => [
        ['alias' => 'tax', 'expression' => 'amount * 0.15', 'label' => 'Tax Amount', 'format' => 'currency'],
    ],
    'aggregations' => [
        ['function' => 'sum',   'column' => 'amount', 'alias' => 'revenue'],
        ['function' => 'count', 'column' => 'id',     'alias' => 'orders'],
    ],
    'filters' => [
        'operator'   => 'AND',
        'conditions' => [
            ['field' => 'status', 'operator' => '=',  'value' => 'completed'],
            ['field' => 'amount', 'operator' => '>=', 'value' => 100],
        ],
    ],
    'order_by'   => [['column' => 'revenue', 'direction' => 'desc']],
    'pagination' => ['page' => 1, 'per_page' => 25],
]);

$result->data;                        // array of rows (includes computed 'tax' column)
$result->total;                       // total row count without LIMIT
$result->metadata->executionTimeMs;   // e.g. 8.4
$result->metadata->cacheHit;          // true if served from Redis

// Execute a saved report by ID
use Mostafax\ReportingEngine\Application\Services\ExecutionService;

$result = app(ExecutionService::class)
    ->runById('monthly-revenue', ['pagination' => ['page' => 2]]);
```

---

## 🧩 Blade Components

```blade
{{-- KPI cards — 6 visual themes --}}
<x-reporting-engine::kpi-widget
    report="revenue-by-status"
    :cols="3"
    color="#0077A8"
    variant="glass"
/>

{{-- Chart with Chart.js (SSR table fallback for no-JS) --}}
<x-reporting-engine::chart-widget
    report="revenue-by-status"
    chart-type="bar"
    label-column="status"
    value-column="total_revenue"
/>

{{-- Filterable table with inline filters and export --}}
<x-reporting-engine::report-filter report="orders" :inline="true" />
<x-reporting-engine::report-widget
    report="orders"
    :show-export="true"
    :per-page="15"
/>

{{-- Export buttons only --}}
<x-reporting-engine::report-export
    report="orders"
    :formats="['csv', 'xlsx', 'json']"
/>

{{-- Livewire: real-time filter + sort + paginate --}}
<livewire:report-widget
    report="orders"
    :per-page="20"
    :show-filters="true"
    :show-export="true"
/>
```

---

## 🎨 KPI Themes

Pass `variant="…"` to switch the card style. The `color` prop sets `--kpi-color` inherited by all variants. Visit `/reporting-demo` for a live switcher and color picker.

```blade
<x-reporting-engine::kpi-widget report="id" variant="flat"     /> {{-- default, white card with color top border --}}
<x-reporting-engine::kpi-widget report="id" variant="gradient" /> {{-- tinted background --}}
<x-reporting-engine::kpi-widget report="id" variant="dark"     /> {{-- dark slate background --}}
<x-reporting-engine::kpi-widget report="id" variant="glass"    /> {{-- frosted glass on color gradient --}}
<x-reporting-engine::kpi-widget report="id" variant="minimal"  /> {{-- borderless, color bottom line --}}
<x-reporting-engine::kpi-widget report="id" variant="bold"     /> {{-- solid color fill --}}
```

---

## 🌐 REST API

All endpoints are prefixed with `config('reporting-engine.routes.prefix')` (default: `api/reporting`).

### Report CRUD

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/run` | Ad-hoc DSL execution |
| `GET` | `/` | List saved reports |
| `POST` | `/` | Create and save a report |
| `GET` | `/{id}` | Get report details |
| `PUT` | `/{id}` | Update report (auto-versions) |
| `DELETE` | `/{id}` | Delete report |
| `POST` | `/{id}/run` | Execute a saved report |
| `GET` | `/{id}/export?format=csv` | Stream export (csv / xlsx / json) |
| `POST` | `/{id}/clone` | Clone a report |

**POST /api/reporting/run** — Body:
```json
{
  "definition": {
    "source": "mysql",
    "table": "orders",
    "fields": [{"column": "id"}, {"column": "amount"}],
    "filters": {
      "operator": "AND",
      "conditions": [{"field": "status", "operator": "=", "value": "completed"}]
    }
  }
}
```

**POST /api/reporting** — Body:
```json
{
  "name": "Revenue by Status",
  "description": "Monthly revenue grouped by order status",
  "definition": { "source": "mysql", "table": "orders", "..." : "..." },
  "change_note": "Initial version"
}
```

### Versioning

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/{id}/versions` | List all versions |
| `GET` | `/{id}/versions/{v}` | Get a specific version |
| `POST` | `/{id}/versions/{v}/rollback` | Restore to a version |

### Sharing

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/{id}/shares` | List active shares |
| `POST` | `/{id}/shares` | Grant access |
| `DELETE` | `/{id}/shares/{shareId}` | Revoke access |

### Templates

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/templates` | List templates |
| `POST` | `/templates/from-report/{id}` | Save report as template |
| `POST` | `/templates/{id}/instantiate` | Create report from template |
| `DELETE` | `/templates/{id}` | Delete template |

### Schema Introspection

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/schema/tables?source=mysql&connection=mysql` | List tables |
| `GET` | `/schema/columns?source=mysql&table=orders` | Describe columns |

### RLS Policies

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/rls-policies` | List policies |
| `POST` | `/rls-policies` | Create policy |
| `PUT` | `/rls-policies/{id}` | Update policy |
| `DELETE` | `/rls-policies/{id}` | Delete policy |

---

## 🕑 Versioning

Every `PUT /{id}` automatically snapshots the **previous** definition before overwriting. The current state is always preserved — nothing is lost on rollback.

```php
// List version history
GET /api/reporting/{id}/versions
// → [{ version_number: 3, saved_by: "user-id", change_note: "Added tax column", created_at: "..." }]

// View a specific version
GET /api/reporting/{id}/versions/2

// Roll back to v2 (current state auto-saved as v4 first)
POST /api/reporting/{id}/versions/2/rollback
```

Pass a `change_note` when saving:

```json
PUT /api/reporting/{id}
{ "name": "Revenue Report", "definition": {...}, "change_note": "Added margin column" }
```

---

## 👥 Sharing & Permissions

Share any report with individual users, roles, or teams. Three permission levels: `view`, `edit`, `admin`. Optional expiry date per grant.

```json
// Grant view access to a role
POST /api/reporting/{id}/shares
{
  "shareable_type": "role",
  "shareable_id":   "analyst",
  "permission":     "view",
  "expires_at":     "2026-12-31"
}

// Grant edit access to a specific user
POST /api/reporting/{id}/shares
{ "shareable_type": "user", "shareable_id": "usr_123", "permission": "edit" }

// List all active shares
GET /api/reporting/{id}/shares

// Revoke a share
DELETE /api/reporting/{id}/shares/{shareId}
```

---

## 🔒 Row-Level Security

Policies stored in `dhr_rls_policies` are automatically AND-merged into every query for matching roles — no code changes required in individual reports.

```json
// Analysts can only see completed orders
POST /api/reporting/rls-policies
{
  "role":       "analyst",
  "table_name": "orders",
  "where_clause": {
    "operator": "AND",
    "conditions": [
      { "field": "status", "operator": "=", "value": "completed" }
    ]
  }
}
```

- `report_id: null` — global policy (applies to all reports using that table)
- `report_id: "uuid"` — scoped to a specific report only

---

## 📋 Templates

```json
// Save a report as a reusable template
POST /api/reporting/templates/from-report/{reportId}
{ "name": "Monthly Revenue Template", "category": "sales" }

// List templates (system + tenant)
GET /api/reporting/templates?category=sales
// → [{ id, name, category, is_system, definition }]

// Create a report from a template
POST /api/reporting/templates/{templateId}/instantiate
{ "name": "June 2026 Revenue" }
// → Returns a new report DTO with a fresh UUID
```

---

## 🛡️ Security Model

Every layer fires independently — compromising one does not bypass the others.

| Layer | Mechanism | Protects Against |
|---|---|---|
| **Identifier Whitelist** | `QuerySanitizer` — regex `/^[a-zA-Z_][a-zA-Z0-9_.]*$/` on all column/table names | Column/table name injection |
| **Formula Allowlist** | `FormulaLexer` (character set) + `MySQLFormulaTranspiler` (backtick quoting) | Expression injection |
| **PDO Bindings** | Laravel Query Builder — values always PDO parameters, never interpolated | SQL value injection |
| **Field-Level ACL** | `FieldAccessControl` — denied fields silently stripped; denied filter fields throw 422 | Sensitive field exposure |
| **Row-Level Security** | `RowLevelSecurityService` — per-role WHERE policies AND-merged automatically | Unauthorised row access |
| **Query Limits** | `QueryValidator` — max rows, joins, conditions, aggregations enforced before any DB call | Resource exhaustion |
| **Rate Limiting** | `QueryLimitMiddleware` — per-user budget via Laravel RateLimiter | Abuse / DoS |
| **Tenant Isolation** | Tenant ID injected into every query (MySQL WHERE / MongoDB `$match`) | Cross-tenant data leakage |

---

## 🔌 Custom Adapters

```php
use Mostafax\ReportingEngine\Contracts\DataSourceInterface;
use Mostafax\ReportingEngine\Domain\DTOs\QueryDefinition;
use Mostafax\ReportingEngine\Domain\DTOs\ExecutionResult;

class PostgreSQLDataSource implements DataSourceInterface
{
    public function supports(string $sourceType): bool
    {
        return $sourceType === 'pgsql';
    }

    public function query(QueryDefinition $def): ExecutionResult
    {
        $rows = DB::connection('pgsql')
            ->table($def->table)
            ->get()
            ->toArray();

        return new ExecutionResult($rows, $total, $metadata);
    }

    public function aggregate(QueryDefinition $def): ExecutionResult { /* ... */ }
    public function count(QueryDefinition $def): int                 { /* ... */ }
}

// Register in AppServiceProvider::boot()
$this->app->make(DataSourceResolver::class)
    ->register('pgsql', new PostgreSQLDataSource());
```

---

## ⚙️ Configuration

```php
// config/reporting-engine.php

return [

    // ========== Query Limits ==========
    'limits' => [
        'max_rows'              => 10_000,
        'max_per_page'          => 500,
        'max_execution_seconds' => 30,
        'max_joins'             => 5,
        'max_conditions'        => 20,
        'max_aggregations'      => 10,
    ],

    // ========== Redis Cache ==========
    'cache' => [
        'enabled' => env('REPORTING_CACHE_ENABLED', true),
        'driver'  => 'redis',       // requires predis/predis
        'ttl'     => 300,           // seconds
    ],

    // ========== Multi-Tenancy ==========
    'multi_tenancy' => [
        'enabled'       => env('REPORTING_TENANT_ENABLED', false),
        'tenant_column' => env('REPORTING_TENANT_COLUMN', 'tenant_id'),
    ],

    // ========== Field ACL ==========
    'field_acl' => [
        'always_deny' => ['password', 'api_key', 'secret'],
        'role_deny'   => [
            'analyst' => ['ssn', 'credit_card'],
        ],
    ],

    // ========== Blade ==========
    'blade' => [
        'theme'       => 'auto',          // 'bootstrap' | 'tailwind' | 'auto'
        'rtl_locales' => ['ar', 'fa', 'he', 'ur'],
    ],

    // ========== Routes ==========
    'routes' => [
        'enabled'    => true,
        'prefix'     => env('REPORTING_ROUTE_PREFIX', 'api/reporting'),
        'middleware' => array_filter(['api', env('REPORTING_AUTH_MIDDLEWARE', 'auth:sanctum')]),
    ],

];
```

---

## 🗄️ Database Tables

| Table | Purpose |
|---|---|
| `dhr_reports` | Saved report definitions |
| `dhr_report_executions` | Execution audit log |
| `dhr_report_versions` | Full version history (auto-created on every update) |
| `dhr_report_shares` | Sharing permission grants |
| `dhr_report_templates` | Reusable report templates |
| `dhr_rls_policies` | Row-level security policies |
| `dhr_dashboards` | Dashboard configurations |
| `dhr_widgets` | Widget bindings |

---

## 🏛️ Architecture

The 8-step hardened pipeline fires on every report — ad-hoc or saved.

```
ReportEngine::run($dsl)
  01  DslParser            → raw array → typed, immutable QueryDefinition (MD5-hashed)
  02  QueryValidator       → enforce limits + operator/aggregation whitelist
  03  QuerySanitizer       → identifier regex + FormulaLexer character allowlist
  04  FieldACL + RLS       → strip denied fields; AND-merge RLS WHERE policies
  05  Redis Cache          → GET by definition hash (tenant-scoped key)
  06  DataSourceResolver   → MySQLDataSource | MongoDataSource | custom
  07  Execute              → data + total + metadata in one round-trip
  08  Cache PUT + Event    → store result; dispatch ReportExecuted event
```

```php
// Export — streamed, no temp files
use Mostafax\ReportingEngine\Application\Services\ExportService;

return app(ExportService::class)->exportById(
    reportId:  $id,
    format:    $request->query('format', 'csv'),  // csv | xlsx | json
    userRoles: $request->user()->roles(),
);

// Clone a report
POST /api/reporting/{id}/clone
{ "name": "Q2 Revenue Report" }
// → New UUID, "Copy of…" name, same definition, shares not copied
```

---

## ❓ FAQ

**Q: How do I change the API route prefix?**
```php
// config/reporting-engine.php
'routes' => ['prefix' => 'api/v2/reports']
```

**Q: How do I disable authentication in development?**
```env
REPORTING_AUTH_MIDDLEWARE=
```

**Q: Can I use a non-default Redis connection?**
```php
// config/reporting-engine.php
'cache' => [
    'driver'     => 'redis',
    'connection' => 'reporting',  // any connection from config/database.php
],
```

**Q: How do I add a field to the global deny list?**
```php
// config/reporting-engine.php
'field_acl' => [
    'always_deny' => ['password', 'api_key', 'secret', 'stripe_key'],
],
```

**Q: Does it work with PostgreSQL?**  
Not out-of-the-box, but you can write a custom adapter in ~30 lines. See [Custom Adapters](#-custom-adapters) above.

**Q: How do I use it with multi-tenancy?**
```env
REPORTING_TENANT_ENABLED=true
REPORTING_TENANT_COLUMN=tenant_id
```

Bind `current_tenant_id` in a middleware:
```php
app()->bind('current_tenant_id', fn () => auth()->user()->tenant_id);
```

---

## 📄 License

MIT © 2026 [Mostafa Elbayyar](mailto:mostafa.m.elbiar2@gmail.com)

---

<div align="center">

**Built with ❤️ for the Laravel community**

[⭐ Star on GitHub](https://github.com/mostafax2/dynamic-hybrid-reporting-engine) · [🐛 Report Bug](https://github.com/mostafax2/dynamic-hybrid-reporting-engine/issues) · [💡 Request Feature](https://github.com/mostafax2/dynamic-hybrid-reporting-engine/issues)

</div>
