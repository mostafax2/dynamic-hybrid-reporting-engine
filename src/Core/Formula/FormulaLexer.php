<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\Formula;

/**
 * Tokenises a computed-field formula into an allowlisted token stream.
 *
 * Security boundary: anything not explicitly recognised below raises a
 * FormulaParseException. The transpilers consume ONLY this token stream — raw
 * user text never reaches generated SQL/Mongo. Characters like ; ` # | are
 * unrepresentable here, so injection vectors fail at the lexer.
 *
 * @phpstan-type Token array{type: string, value: string}
 */
final class FormulaLexer
{
    /** Keywords emitted as FUNCTION (canonicalised to upper-case value). */
    private const FUNCTIONS = [
        'ROUND', 'FLOOR', 'CEIL', 'CEILING', 'ABS', 'MOD', 'POWER', 'SQRT',
        'GREATEST', 'LEAST',
        'DATE_FORMAT', 'DATEDIFF', 'NOW', 'DATE', 'YEAR', 'MONTH', 'DAY',
        'IF', 'COALESCE', 'NULLIF', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END',
        'CONCAT', 'LENGTH', 'LOWER', 'UPPER', 'TRIM',
    ];

    /**
     * @return array<int, array{type: string, value: string}>
     * @throws FormulaParseException on any character outside the allowlist
     */
    public function tokenise(string $expression): array
    {
        $tokens = [];
        $len    = strlen($expression);
        $i      = 0;

        while ($i < $len) {
            $ch = $expression[$i];

            // Whitespace — skip
            if (ctype_space($ch)) {
                $i++;
                continue;
            }

            // Number: \d+(\.\d+)?
            if (ctype_digit($ch)) {
                $start = $i;
                while ($i < $len && ctype_digit($expression[$i])) {
                    $i++;
                }
                if ($i < $len && $expression[$i] === '.' && $i + 1 < $len && ctype_digit($expression[$i + 1])) {
                    $i++;
                    while ($i < $len && ctype_digit($expression[$i])) {
                        $i++;
                    }
                }
                $tokens[] = ['type' => 'NUMBER', 'value' => substr($expression, $start, $i - $start)];
                continue;
            }

            // Single-quoted string literal — '' escapes a quote
            if ($ch === "'") {
                $start = $i;
                $i++;
                while ($i < $len) {
                    if ($expression[$i] === "'") {
                        if ($i + 1 < $len && $expression[$i + 1] === "'") {
                            $i += 2;
                            continue;
                        }
                        $i++;
                        $tokens[] = ['type' => 'STRING', 'value' => substr($expression, $start, $i - $start)];
                        continue 2;
                    }
                    $i++;
                }
                throw new FormulaParseException('Unterminated string literal', $start, $expression);
            }

            // Identifier: [A-Za-z_][A-Za-z0-9_]* → FUNCTION | AND_OR | NULL_KW | COLUMN_REF
            if (ctype_alpha($ch) || $ch === '_') {
                $start = $i;
                while ($i < $len && (ctype_alnum($expression[$i]) || $expression[$i] === '_')) {
                    $i++;
                }
                $word  = substr($expression, $start, $i - $start);
                $upper = strtoupper($word);

                if ($upper === 'AND' || $upper === 'OR') {
                    $tokens[] = ['type' => 'AND_OR', 'value' => $upper];
                } elseif ($upper === 'NULL') {
                    $tokens[] = ['type' => 'NULL_KW', 'value' => $upper];
                } elseif (in_array($upper, self::FUNCTIONS, true)) {
                    $tokens[] = ['type' => 'FUNCTION', 'value' => $upper];
                } else {
                    $tokens[] = ['type' => 'COLUMN_REF', 'value' => $word];
                }
                continue;
            }

            // Comparison operators (multi-char first)
            $two = substr($expression, $i, 2);
            if (in_array($two, ['!=', '<>', '>=', '<='], true)) {
                $tokens[] = ['type' => 'COMPARISON', 'value' => $two];
                $i += 2;
                continue;
            }
            if ($ch === '=' || $ch === '>' || $ch === '<') {
                $tokens[] = ['type' => 'COMPARISON', 'value' => $ch];
                $i++;
                continue;
            }

            // Arithmetic operators
            if (in_array($ch, ['+', '-', '*', '/', '%'], true)) {
                $tokens[] = ['type' => 'OPERATOR', 'value' => $ch];
                $i++;
                continue;
            }

            // Structural
            $structural = match ($ch) {
                '('     => 'LPAREN',
                ')'     => 'RPAREN',
                ','     => 'COMMA',
                default => null,
            };
            if ($structural !== null) {
                $tokens[] = ['type' => $structural, 'value' => $ch];
                $i++;
                continue;
            }

            // Anything else is rejected — this is the injection guard
            throw new FormulaParseException(
                "Illegal character '{$ch}' in formula",
                $i,
                $expression,
            );
        }

        return $tokens;
    }
}
