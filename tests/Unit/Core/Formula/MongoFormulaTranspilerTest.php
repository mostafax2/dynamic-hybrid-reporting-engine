<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Tests\Unit\Core\Formula;

use Mostafax\ReportingEngine\Core\Formula\FormulaParseException;
use Mostafax\ReportingEngine\Core\Formula\MongoFormulaTranspiler;
use PHPUnit\Framework\TestCase;

final class MongoFormulaTranspilerTest extends TestCase
{
    private MongoFormulaTranspiler $transpiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transpiler = new MongoFormulaTranspiler();
    }

    public function test_column_ref_becomes_field_path(): void
    {
        $this->assertSame('$amount', $this->transpiler->transpile('amount'));
    }

    public function test_addition(): void
    {
        $this->assertSame(
            ['$add' => ['$amount', 1]],
            $this->transpiler->transpile('amount + 1'),
        );
    }

    public function test_precedence_multiply_before_add(): void
    {
        $this->assertSame(
            ['$add' => ['$a', ['$multiply' => ['$b', 2]]]],
            $this->transpiler->transpile('a + b * 2'),
        );
    }

    public function test_parentheses_override_precedence(): void
    {
        $this->assertSame(
            ['$multiply' => [['$add' => ['$a', '$b']], 2]],
            $this->transpiler->transpile('(a + b) * 2'),
        );
    }

    public function test_comparison(): void
    {
        $this->assertSame(
            ['$gt' => ['$amount', 100]],
            $this->transpiler->transpile('amount > 100'),
        );
    }

    public function test_coalesce_maps_to_ifNull(): void
    {
        $this->assertSame(
            ['$ifNull' => ['$amount', 0]],
            $this->transpiler->transpile('COALESCE(amount, 0)'),
        );
    }

    public function test_unary_floor_takes_bare_arg(): void
    {
        $this->assertSame(['$floor' => '$amount'], $this->transpiler->transpile('FLOOR(amount)'));
    }

    public function test_rejects_column_outside_allowlist(): void
    {
        $this->expectException(FormulaParseException::class);
        $this->transpiler->transpile('secret + 1', ['amount']);
    }

    public function test_unsupported_function_rejected(): void
    {
        $this->expectException(FormulaParseException::class);
        $this->transpiler->transpile('NOW()');
    }

    public function test_semicolon_injection_rejected(): void
    {
        $this->expectException(FormulaParseException::class);
        $this->transpiler->transpile('1; DROP TABLE users');
    }
}
