<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Tests\Unit\Core\Formula;

use Mostafax\ReportingEngine\Core\Formula\FormulaParseException;
use Mostafax\ReportingEngine\Core\Formula\MySQLFormulaTranspiler;
use PHPUnit\Framework\TestCase;

final class MySQLFormulaTranspilerTest extends TestCase
{
    private MySQLFormulaTranspiler $transpiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transpiler = new MySQLFormulaTranspiler();
    }

    public function test_quotes_column_references(): void
    {
        $this->assertSame('`amount` + 1', $this->transpiler->transpile('amount + 1'));
    }

    public function test_arithmetic_chain(): void
    {
        $this->assertSame(
            '`price` * `qty` - `discount`',
            $this->transpiler->transpile('price * qty - discount'),
        );
    }

    public function test_function_call_passthrough(): void
    {
        $this->assertSame(
            'ROUND(`amount`, 2)',
            $this->transpiler->transpile('ROUND(amount, 2)'),
        );
    }

    public function test_string_literal_is_reescaped(): void
    {
        $this->assertSame("CONCAT(`a`, 'x')", $this->transpiler->transpile("CONCAT(a, 'x')"));
    }

    public function test_rejects_column_outside_allowlist(): void
    {
        $this->expectException(FormulaParseException::class);
        $this->transpiler->transpile('secret + 1', ['amount', 'qty']);
    }

    public function test_allows_column_inside_allowlist(): void
    {
        $this->assertSame('`amount` + 1', $this->transpiler->transpile('amount + 1', ['amount']));
    }

    // ── Injection guards (inherited from the lexer) ──────────────────────────

    public function test_semicolon_injection_rejected(): void
    {
        $this->expectException(FormulaParseException::class);
        $this->transpiler->transpile('1; DROP TABLE users');
    }

    public function test_backtick_injection_rejected(): void
    {
        $this->expectException(FormulaParseException::class);
        $this->transpiler->transpile('`amount`');
    }

    public function test_comment_injection_rejected(): void
    {
        $this->expectException(FormulaParseException::class);
        $this->transpiler->transpile('amount # comment');
    }
}
