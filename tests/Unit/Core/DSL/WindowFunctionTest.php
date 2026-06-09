<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Tests\Unit\Core\DSL;

use InvalidArgumentException;
use Mostafax\ReportingEngine\Core\DSL\WindowFunction;
use PHPUnit\Framework\TestCase;

final class WindowFunctionTest extends TestCase
{
    // ── fromArray: ranking functions ──────────────────────────────────────────

    public function test_row_number_is_ranking(): void
    {
        $wf = WindowFunction::fromArray([
            'function' => 'ROW_NUMBER',
            'alias'    => 'row_num',
        ]);

        $this->assertSame('ROW_NUMBER', $wf->function);
        $this->assertTrue($wf->isRanking());
        $this->assertFalse($wf->isLagLead());
        $this->assertFalse($wf->isBucket());
    }

    public function test_rank_dense_rank_percent_rank_cume_dist_are_ranking(): void
    {
        foreach (['RANK', 'DENSE_RANK', 'PERCENT_RANK', 'CUME_DIST'] as $fn) {
            $wf = WindowFunction::fromArray(['function' => $fn, 'alias' => 'x']);
            $this->assertTrue($wf->isRanking(), "{$fn} should be a ranking function");
        }
    }

    // ── fromArray: value functions ────────────────────────────────────────────

    public function test_sum_over_with_partition_and_order(): void
    {
        $wf = WindowFunction::fromArray([
            'function'     => 'SUM',
            'alias'        => 'running_total',
            'column'       => 'amount',
            'partition_by' => ['status', 'region'],
            'order_by'     => [['column' => 'created_at', 'direction' => 'asc']],
        ]);

        $this->assertSame('SUM', $wf->function);
        $this->assertSame('amount', $wf->column);
        $this->assertSame(['status', 'region'], $wf->partitionBy);
        $this->assertCount(1, $wf->orderBy);
        $this->assertSame('created_at', $wf->orderBy[0]->column);
        $this->assertFalse($wf->isRanking());
    }

    // ── fromArray: LAG / LEAD ─────────────────────────────────────────────────

    public function test_lag_is_lag_lead(): void
    {
        $wf = WindowFunction::fromArray([
            'function' => 'LAG',
            'alias'    => 'prev_amount',
            'column'   => 'amount',
            'offset'   => 2,
            'default'  => 0,
        ]);

        $this->assertTrue($wf->isLagLead());
        $this->assertFalse($wf->isRanking());
        $this->assertSame(2, $wf->offset);
        $this->assertSame(0, $wf->default);
    }

    public function test_lag_default_offset_is_1(): void
    {
        $wf = WindowFunction::fromArray([
            'function' => 'LEAD',
            'alias'    => 'next_val',
            'column'   => 'amount',
        ]);

        $this->assertSame(1, $wf->offset);
        $this->assertNull($wf->default);
    }

    // ── fromArray: NTILE bucket ───────────────────────────────────────────────

    public function test_ntile_is_bucket(): void
    {
        $wf = WindowFunction::fromArray([
            'function' => 'NTILE',
            'alias'    => 'quartile',
            'ntile'    => 4,
        ]);

        $this->assertTrue($wf->isBucket());
        $this->assertSame(4, $wf->ntile);
    }

    // ── function name normalisation ───────────────────────────────────────────

    public function test_function_name_is_uppercased(): void
    {
        $wf = WindowFunction::fromArray(['function' => 'row_number', 'alias' => 'rn']);

        $this->assertSame('ROW_NUMBER', $wf->function);
    }

    // ── validation ────────────────────────────────────────────────────────────

    public function test_unknown_function_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown window function/');

        WindowFunction::fromArray(['function' => 'EXPLODE', 'alias' => 'x']);
    }

    public function test_missing_function_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WindowFunction::fromArray(['alias' => 'rn']);
    }

    public function test_missing_alias_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WindowFunction::fromArray(['function' => 'ROW_NUMBER']);
    }

    // ── allowed function coverage ─────────────────────────────────────────────

    public function test_all_ranking_functions_are_allowed(): void
    {
        foreach (WindowFunction::RANKING_FUNCTIONS as $fn) {
            $wf = WindowFunction::fromArray(['function' => $fn, 'alias' => 'x']);
            $this->assertSame($fn, $wf->function);
        }
    }

    public function test_all_value_functions_are_allowed(): void
    {
        foreach (WindowFunction::VALUE_FUNCTIONS as $fn) {
            $wf = WindowFunction::fromArray(['function' => $fn, 'alias' => 'x', 'column' => 'amount']);
            $this->assertSame($fn, $wf->function);
        }
    }

    public function test_order_by_accepts_string_shorthand(): void
    {
        $wf = WindowFunction::fromArray([
            'function' => 'SUM',
            'alias'    => 'rs',
            'column'   => 'amount',
            'order_by' => ['created_at'],
        ]);

        $this->assertCount(1, $wf->orderBy);
        $this->assertSame('created_at', $wf->orderBy[0]->column);
        $this->assertSame('asc', $wf->orderBy[0]->direction);
    }

    public function test_partition_by_accepts_snake_case_key(): void
    {
        $wf = WindowFunction::fromArray([
            'function'    => 'ROW_NUMBER',
            'alias'       => 'rn',
            'partitionBy' => ['status'],
        ]);

        $this->assertSame(['status'], $wf->partitionBy);
    }
}
