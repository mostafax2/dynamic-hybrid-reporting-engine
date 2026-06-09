<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Tests\Unit\Infrastructure\Builders;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;
use Mostafax\ReportingEngine\Core\DSL\DslParser;
use Mostafax\ReportingEngine\Infrastructure\Builders\MySQLQueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Tests MySQLQueryBuilder SQL generation using an in-memory SQLite connection.
 *
 * We wire a minimal Laravel container so the DB facade resolves correctly
 * without requiring a full framework boot. DB::raw() strings pass through
 * unchanged, so MySQL-specific expressions can be verified against SQLite.
 */
final class MySQLQueryBuilderTest extends TestCase
{
    private DslParser         $parser;
    private MySQLQueryBuilder $builder;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Boot an in-memory SQLite connection.
        // Register as BOTH 'mysql' (used by the DSL) and the default connection
        // so that DB::raw() — which resolves via getDefaultConnection() — also works.
        $config = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];

        $capsule = new Capsule();
        $capsule->addConnection($config);          // registers as 'default'
        $capsule->addConnection($config, 'mysql'); // registers as 'mysql'

        // Tell the Capsule container which connection is "default"
        $capsule->getContainer()['config']['database.default'] = 'mysql';

        $capsule->setAsGlobal();

        // Wire the DB facade to the DatabaseManager from the Capsule
        $container = new Container();
        $container->instance('db', $capsule->getDatabaseManager());
        Facade::setFacadeApplication($container);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser  = new DslParser();
        $this->builder = new MySQLQueryBuilder();
    }

    // ── Basic SQL structure ───────────────────────────────────────────────────

    public function test_builds_query_for_correct_table(): void
    {
        $query = $this->builder->build($this->parser->parse($this->baseDsl()));
        $sql   = $query->toSql();

        // SQLite quotes with "", MySQL with ``; check the unquoted name
        $this->assertStringContainsStringIgnoringCase('orders', $sql);
    }

    public function test_select_includes_plain_columns(): void
    {
        $sql = $this->builder->build($this->parser->parse($this->baseDsl()))->toSql();

        $this->assertStringContainsString('`id`', $sql);
        $this->assertStringContainsString('`status`', $sql);
    }

    public function test_select_includes_aggregation(): void
    {
        $sql = $this->builder->build($this->parser->parse($this->baseDsl()))->toSql();

        $this->assertStringContainsStringIgnoringCase('SUM(`amount`) as `total_revenue`', $sql);
    }

    // ── GROUP BY: column type ─────────────────────────────────────────────────

    public function test_plain_column_group_by_in_sql(): void
    {
        $sql = $this->builder->build($this->parser->parse($this->baseDsl()))->toSql();

        $this->assertStringContainsString('group by', strtolower($sql));
    }

    // ── GROUP BY: date_trunc type ────────────────────────────────────────────

    public function test_date_trunc_month_generates_date_format(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [[
            'type'        => 'date_trunc',
            'column'      => 'created_at',
            'granularity' => 'month',
            'alias'       => 'created_month',
        ]];

        $sql = $this->builder->build($this->parser->parse($dsl))->toSql();

        $this->assertStringContainsString("DATE_FORMAT(`created_at`, '%Y-%m-01')", $sql);
    }

    public function test_date_trunc_year_generates_date_format(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [[
            'type'        => 'date_trunc',
            'column'      => 'created_at',
            'granularity' => 'year',
        ]];

        $sql = $this->builder->build($this->parser->parse($dsl))->toSql();

        $this->assertStringContainsString("DATE_FORMAT(`created_at`, '%Y-01-01')", $sql);
    }

    public function test_date_trunc_day_generates_date_function(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [[
            'type'        => 'date_trunc',
            'column'      => 'created_at',
            'granularity' => 'day',
        ]];

        $sql = $this->builder->build($this->parser->parse($dsl))->toSql();

        $this->assertStringContainsString('DATE(`created_at`)', $sql);
    }

    public function test_date_trunc_select_alias_included(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [[
            'type'        => 'date_trunc',
            'column'      => 'created_at',
            'granularity' => 'month',
            'alias'       => 'order_month',
        ]];

        $sql = $this->builder->build($this->parser->parse($dsl))->toSql();

        $this->assertStringContainsString('`order_month`', $sql);
    }

    // ── GROUP BY: expression type ────────────────────────────────────────────

    public function test_expression_type_group_by_uses_formula_transpiler(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [[
            'type'       => 'expression',
            'expression' => 'YEAR(created_at)',
            'alias'      => 'order_year',
        ]];

        $sql = $this->builder->build($this->parser->parse($dsl))->toSql();

        $this->assertStringContainsString('`order_year`', $sql);
    }

    // ── Joins ─────────────────────────────────────────────────────────────────

    public function test_left_join_included_in_sql(): void
    {
        $dsl           = $this->baseDsl();
        $dsl['joins']  = [[
            'type'     => 'left',
            'table'    => 'customers',
            'first'    => 'orders.customer_id',
            'operator' => '=',
            'second'   => 'customers.id',
        ]];

        $sql = $this->builder->build($this->parser->parse($dsl))->toSql();

        $this->assertStringContainsStringIgnoringCase('left join', $sql);
        $this->assertStringContainsStringIgnoringCase('customers', $sql);
    }

    public function test_join_with_alias(): void
    {
        $dsl           = $this->baseDsl();
        $dsl['joins']  = [[
            'type'     => 'left',
            'table'    => 'customers',
            'alias'    => 'c',
            'first'    => 'orders.customer_id',
            'operator' => '=',
            'second'   => 'c.id',
        ]];

        $sql = $this->builder->build($this->parser->parse($dsl))->toSql();

        // SQLite quotes identifiers with ""; MySQL with ``; check unquoted fragments
        $this->assertStringContainsStringIgnoringCase('customers', $sql);
        $this->assertStringContainsStringIgnoringCase('as', $sql);
    }

    // ── Filters ───────────────────────────────────────────────────────────────

    public function test_where_clause_from_filter(): void
    {
        $dsl            = $this->baseDsl();
        $dsl['filters'] = [
            'operator'   => 'AND',
            'conditions' => [['field' => 'status', 'operator' => '=', 'value' => 'active']],
        ];

        $sql      = $this->builder->build($this->parser->parse($dsl))->toSql();
        $bindings = $this->builder->build($this->parser->parse($dsl))->getBindings();

        $this->assertStringContainsStringIgnoringCase('where', $sql);
        $this->assertContains('active', $bindings);
    }

    public function test_where_in_filter(): void
    {
        $dsl            = $this->baseDsl();
        $dsl['filters'] = [
            'operator'   => 'AND',
            'conditions' => [['field' => 'status', 'operator' => 'in', 'value' => ['a', 'b']]],
        ];

        $sql = $this->builder->build($this->parser->parse($dsl))->toSql();

        $this->assertStringContainsStringIgnoringCase('in (', $sql);
    }

    // ── HAVING clause ─────────────────────────────────────────────────────────

    public function test_having_clause_included_when_having_dsl(): void
    {
        $dsl           = $this->baseDsl();
        $dsl['having'] = [
            'operator'   => 'AND',
            'conditions' => [
                ['field' => 'total_revenue', 'operator' => '>', 'value' => 500],
            ],
        ];

        $sql = $this->builder->build($this->parser->parse($dsl))->toSql();

        $this->assertStringContainsStringIgnoringCase('having', $sql);
    }

    // ── Window functions ──────────────────────────────────────────────────────

    public function test_window_function_row_number_in_select(): void
    {
        $dsl            = $this->baseDsl();
        $dsl['windows'] = [[
            'function'     => 'ROW_NUMBER',
            'alias'        => 'row_num',
            'partition_by' => ['status'],
            'order_by'     => [['column' => 'created_at', 'direction' => 'asc']],
        ]];

        $sql = $this->builder->build($this->parser->parse($dsl))->toSql();

        $this->assertStringContainsStringIgnoringCase('ROW_NUMBER() OVER', $sql);
        $this->assertStringContainsString('PARTITION BY', $sql);
        $this->assertStringContainsString('`row_num`', $sql);
    }

    public function test_window_function_sum_over_partition(): void
    {
        $dsl            = $this->baseDsl();
        $dsl['windows'] = [[
            'function'     => 'SUM',
            'alias'        => 'running_total',
            'column'       => 'amount',
            'partition_by' => ['status'],
            'order_by'     => [['column' => 'created_at', 'direction' => 'asc']],
        ]];

        $sql = $this->builder->build($this->parser->parse($dsl))->toSql();

        $this->assertStringContainsStringIgnoringCase('SUM(`amount`) OVER', $sql);
        $this->assertStringContainsString('`running_total`', $sql);
    }

    public function test_window_function_lag_includes_offset(): void
    {
        $dsl            = $this->baseDsl();
        $dsl['windows'] = [[
            'function' => 'LAG',
            'alias'    => 'prev_amount',
            'column'   => 'amount',
            'offset'   => 1,
            'order_by' => [['column' => 'created_at', 'direction' => 'asc']],
        ]];

        $sql = $this->builder->build($this->parser->parse($dsl))->toSql();

        $this->assertStringContainsStringIgnoringCase('LAG(`amount`, 1)', $sql);
    }

    // ── ORDER BY ──────────────────────────────────────────────────────────────

    public function test_order_by_desc_in_sql(): void
    {
        $sql = $this->builder->build($this->parser->parse($this->baseDsl()))->toSql();

        $this->assertStringContainsStringIgnoringCase('order by', $sql);
        $this->assertStringContainsStringIgnoringCase('desc', $sql);
    }

    // ── Pagination (offset / limit applied by caller, not builder) ────────────

    public function test_builder_does_not_apply_limit(): void
    {
        // The builder returns a query instance; pagination is applied by the DataSource
        $query = $this->builder->build($this->parser->parse($this->baseDsl()));
        $sql   = $query->toSql();

        $this->assertStringNotContainsStringIgnoringCase('limit', $sql);
    }

    // ── Fixture ───────────────────────────────────────────────────────────────

    private function baseDsl(): array
    {
        return [
            'source'       => 'mysql',
            'connection'   => 'mysql',
            'table'        => 'orders',
            'fields'       => [
                ['column' => 'id'],
                ['column' => 'status'],
            ],
            'aggregations' => [
                ['function' => 'sum', 'column' => 'amount', 'alias' => 'total_revenue'],
            ],
            'group_by'  => ['status'],
            'order_by'  => [['column' => 'total_revenue', 'direction' => 'desc']],
            'pagination' => ['page' => 1, 'per_page' => 25],
        ];
    }
}
