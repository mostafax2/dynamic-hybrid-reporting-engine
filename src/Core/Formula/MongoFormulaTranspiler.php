<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\Formula;

/**
 * Transpiles a computed-field formula into a MongoDB aggregation expression.
 *
 * Security: consumes ONLY the lexer's allowlisted token stream, then builds a
 * structured Mongo expression tree (no string concatenation into a query).
 * Column refs become "$field" path references, validated against
 * $allowedColumns when provided. Unsupported constructs are rejected rather
 * than emitted, so an unrecognised formula fails closed.
 *
 * Grammar (precedence low→high):
 *   or      := and ( OR and )*
 *   and     := comparison ( AND comparison )*
 *   comparison := additive ( COMPARISON additive )?
 *   additive   := term ( (+|-) term )*
 *   term       := unary ( (*|/|%) unary )*
 *   unary      := (-)? primary
 *   primary    := NUMBER | STRING | NULL | COLUMN_REF | FUNCTION(args) | ( or )
 */
final class MongoFormulaTranspiler
{
    private const ARITH = [
        '+' => '$add', '-' => '$subtract', '*' => '$multiply',
        '/' => '$divide', '%' => '$mod',
    ];

    private const COMPARE = [
        '=' => '$eq', '!=' => '$ne', '<>' => '$ne',
        '>' => '$gt', '>=' => '$gte', '<' => '$lt', '<=' => '$lte',
    ];

    /** Lexer FUNCTION keyword → Mongo aggregation operator. */
    private const FUNCTIONS = [
        'ROUND' => '$round', 'FLOOR' => '$floor', 'CEIL' => '$ceil', 'CEILING' => '$ceil',
        'ABS' => '$abs', 'MOD' => '$mod', 'POWER' => '$pow', 'SQRT' => '$sqrt',
        'COALESCE' => '$ifNull', 'CONCAT' => '$concat', 'LENGTH' => '$strLenCP',
        'LOWER' => '$toLower', 'UPPER' => '$toUpper', 'YEAR' => '$year',
        'MONTH' => '$month', 'DAY' => '$dayOfMonth',
    ];

    /** @var array<int, array{type: string, value: string}> */
    private array $tokens = [];
    private int $pos = 0;
    private string $expression = '';
    /** @var array<string,int> */
    private array $allowed = [];

    public function __construct(
        private readonly FormulaLexer $lexer = new FormulaLexer(),
    ) {}

    /**
     * @param  string[] $allowedColumns
     * @return mixed  a Mongo aggregation expression (array | scalar | "$field")
     * @throws FormulaParseException
     */
    public function transpile(string $expression, array $allowedColumns = []): mixed
    {
        $this->tokens     = $this->lexer->tokenise($expression);
        $this->pos        = 0;
        $this->expression = $expression;
        $this->allowed    = array_flip($allowedColumns);

        if ($this->tokens === []) {
            throw new FormulaParseException('Empty formula expression', 0, $expression);
        }

        $expr = $this->parseOr();

        if ($this->pos < count($this->tokens)) {
            throw new FormulaParseException(
                "Unexpected trailing token '{$this->tokens[$this->pos]['value']}'",
                $this->pos,
                $expression,
            );
        }

        return $expr;
    }

    private function parseOr(): mixed
    {
        $left = $this->parseAnd();
        $terms = [$left];
        while ($this->isValue('AND_OR', 'OR')) {
            $this->pos++;
            $terms[] = $this->parseAnd();
        }
        return count($terms) === 1 ? $left : ['$or' => $terms];
    }

    private function parseAnd(): mixed
    {
        $left = $this->parseComparison();
        $terms = [$left];
        while ($this->isValue('AND_OR', 'AND')) {
            $this->pos++;
            $terms[] = $this->parseComparison();
        }
        return count($terms) === 1 ? $left : ['$and' => $terms];
    }

    private function parseComparison(): mixed
    {
        $left = $this->parseAdditive();
        if ($this->isType('COMPARISON')) {
            $op = $this->tokens[$this->pos]['value'];
            $this->pos++;
            $right = $this->parseAdditive();
            return [self::COMPARE[$op] => [$left, $right]];
        }
        return $left;
    }

