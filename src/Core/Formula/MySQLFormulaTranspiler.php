<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\Formula;

/**
 * Transpiles a computed-field formula into a MySQL expression.
 *
 * Security: operates ONLY on the lexer's allowlisted token stream. Raw user
 * text never reaches the output — column refs are backtick-quoted, string
 * literals are re-escaped, and every function/operator is allowlisted. When
 * $allowedColumns is non-empty, any column outside it is rejected. The result
 * is wrapped in DB::raw() by the builder, so this method is the trust boundary.
 */
final class MySQLFormulaTranspiler
{
    public function __construct(
        private readonly FormulaLexer $lexer = new FormulaLexer(),
    ) {}

    /**
     * @param  string[] $allowedColumns  empty = allow any identifier-safe column
     * @throws FormulaParseException
     */
    public function transpile(string $expression, array $allowedColumns = []): string
    {
        $tokens  = $this->lexer->tokenise($expression);
        $allowed = array_flip($allowedColumns);
        $out     = [];

        foreach ($tokens as $pos => $token) {
            $out[] = match ($token['type']) {
                'NUMBER'                          => $token['value'],
                'STRING'                          => $this->quoteString($token['value']),
                'COLUMN_REF'                      => $this->quoteColumn($token['value'], $allowed, $expression, $pos),
                'FUNCTION'                        => $token['value'],
                'OPERATOR', 'COMPARISON'          => " {$token['value']} ",
                'AND_OR'                          => " {$token['value']} ",
                'NULL_KW'                         => 'NULL',
                'LPAREN'                          => '(',
                'RPAREN'                          => ')',
                'COMMA'                           => ', ',
                default                           => throw new FormulaParseException(
                    "Unsupported token '{$token['type']}' in MySQL formula",
                    $pos,
                    $expression,
                ),
            };
        }

        return trim(implode('', $out));
    }

    /** @param array<string,int> $allowed */
    private function quoteColumn(string $name, array $allowed, string $expression, int $pos): string
    {
        if ($allowed !== [] && !isset($allowed[$name])) {
            throw new FormulaParseException(
                "Unknown column '{$name}' in formula",
                $pos,
                $expression,
            );
        }
        // The lexer already constrained $name to [A-Za-z_][A-Za-z0-9_]*
        return "`{$name}`";
    }

    /** Re-quote a lexer STRING token (already single-quoted) for MySQL. */
    private function quoteString(string $literal): string
    {
        // Strip the lexer's surrounding quotes, un-double internal quotes, re-escape.
        $inner = substr($literal, 1, -1);
        $inner = str_replace("''", "'", $inner);
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $inner) . "'";
    }
}
