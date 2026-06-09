<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Tests\Unit\Infrastructure\Security;

use Mostafax\ReportingEngine\Core\DSL\DslParser;
use Mostafax\ReportingEngine\Core\Validation\DslValidationException;
use Mostafax\ReportingEngine\Infrastructure\Security\QuerySanitizer;
use PHPUnit\Framework\TestCase;

final class QuerySanitizerTest extends TestCase
{
    private DslParser     $parser;
    private QuerySanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser    = new DslParser();
        $this->sanitizer = new QuerySanitizer();
    }

    // ── Table / field identifiers ─────────────────────────────────────────────

    public function test_valid_dsl_passes_without_exception(): void
    {
        $definition = $this->parser->parse($this->baseDsl());
        $result     = $this->sanitizer->sanitize($definition);

        $this->assertNotNull($result);
    }

    public function test_rejects_sql_injection_in_table(): void
    {
        $this->expectException(DslValidationException::class);
        $this->expectExceptionMessageMatches('/unsafe identifier.*table/');

        $dsl          = $this->baseDsl();
        $dsl['table'] = "users; DROP TABLE--";
        $this->sanitizer->sanitize($this->parser->parse($dsl));
    }

    public function test_rejects_injection_in_field_column(): void
    {
        $this->expectException(DslValidationException::class);

        $dsl             = $this->baseDsl();
        $dsl['fields'][] = ['column' => "1=1--"];
        $this->sanitizer->sanitize($this->parser->parse($dsl));
    }

    public function test_dot_notation_column_is_allowed(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['fields'][] = ['column' => 'orders.customer_id'];
        $result          = $this->sanitizer->sanitize($this->parser->parse($dsl));

        $this->assertNotNull($result);
    }

    // ── group_by: column type ────────────────────────────────────────────────

    public function test_plain_string_group_by_passes(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['group_by'] = ['status'];
        $result          = $this->sanitizer->sanitize($this->parser->parse($dsl));

        $this->assertNotNull($result);
    }

    public function test_column_type_group_by_passes(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [['type' => 'column', 'column' => 'status']];
        $result          = $this->sanitizer->sanitize($this->parser->parse($dsl));

        $this->assertNotNull($result);
    }

    public function test_column_type_with_injection_fails(): void
    {
        $this->expectException(DslValidationException::class);
        $this->expectExceptionMessageMatches('/unsafe identifier.*group_by/');

        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [['type' => 'column', 'column' => "status; DROP TABLE--"]];
        $this->sanitizer->sanitize($this->parser->parse($dsl));
    }

    public function test_column_alias_injection_fails(): void
    {
        $this->expectException(DslValidationException::class);

        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [['type' => 'column', 'column' => 'status', 'alias' => "bad--alias"]];
        $this->sanitizer->sanitize($this->parser->parse($dsl));
    }

    // ── group_by: date_trunc type ────────────────────────────────────────────

    public function test_date_trunc_group_by_passes(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [[
            'type'        => 'date_trunc',
            'column'      => 'created_at',
            'granularity' => 'month',
            'alias'       => 'created_month',
        ]];
        $result = $this->sanitizer->sanitize($this->parser->parse($dsl));

        $this->assertNotNull($result);
    }

    public function test_date_trunc_with_injection_in_column_fails(): void
    {
        $this->expectException(DslValidationException::class);

        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [[
            'type'        => 'date_trunc',
            'column'      => "created_at); DROP TABLE--",
            'granularity' => 'month',
        ]];
        // DslParser will throw on invalid granularity or GroupByField will catch it at parse time
        // We verify sanitizer also rejects it if it gets through
        try {
            $this->sanitizer->sanitize($this->parser->parse($dsl));
        } catch (\InvalidArgumentException) {
            // acceptable: caught at parse time
            $this->addToAssertionCount(1);
            return;
        }
        $this->fail('Expected exception was not thrown');
    }

    // ── group_by: expression type ────────────────────────────────────────────

    public function test_expression_type_with_safe_formula_passes(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [[
            'type'       => 'expression',
            'expression' => 'YEAR(created_at)',
            'alias'      => 'order_year',
        ]];
        $result = $this->sanitizer->sanitize($this->parser->parse($dsl));

        $this->assertNotNull($result);
    }

    public function test_expression_type_with_illegal_character_fails(): void
    {
        $this->expectException(DslValidationException::class);
        $this->expectExceptionMessageMatches('/Security.*group_by expression/');

        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [[
            'type'       => 'expression',
            'expression' => 'status; DROP TABLE users',
            'alias'      => 'hacked',
        ]];
        $this->sanitizer->sanitize($this->parser->parse($dsl));
    }

    public function test_expression_type_date_format_passes(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [[
            'type'       => 'expression',
            'expression' => "DATE_FORMAT(created_at, '%Y-%m')",
            'alias'      => 'month',
        ]];
        $result = $this->sanitizer->sanitize($this->parser->parse($dsl));

        $this->assertNotNull($result);
    }

    // ── HAVING validation ─────────────────────────────────────────────────────

    public function test_having_with_safe_aggregation_alias_passes(): void
    {
        $dsl           = $this->baseDsl();
        $dsl['having'] = [
            'operator'   => 'AND',
            'conditions' => [
                ['field' => 'total_revenue', 'operator' => '>', 'value' => 1000],
            ],
        ];
        $result = $this->sanitizer->sanitize($this->parser->parse($dsl));

        $this->assertNotNull($result);
    }

    public function test_having_with_injection_in_field_fails(): void
    {
        $this->expectException(DslValidationException::class);
        $this->expectExceptionMessageMatches('/unsafe identifier.*having/');

        $dsl           = $this->baseDsl();
        $dsl['having'] = [
            'operator'   => 'AND',
            'conditions' => [
                ['field' => "total; DROP--", 'operator' => '>', 'value' => 100],
            ],
        ];
        $this->sanitizer->sanitize($this->parser->parse($dsl));
    }

    // ── Window function validation ────────────────────────────────────────────

    public function test_window_function_with_safe_identifiers_passes(): void
    {
        $dsl            = $this->baseDsl();
        $dsl['windows'] = [[
            'function'     => 'ROW_NUMBER',
            'alias'        => 'row_num',
            'partition_by' => ['status'],
            'order_by'     => [['column' => 'created_at', 'direction' => 'asc']],
        ]];
        $result = $this->sanitizer->sanitize($this->parser->parse($dsl));

        $this->assertNotNull($result);
    }

    public function test_window_function_injection_in_partition_by_fails(): void
    {
        $this->expectException(DslValidationException::class);
        $this->expectExceptionMessageMatches('/unsafe identifier.*windows/');

        $dsl            = $this->baseDsl();
        $dsl['windows'] = [[
            'function'     => 'ROW_NUMBER',
            'alias'        => 'rn',
            'partition_by' => ["status; DROP--"],
        ]];
        $this->sanitizer->sanitize($this->parser->parse($dsl));
    }

    public function test_window_function_injection_in_alias_fails(): void
    {
        $this->expectException(DslValidationException::class);

        $dsl            = $this->baseDsl();
        $dsl['windows'] = [[
            'function' => 'ROW_NUMBER',
            'alias'    => "bad--alias",
        ]];
        $this->sanitizer->sanitize($this->parser->parse($dsl));
    }

    // ── Filter validation ─────────────────────────────────────────────────────

    public function test_filter_injection_in_field_fails(): void
    {
        $this->expectException(DslValidationException::class);

        $dsl              = $this->baseDsl();
        $dsl['filters']   = [
            'operator'   => 'AND',
            'conditions' => [
                ['field' => "status; DROP--", 'operator' => '=', 'value' => 'active'],
            ],
        ];
        $this->sanitizer->sanitize($this->parser->parse($dsl));
    }

    // ── Computed fields ───────────────────────────────────────────────────────

    public function test_computed_field_with_safe_expression_passes(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['computed'] = [
            ['alias' => 'revenue_rounded', 'expression' => 'ROUND(amount, 2)'],
        ];
        $result = $this->sanitizer->sanitize($this->parser->parse($dsl));

        $this->assertNotNull($result);
    }

    public function test_computed_field_with_illegal_expression_fails(): void
    {
        $this->expectException(DslValidationException::class);
        $this->expectExceptionMessageMatches('/invalid formula/');

        $dsl             = $this->baseDsl();
        $dsl['computed'] = [
            ['alias' => 'hacked', 'expression' => 'amount; DROP TABLE users'],
        ];
        $this->sanitizer->sanitize($this->parser->parse($dsl));
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
            'group_by' => ['status'],
        ];
    }
}
