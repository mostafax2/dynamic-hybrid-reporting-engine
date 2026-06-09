<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\DSL;

/**
 * Describes a SQL window function expression.
 *
 * DSL shape:
 * {
 *   "function":     "ROW_NUMBER",         // required — see ALLOWED list
 *   "alias":        "row_num",            // required
 *   "column":       "amount",             // for SUM/AVG/MIN/MAX/COUNT/LAG/LEAD
 *   "partition_by": ["status", "region"], // optional — column names
 *   "order_by":     [{"column": "created_at", "direction": "asc"}],
 *   "offset":       1,                    // LAG / LEAD
 *   "default":      null,                 // LAG / LEAD fallback
 *   "ntile":        4                     // NTILE bucket count
 * }
 *
 * Ranking (no column):  ROW_NUMBER, RANK, DENSE_RANK, PERCENT_RANK, CUME_DIST
 * Value (with column):  SUM, AVG, COUNT, MIN, MAX, FIRST_VALUE, LAST_VALUE
 * Lag/Lead:             LAG, LEAD
 * Bucket:               NTILE
 */
final readonly class WindowFunction
{
    public const RANKING_FUNCTIONS = ['ROW_NUMBER', 'RANK', 'DENSE_RANK', 'PERCENT_RANK', 'CUME_DIST'];
    public const VALUE_FUNCTIONS   = ['SUM', 'AVG', 'COUNT', 'MIN', 'MAX', 'FIRST_VALUE', 'LAST_VALUE', 'NTH_VALUE'];
    public const LAG_LEAD          = ['LAG', 'LEAD'];
    public const BUCKET_FUNCTIONS  = ['NTILE'];

    public const ALLOWED_FUNCTIONS = [
        ...self::RANKING_FUNCTIONS,
        ...self::VALUE_FUNCTIONS,
        ...self::LAG_LEAD,
        ...self::BUCKET_FUNCTIONS,
    ];

    /** @param OrderByField[] $orderBy */
    public function __construct(
        public string  $function,
        public string  $alias,
        public array   $partitionBy = [],
        public array   $orderBy     = [],
        public ?string $column      = null,
        public int     $offset      = 1,
        public mixed   $default     = null,
        public ?int    $ntile       = null,
    ) {
        $upper = strtoupper($this->function);
        if (!in_array($upper, self::ALLOWED_FUNCTIONS, true)) {
            throw new \InvalidArgumentException(
                "Unknown window function '{$this->function}'. Allowed: " . implode(', ', self::ALLOWED_FUNCTIONS)
            );
        }
    }

    public static function fromArray(array $data): self
    {
        $fn = strtoupper((string) ($data['function'] ?? throw new \InvalidArgumentException(
            'WindowFunction requires a function name'
        )));

        $orderBy = array_map(
            fn($o) => is_string($o)
                ? new OrderByField($o, 'asc')
                : OrderByField::fromArray($o),
            (array) ($data['order_by'] ?? $data['orderBy'] ?? []),
        );

        return new self(
            function:    $fn,
            alias:       (string) ($data['alias'] ?? throw new \InvalidArgumentException(
                'WindowFunction requires an alias'
            )),
            partitionBy: array_map('strval', (array) ($data['partition_by'] ?? $data['partitionBy'] ?? [])),
            orderBy:     $orderBy,
            column:      isset($data['column']) ? (string) $data['column'] : null,
            offset:      (int) ($data['offset'] ?? 1),
            default:     $data['default'] ?? null,
            ntile:       isset($data['ntile']) ? (int) $data['ntile'] : null,
        );
    }

    public function isRanking(): bool
    {
        return in_array(strtoupper($this->function), self::RANKING_FUNCTIONS, true);
    }

    public function isLagLead(): bool
    {
        return in_array(strtoupper($this->function), self::LAG_LEAD, true);
    }

    public function isBucket(): bool
    {
        return in_array(strtoupper($this->function), self::BUCKET_FUNCTIONS, true);
    }
}
