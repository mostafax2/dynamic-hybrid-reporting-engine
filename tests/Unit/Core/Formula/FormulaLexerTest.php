<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Tests\Unit\Core\Formula;

use Mostafax\ReportingEngine\Core\Formula\FormulaLexer;
use Mostafax\ReportingEngine\Core\Formula\FormulaParseException;
use PHPUnit\Framework\TestCase;

final class FormulaLexerTest extends TestCase
{
    private FormulaLexer $lexer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lexer = new FormulaLexer();
    }

    // ── Number tokens ─────────────────────────────────────────────────────────

    public function test_tokenises_integer(): void
    {
        $tokens = $this->lexer->tokenise('42');

        $this->assertCount(1, $tokens);
        $this->assertSame('NUMBER', $tokens[0]['type']);
        $this->assertSame('42', $tokens[0]['value']);
    }

    public function test_tokenises_float(): void
    {
        $tokens = $this->lexer->tokenise('3.14');

        $this->assertSame('NUMBER', $tokens[0]['type']);
        $this->assertSame('3.14', $tokens[0]['value']);
    }

    // ── String literals ───────────────────────────────────────────────────────

    public function test_tokenises_string_literal(): void
    {
        $tokens = $this->lexer->tokenise("'hello'");

        $this->assertCount(1, $tokens);
        $this->assertSame('STRING', $tokens[0]['type']);
        $this->assertSame("'hello'", $tokens[0]['value']);
    }

    public function test_tokenises_empty_string_literal(): void
    {
        $tokens = $this->lexer->tokenise("''");

        $this->assertSame('STRING', $tokens[0]['type']);
    }

    // ── Column references ─────────────────────────────────────────────────────

    public function test_tokenises_column_ref(): void
    {
        $tokens = $this->lexer->tokenise('amount');

        $this->assertSame('COLUMN_REF', $tokens[0]['type']);
        $this->assertSame('amount', $tokens[0]['value']);
    }

    public function test_column_ref_with_underscores(): void
    {
        $tokens = $this->lexer->tokenise('total_amount');

        $this->assertSame('COLUMN_REF', $tokens[0]['type']);
    }

    // ── Function keywords ─────────────────────────────────────────────────────

    public function test_tokenises_known_function(): void
    {
        $tokens = $this->lexer->tokenise('ROUND');

        $this->assertSame('FUNCTION', $tokens[0]['type']);
        $this->assertSame('ROUND', $tokens[0]['value']);
    }

    public function test_function_recognised_case_insensitive(): void
    {
        $tokens = $this->lexer->tokenise('round');

        $this->assertSame('FUNCTION', $tokens[0]['type']);
        $this->assertSame('ROUND', $tokens[0]['value']);
    }

    public function test_all_date_functions_tokenised_as_function(): void
    {
        foreach (['DATE_FORMAT', 'DATEDIFF', 'NOW', 'DATE', 'YEAR', 'MONTH', 'DAY'] as $fn) {
            $tokens = $this->lexer->tokenise($fn);
            $this->assertSame('FUNCTION', $tokens[0]['type'], "{$fn} should be FUNCTION");
        }
    }

    public function test_all_conditional_keywords_tokenised_as_function(): void
    {
        foreach (['IF', 'COALESCE', 'NULLIF', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END'] as $kw) {
            $tokens = $this->lexer->tokenise($kw);
            $this->assertSame('FUNCTION', $tokens[0]['type'], "{$kw} should be FUNCTION");
        }
    }

    // ── Operators ─────────────────────────────────────────────────────────────

    public function test_tokenises_arithmetic_operators(): void
    {
        $tokens = $this->lexer->tokenise('a + b - c * d / e % f');

        $ops = array_values(array_filter($tokens, fn($t) => $t['type'] === 'OPERATOR'));
        $this->assertCount(5, $ops);
        $this->assertSame(['+', '-', '*', '/', '%'], array_column($ops, 'value'));
    }

    public function test_tokenises_comparison_operators(): void
    {
        foreach (['!=', '<>', '>=', '<=', '=', '>', '<'] as $op) {
            $tokens = $this->lexer->tokenise("a {$op} b");
            $comps  = array_values(array_filter($tokens, fn($t) => $t['type'] === 'COMPARISON'));
            $this->assertNotEmpty($comps, "Expected COMPARISON for {$op}");
        }
    }

    // ── AND/OR ────────────────────────────────────────────────────────────────

    public function test_and_or_tokenised_as_and_or(): void
    {
        $tokens = $this->lexer->tokenise('a AND b OR c');

        $andOr = array_values(array_filter($tokens, fn($t) => $t['type'] === 'AND_OR'));
        $this->assertCount(2, $andOr);
        $this->assertSame('AND', $andOr[0]['value']);
        $this->assertSame('OR', $andOr[1]['value']);
    }

    // ── NULL keyword ──────────────────────────────────────────────────────────

    public function test_null_keyword_tokenised(): void
    {
        $tokens = $this->lexer->tokenise('NULL');

        $this->assertSame('NULL_KW', $tokens[0]['type']);
    }

    // ── Parentheses and comma ─────────────────────────────────────────────────

    public function test_tokenises_parens_and_comma(): void
    {
        $tokens = $this->lexer->tokenise('ROUND(amount, 2)');

        $types = array_column($tokens, 'type');
        $this->assertContains('LPAREN', $types);
        $this->assertContains('RPAREN', $types);
        $this->assertContains('COMMA', $types);
    }

    // ── Complex expressions ───────────────────────────────────────────────────

    public function test_year_function_call_tokenises_correctly(): void
    {
        $tokens = $this->lexer->tokenise('YEAR(created_at)');

        $this->assertSame('FUNCTION',   $tokens[0]['type']);
        $this->assertSame('LPAREN',     $tokens[1]['type']);
        $this->assertSame('COLUMN_REF', $tokens[2]['type']);
        $this->assertSame('created_at', $tokens[2]['value']);
        $this->assertSame('RPAREN',     $tokens[3]['type']);
    }

    public function test_date_format_expression_tokenises(): void
    {
        $tokens = $this->lexer->tokenise("DATE_FORMAT(created_at, '%Y-%m')");

        $this->assertNotEmpty($tokens);
        $types = array_column($tokens, 'type');
        $this->assertContains('FUNCTION', $types);
        $this->assertContains('STRING', $types);
    }

    public function test_coalesce_with_null_fallback(): void
    {
        $tokens = $this->lexer->tokenise('COALESCE(amount, 0)');

        $this->assertNotEmpty($tokens);
        $this->assertSame('FUNCTION', $tokens[0]['type']);
    }

    // ── Security: illegal characters ─────────────────────────────────────────

    public function test_semicolon_raises_exception(): void
    {
        $this->expectException(FormulaParseException::class);

        $this->lexer->tokenise('status; DROP TABLE users');
    }

    public function test_backtick_raises_exception(): void
    {
        $this->expectException(FormulaParseException::class);

        $this->lexer->tokenise('`status`');
    }

    public function test_hash_comment_raises_exception(): void
    {
        $this->expectException(FormulaParseException::class);

        $this->lexer->tokenise('1 # comment');
    }

    public function test_union_raises_exception(): void
    {
        // The pipe character | is not in the allowlist
        $this->expectException(FormulaParseException::class);

        $this->lexer->tokenise('a | b');
    }

    public function test_exception_carries_position(): void
    {
        try {
            $this->lexer->tokenise('valid; bad');
            $this->fail('Should have thrown FormulaParseException');
        } catch (FormulaParseException $e) {
            $this->assertGreaterThanOrEqual(0, $e->position);
        }
    }

    public function test_exception_carries_original_expression(): void
    {
        $expr = 'bad;expr';
        try {
            $this->lexer->tokenise($expr);
            $this->fail('Should have thrown');
        } catch (FormulaParseException $e) {
            $this->assertSame($expr, $e->expression);
        }
    }

    // ── Whitespace handling ───────────────────────────────────────────────────

    public function test_whitespace_is_ignored(): void
    {
        $tokens = $this->lexer->tokenise("  amount  +  1  ");

        $types = array_column($tokens, 'type');
        $this->assertNotContains('WHITESPACE', $types);
        $this->assertCount(3, $tokens);
    }

    public function test_whitespace_only_expression_returns_empty_array(): void
    {
        $tokens = $this->lexer->tokenise('   ');
        $this->assertSame([], $tokens);
    }
}