    private function parseAdditive(): mixed
    {
        $left = $this->parseTerm();
        while ($this->isType('OPERATOR') && in_array($this->tokens[$this->pos]['value'], ['+', '-'], true)) {
            $op = $this->tokens[$this->pos]['value'];
            $this->pos++;
            $right = $this->parseTerm();
            $left  = [self::ARITH[$op] => [$left, $right]];
        }
        return $left;
    }

    private function parseTerm(): mixed
    {
        $left = $this->parseUnary();
        while ($this->isType('OPERATOR') && in_array($this->tokens[$this->pos]['value'], ['*', '/', '%'], true)) {
            $op = $this->tokens[$this->pos]['value'];
            $this->pos++;
            $right = $this->parseUnary();
            $left  = [self::ARITH[$op] => [$left, $right]];
        }
        return $left;
    }

    private function parseUnary(): mixed
    {
        if ($this->isValue('OPERATOR', '-')) {
            $this->pos++;
            return ['$subtract' => [0, $this->parseUnary()]];
        }
        return $this->parsePrimary();
    }

    private function parsePrimary(): mixed
    {
        $token = $this->tokens[$this->pos]
            ?? throw new FormulaParseException('Unexpected end of formula', $this->pos, $this->expression);

        switch ($token['type']) {
            case 'NUMBER':
                $this->pos++;
                return str_contains($token['value'], '.')
                    ? (float) $token['value']
                    : (int) $token['value'];

            case 'STRING':
                $this->pos++;
                return str_replace("''", "'", substr($token['value'], 1, -1));

            case 'NULL_KW':
                $this->pos++;
                return null;

            case 'COLUMN_REF':
                $this->pos++;
                if ($this->allowed !== [] && !isset($this->allowed[$token['value']])) {
                    throw new FormulaParseException(
                        "Unknown column '{$token['value']}' in formula",
                        $this->pos,
                        $this->expression,
                    );
                }
                return '$' . $token['value'];

            case 'FUNCTION':
                return $this->parseFunctionCall($token['value']);

            case 'LPAREN':
                $this->pos++;
                $inner = $this->parseOr();
                $this->expect('RPAREN');
                return $inner;
        }

        throw new FormulaParseException(
            "Unexpected token '{$token['value']}' in Mongo formula",
            $this->pos,
            $this->expression,
        );
    }

    private function parseFunctionCall(string $fn): mixed
    {
        if (!isset(self::FUNCTIONS[$fn])) {
            throw new FormulaParseException(
                "Function '{$fn}' is not supported by the Mongo transpiler",
                $this->pos,
                $this->expression,
            );
        }
        $this->pos++;
        $this->expect('LPAREN');

        $args = [];
        if (!$this->isType('RPAREN')) {
            $args[] = $this->parseOr();
            while ($this->isType('COMMA')) {
                $this->pos++;
                $args[] = $this->parseOr();
            }
        }
        $this->expect('RPAREN');

        $op = self::FUNCTIONS[$fn];
        // Single-arg unary operators take the bare expression; the rest take an array.
        return match ($op) {
            '$floor', '$ceil', '$abs', '$sqrt', '$toLower', '$toUpper', '$strLenCP',
            '$year', '$month', '$dayOfMonth' => [$op => $args[0] ?? null],
            default                          => [$op => $args],
        };
    }

    private function isType(string $type): bool
    {
        return ($this->tokens[$this->pos]['type'] ?? null) === $type;
    }

    private function isValue(string $type, string $value): bool
    {
        $t = $this->tokens[$this->pos] ?? null;
        return $t !== null && $t['type'] === $type && $t['value'] === $value;
    }

    private function expect(string $type): void
    {
        if (!$this->isType($type)) {
            $got = $this->tokens[$this->pos]['value'] ?? 'end of input';
            throw new FormulaParseException(
                "Expected {$type} but got '{$got}'",
                $this->pos,
                $this->expression,
            );
        }
        $this->pos++;
    }
}
