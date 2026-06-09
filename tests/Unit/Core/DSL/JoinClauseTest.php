<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Tests\Unit\Core\DSL;

use Mostafax\ReportingEngine\Core\DSL\JoinClause;
use PHPUnit\Framework\TestCase;

final class JoinClauseTest extends TestCase
{
    public function test_from_array_minimal(): void
    {
        $join = JoinClause::fromArray(['table' => 'orders', 'first' => 'orders.user_id']);

        $this->assertSame('inner', $join->type);
        $this->assertSame('orders', $join->table);
        $this->assertSame('orders.user_id', $join->first);
        $this->assertSame('=', $join->operator);
        $this->assertNull($join->second);
        $this->assertNull($join->alias);
    }

    public function test_from_array_with_alias(): void
    {
        $join = JoinClause::fromArray([
            'type'   => 'left',
            'table'  => 'order_items',
            'alias'  => 'oi',
            'first'  => 'oi.order_id',
            'second' => 'orders.id',
        ]);

        $this->assertSame('left', $join->type);
        $this->assertSame('oi', $join->alias);
        $this->assertSame('orders.id', $join->second);
    }

    public function test_unknown_type_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        JoinClause::fromArray(['type' => 'sideways', 'table' => 't', 'first' => 'a']);
    }

    public function test_missing_table_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        JoinClause::fromArray(['first' => 'a']);
    }
}
