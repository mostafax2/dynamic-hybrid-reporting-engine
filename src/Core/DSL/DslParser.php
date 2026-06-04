<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Core\DSL;

use Mostafax\ReportingEngine\Core\Validation\DslValidationException;

/**
 * Parses a raw JSON string or associative array into a fully-typed QueryDefinition.
 *
 * This is the entry point for all external DSL input. Every field is
 * explicitly cast; no raw arrays leave this layer untyped.
 */
final class DslParser
{
    /**
     * @throws DslValidationException
     */
    public function parse(array|string $raw): QueryDefinition
    {
        $data = is_string($raw) ? $this->decodeJson($raw) : $raw;

        $source = strtolower((string) ($data['source'] ?? throw new DslValidationException('DSL is missing required field: source')));
        $table  = (string) ($data['table'] ?? $data['collection'] ?? throw new DslValidationException('DSL is missing required field: table / collection'));

        $connection = (string) ($data['connection'] ?? $this->resolveConnection($source));

        return new QueryDefinition(
            source:       $source,
            connection:   $connection,
            table:        $table,
            fields:       $this->parseFields($data['fields'] ?? []),
            aggregations: $this->parseAggregations($data['aggregations'] ?? []),
            filters:      isset($data['filters']) && !empty($data['filters']) ? FilterGroup::fromArray($data['filters']) : null,
            groupBy:      array_map('strval', (array) ($data['group_by'] ?? $data['groupBy'] ?? [])),
            orderBy:      $this->parseOrderBy($data['order_by'] ?? $data['orderBy'] ?? []),
            pagination:   Pagination::fromArray($data['pagination'] ?? []),
            joins:        $this->parseJoins($data['joins'] ?? []),
            options:      (array) ($data['options'] ?? []),
            reportId:     isset($data['report_id']) ? (string) $data['report_id'] : null,
            tenantId:     isset($data['tenant_id']) ? (string) $data['tenant_id'] : null,
        );
    }

    /** @return SelectField[] */
    private function parseFields(array $raw): array
    {
        $fields = [];
        foreach ($raw as $item) {
            $fields[] = is_string($item)
                ? new SelectField(column: $item)
                : SelectField::fromArray($item);
        }
        return $fields;
    }

    /** @return AggregationField[] */
    private function parseAggregations(array $raw): array
    {
        return array_map(
            fn(array $item) => AggregationField::fromArray($item),
            $raw,
        );
    }

    /** @return OrderByField[] */
    private function parseOrderBy(array $raw): array
    {
        return array_map(
            fn(array|string $item) => is_string($item)
                ? new OrderByField(column: $item, direction: 'asc')
                : OrderByField::fromArray($item),
            $raw,
        );
    }

    /** @return JoinClause[] */
    private function parseJoins(array $raw): array
    {
        return array_map(
            fn(array $item) => JoinClause::fromArray($item),
            $raw,
        );
    }

    private function resolveConnection(string $source): string
    {
        if (function_exists('config')) {
            return (string) config("reporting-engine.connections.{$source}", $source);
        }
        return $source;
    }

    /** @throws DslValidationException */
    private function decodeJson(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new DslValidationException("Invalid DSL JSON: {$e->getMessage()}", previous: $e);
        }

        if (!is_array($data)) {
            throw new DslValidationException('DSL JSON must decode to an object/array');
        }

        return $data;
    }
}
