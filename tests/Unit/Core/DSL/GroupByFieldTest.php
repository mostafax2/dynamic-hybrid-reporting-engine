<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Tests\Unit\Core\DSL;

use InvalidArgumentException;
use Mostafax\ReportingEngine\Core\DSL\GroupByField;
use PHPUnit\Framework\TestCase;

final class GroupByFieldTest extends TestCase
{
    // ── fromRaw: string shorthand ─────────────────────────────────────────────

    public function test_plain_string_creates_column_type(): void
    {
        $field = GroupByField::fromRaw('status');

        $this->assertSame('column', $field->type);
        $this->assertSame('status', $field->column);
        $this->assertNull($field->alias);
        $this->assertNull($field->granularity);
        $this->assertNull($field->expression);
    }

    public function test_plain_string_output_name_equals_column(): void
    {
        $field = GroupByField::fromRaw('region');

        $this->assertSame('region', $field->outputName());
    }

    // ── fromRaw: column object ────────────────────────────────────────────────

    public function test_column_array_without_alias(): void
    {
        $field = GroupByField::fromRaw(['type' => 'column', 'column' => 'status']);

        $this->assertSame('column', $field->type);
        $this->assertSame('status', $field->column);
        $this->assertNull($field->alias);
    }

    public function test_column_array_with_alias(): void
    {
        $field = GroupByField::fromRaw(['type' => 'column', 'column' => 'status', 'alias' => 'order_status']);

        $this->assertSame('order_status', $field->alias);
        $this->assertSame('order_status', $field->outputName());
    }

    public function test_column_array_missing_column_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GroupByField::fromRaw(['type' => 'column']);
    }

    // ── fromRaw: date_trunc ───────────────────────────────────────────────────

    public function test_date_trunc_parses_all_granularities(): void
    {
        foreach (GroupByField::GRANULARITIES as $granularity) {
            $field = GroupByField::fromRaw([
                'type'        => 'date_trunc',
                'column'      => 'created_at',
                'granularity' => $granularity,
                'alias'       => "created_{$granularity}",
            ]);

            $this->assertSame('date_trunc', $field->type);
            $this->assertSame($granularity, $field->granularity);
            $this->assertSame('created_at', $field->column);
        }
    }

    public function test_date_trunc_default_granularity_is_month(): void
    {
        $field = GroupByField::fromRaw([
            'type'   => 'date_trunc',
            'column' => 'created_at',
        ]);

        $this->assertSame('month', $field->granularity);
    }

    public function test_date_trunc_auto_alias_when_none_provided(): void
    {
        $field = GroupByField::fromRaw([
            'type'        => 'date_trunc',
            'column'      => 'created_at',
            'granularity' => 'year',
        ]);

        $this->assertSame('created_at_year', $field->alias);
        $this->assertSame('created_at_year', $field->outputName());
    }

    public function test_date_trunc_invalid_granularity_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/granularity must be one of/');

        GroupByField::fromRaw([
            'type'        => 'date_trunc',
            'column'      => 'created_at',
            'granularity' => 'century',
        ]);
    }

    public function test_date_trunc_missing_column_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GroupByField::fromRaw(['type' => 'date_trunc', 'granularity' => 'month']);
    }

    // ── fromRaw: expression ───────────────────────────────────────────────────

    public function test_expression_type_stores_expression_and_alias(): void
    {
        $field = GroupByField::fromRaw([
            'type'       => 'expression',
            'expression' => 'YEAR(created_at)',
            'alias'      => 'order_year',
        ]);

        $this->assertSame('expression', $field->type);
        $this->assertSame('YEAR(created_at)', $field->expression);
        $this->assertSame('order_year', $field->alias);
        $this->assertSame('order_year', $field->outputName());
    }

    public function test_expression_missing_alias_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GroupByField::fromRaw(['type' => 'expression', 'expression' => 'YEAR(created_at)']);
    }

    public function test_expression_missing_expression_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GroupByField::fromRaw(['type' => 'expression', 'alias' => 'order_year']);
    }

    // ── type-case insensitivity ───────────────────────────────────────────────

    public function test_fromRaw_type_is_case_insensitive(): void
    {
        $field = GroupByField::fromRaw([
            'type'   => 'COLUMN',
            'column' => 'status',
        ]);

        $this->assertSame('column', $field->type);
    }

    public function test_fromRaw_unknown_type_falls_through_to_column(): void
    {
        $field = GroupByField::fromRaw([
            'type'   => 'weird_type',
            'column' => 'status',
        ]);

        // Default branch in fromRaw makes it a column type
        $this->assertSame('column', $field->type);
        $this->assertSame('status', $field->column);
    }

    // ── outputName precedence ─────────────────────────────────────────────────

    public function test_output_name_prefers_alias_over_column(): void
    {
        $field = new GroupByField(type: 'column', column: 'status', alias: 'order_status');
        $this->assertSame('order_status', $field->outputName());
    }

    public function test_output_name_falls_back_to_column_when_no_alias(): void
    {
        $field = new GroupByField(type: 'column', column: 'status');
        $this->assertSame('status', $field->outputName());
    }

    public function test_granularities_constant_has_all_six_levels(): void
    {
        $this->assertCount(6, GroupByField::GRANULARITIES);
        $this->assertContains('year', GroupByField::GRANULARITIES);
        $this->assertContains('quarter', GroupByField::GRANULARITIES);
        $this->assertContains('month', GroupByField::GRANULARITIES);
        $this->assertContains('week', GroupByField::GRANULARITIES);
        $this->assertContains('day', GroupByField::GRANULARITIES);
        $this->assertContains('hour', GroupByField::GRANULARITIES);
    }
}
