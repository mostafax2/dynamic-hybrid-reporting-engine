<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Tests\Feature;

use Mostafax\ReportingEngine\Core\DSL\DslParser;
use Mostafax\ReportingEngine\Core\DSL\QueryDefinition;
use Mostafax\ReportingEngine\Core\Validation\DslValidationException;
use Mostafax\ReportingEngine\Core\Validation\QueryValidator;
use Mostafax\ReportingEngine\Infrastructure\Builders\MySQLQueryBuilder;
use Mostafax\ReportingEngine\Infrastructure\Builders\MongoAggregationBuilder;
use Mostafax\ReportingEngine\Infrastructure\Security\FieldAccessControl;
use Mostafax\ReportingEngine\Infrastructure\Security\QuerySanitizer;
use PHPUnit\Framework\TestCase;

final class ReportEngineTest extends TestCase
{
    private DslParser $parser;
    private QuerySanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser    = new DslParser();
        $this->sanitizer = new QuerySanitizer();
    }

    // ── DSL Parsing ───────────────────────────────────────────────

    public function test_parser_produces_query_definition_from_array(): void
    {
        $definition = $this->parser->parse($this->mysqlDsl());

        $this->assertInstanceOf(QueryDefinition::class, $definition);
        $this->assertSame('mysql', $definition->source);
        $this->assertSame('orders', $definition->table);
        $this->assertCount(2, $definition->fields);
        $this->assertCount(2, $definition->aggregations);
        $this->assertSame(['status'], $definition->groupBy);
        $this->assertSame(1, $definition->pagination->page);
        $this->assertSame(25, $definition->pagination->perPage);
    }

    public function test_parser_produces_query_definition_from_json(): void
    {
        $definition = $this->parser->parse(json_encode($this->mysqlDsl()));

        $this->assertInstanceOf(QueryDefinition::class, $definition);
        $this->assertSame('mysql', $definition->source);
    }

    public function test_parser_throws_on_missing_source(): void
    {
        $this->expectException(DslValidationException::class);
        $this->parser->parse(['table' => 'orders']);
    }

    public function test_parser_throws_on_missing_table(): void
    {
        $this->expectException(DslValidationException::class);
        $this->parser->parse(['source' => 'mysql']);
    }

    public function test_parser_throws_on_invalid_json(): void
    {
        $this->expectException(DslValidationException::class);
        $this->parser->parse('{ not valid json }');
    }

    public function test_aggregation_function_is_normalised_to_lowercase(): void
    {
        $dsl = $this->mysqlDsl();
        $dsl['aggregations'][0]['function'] = 'SUM';
        $definition = $this->parser->parse($dsl);

        $this->assertSame('sum', $definition->aggregations[0]->function);
    }

    // ── Pagination offset ─────────────────────────────────────────

    public function test_pagination_calculates_correct_offset(): void
    {
        $dsl = $this->mysqlDsl();
        $dsl['pagination'] = ['page' => 3, 'per_page' => 10];
        $definition        = $this->parser->parse($dsl);

        $this->assertSame(20, $definition->pagination->offset);
    }

    // ── DSL hash stability ────────────────────────────────────────

    public function test_same_dsl_produces_same_hash(): void
    {
        $a = $this->parser->parse($this->mysqlDsl());
        $b = $this->parser->parse($this->mysqlDsl());

        $this->assertSame($a->hash(), $b->hash());
    }

    public function test_different_dsl_produces_different_hash(): void
    {
        $dsl1 = $this->mysqlDsl();
        $dsl2 = $this->mysqlDsl();
        $dsl2['table'] = 'invoices';

        $a = $this->parser->parse($dsl1);
        $b = $this->parser->parse($dsl2);

        $this->assertNotSame($a->hash(), $b->hash());
    }

    // ── Sanitizer ─────────────────────────────────────────────────

    public function test_sanitizer_rejects_sql_injection_in_table_name(): void
    {
        $this->expectException(DslValidationException::class);
        $this->expectExceptionMessageMatches('/unsafe identifier/');

        $dsl = $this->mysqlDsl();
        $dsl['table'] = "orders; DROP TABLE users--";
        $definition   = $this->parser->parse($dsl);
        $this->sanitizer->sanitize($definition);
    }

    public function test_sanitizer_rejects_injection_in_column_name(): void
    {
        $this->expectException(DslValidationException::class);

        $dsl = $this->mysqlDsl();
        $dsl['fields'][] = ['column' => '1=1; --'];
        $definition      = $this->parser->parse($dsl);
        $this->sanitizer->sanitize($definition);
    }

    public function test_sanitizer_allows_dot_notation_column(): void
    {
        $dsl = $this->mysqlDsl();
        $dsl['fields'][] = ['column' => 'orders.customer_id'];
        $definition      = $this->parser->parse($dsl);

        // Must not throw
        $sanitized = $this->sanitizer->sanitize($definition);
        $this->assertNotNull($sanitized);
    }

    // ── Field ACL ─────────────────────────────────────────────────

    public function test_acl_strips_globally_denied_fields(): void
    {
        $acl = new FieldAccessControl([
            'always_deny' => ['password', 'api_key'],
        ]);

        $dsl = $this->mysqlDsl();
        $dsl['fields'][] = ['column' => 'password'];
        $dsl['fields'][] = ['column' => 'api_key'];
        $definition      = $this->parser->parse($dsl);

        $filtered = $acl->apply($definition);

        $columns = array_map(fn($f) => $f->column, $filtered->fields);
        $this->assertNotContains('password', $columns);
        $this->assertNotContains('api_key', $columns);
    }

    public function test_acl_throws_when_denied_field_used_in_filter(): void
    {
        $this->expectException(DslValidationException::class);
        $this->expectExceptionMessageMatches('/Access denied/');

        $acl = new FieldAccessControl(['always_deny' => ['password']]);
        $dsl = $this->mysqlDsl();
        $dsl['filters'] = [
            'operator'   => 'AND',
            'conditions' => [
                ['field' => 'password', 'operator' => '=', 'value' => 'secret'],
            ],
        ];

        $definition = $this->parser->parse($dsl);
        $acl->apply($definition);
    }

    // ── MongoDB pipeline builder ──────────────────────────────────

    public function test_mongo_builder_produces_facet_pipeline(): void
    {
        $builder    = new MongoAggregationBuilder();
        $definition = $this->parser->parse($this->mongoDsl());
        $pipeline   = $builder->build($definition);

        // Must end with $facet stage
        $lastStage = end($pipeline);
        $this->assertArrayHasKey('$facet', $lastStage);
        $this->assertArrayHasKey('data', $lastStage['$facet']);
        $this->assertArrayHasKey('totalCount', $lastStage['$facet']);
    }

    public function test_mongo_builder_includes_match_stage_for_filters(): void
    {
        $builder = new MongoAggregationBuilder();
        $dsl     = $this->mongoDsl();
        $dsl['filters'] = [
            'operator'   => 'AND',
            'conditions' => [['field' => 'status', 'operator' => '=', 'value' => 'active']],
        ];

        $definition = $this->parser->parse($dsl);
        $pipeline   = $builder->build($definition);

        $firstStage = $pipeline[0];
        $this->assertArrayHasKey('$match', $firstStage);
        $this->assertSame('active', $firstStage['$match']['status']);
    }

    // ── MySQL query builder ───────────────────────────────────────

    public function test_query_definition_detects_aggregation_intent(): void
    {
        $definition = $this->parser->parse($this->mysqlDsl());
        $this->assertTrue($definition->isAggregation());
    }

    public function test_query_definition_without_aggregation_is_plain_query(): void
    {
        $dsl                 = $this->mysqlDsl();
        $dsl['aggregations'] = [];
        $dsl['group_by']     = [];
        $definition          = $this->parser->parse($dsl);

        $this->assertFalse($definition->isAggregation());
    }

    // ── Fixtures ─────────────────────────────────────────────────

    private function mysqlDsl(): array
    {
        return [
            'source'       => 'mysql',
            'connection'   => 'mysql',
            'table'        => 'orders',
            'fields'       => [
                ['column' => 'id',            'alias' => 'order_id'],
                ['column' => 'customer_name'],
            ],
            'aggregations' => [
                ['function' => 'sum',   'column' => 'total_amount', 'alias' => 'total_revenue'],
                ['function' => 'count', 'column' => 'id',           'alias' => 'order_count'],
            ],
            'filters'      => [
                'operator'   => 'AND',
                'conditions' => [
                    ['field' => 'status', 'operator' => '=', 'value' => 'completed'],
                    [
                        'operator'   => 'OR',
                        'conditions' => [
                            ['field' => 'total_amount', 'operator' => '>',  'value' => 100],
                            ['field' => 'is_premium',   'operator' => '=',  'value' => true],
                        ],
                    ],
                ],
            ],
            'group_by'     => ['status'],
            'order_by'     => [
                ['column' => 'total_revenue', 'direction' => 'desc'],
            ],
            'pagination'   => ['page' => 1, 'per_page' => 25],
        ];
    }

    private function mongoDsl(): array
    {
        return [
            'source'       => 'mongodb',
            'connection'   => 'mongodb',
            'table'        => 'analytics',
            'fields'       => [
                ['column' => 'userId'],
                ['column' => 'event'],
            ],
            'aggregations' => [
                ['function' => 'sum',   'column' => 'revenue',    'alias' => 'total_revenue'],
                ['function' => 'count', 'column' => '_id',        'alias' => 'event_count'],
            ],
            'group_by'     => ['userId'],
            'order_by'     => [['column' => 'total_revenue', 'direction' => 'desc']],
            'pagination'   => ['page' => 1, 'per_page' => 50],
        ];
    }
}
