<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Tests\Unit\Infrastructure\Builders;

use Mostafax\ReportingEngine\Core\DSL\DslParser;
use Mostafax\ReportingEngine\Infrastructure\Builders\MongoAggregationBuilder;
use PHPUnit\Framework\TestCase;

final class MongoAggregationBuilderTest extends TestCase
{
    private DslParser               $parser;
    private MongoAggregationBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser  = new DslParser();
        $this->builder = new MongoAggregationBuilder();
    }

    // ── Basic pipeline structure ──────────────────────────────────────────────

    public function test_pipeline_ends_with_facet_stage(): void
    {
        $pipeline  = $this->builder->build($this->parser->parse($this->baseDsl()));
        $lastStage = end($pipeline);

        $this->assertArrayHasKey('$facet', $lastStage);
        $this->assertArrayHasKey('data', $lastStage['$facet']);
        $this->assertArrayHasKey('totalCount', $lastStage['$facet']);
    }

    public function test_no_match_stage_when_no_filters(): void
    {
        $dsl = $this->baseDsl();
        unset($dsl['filters']);

        $pipeline = $this->builder->build($this->parser->parse($dsl));

        $hasMatch = array_filter($pipeline, fn($s) => array_key_exists('$match', $s));
        $this->assertEmpty($hasMatch);
    }

    public function test_match_stage_added_when_filters_present(): void
    {
        $dsl              = $this->baseDsl();
        $dsl['filters']   = [
            'operator'   => 'AND',
            'conditions' => [['field' => 'status', 'operator' => '=', 'value' => 'active']],
        ];

        $pipeline   = $this->builder->build($this->parser->parse($dsl));
        $matchStage = $pipeline[0];

        $this->assertArrayHasKey('$match', $matchStage);
        $this->assertSame('active', $matchStage['$match']['status']);
    }

    public function test_group_stage_present_for_aggregation_query(): void
    {
        $pipeline = $this->builder->build($this->parser->parse($this->baseDsl()));

        $groupStages = array_filter($pipeline, fn($s) => array_key_exists('$group', $s));
        $this->assertNotEmpty($groupStages);
    }

    public function test_sort_stage_added_when_order_by_present(): void
    {
        $pipeline   = $this->builder->build($this->parser->parse($this->baseDsl()));
        $sortStages = array_filter($pipeline, fn($s) => array_key_exists('$sort', $s));

        $this->assertNotEmpty($sortStages);
        $sort = reset($sortStages)['$sort'];
        $this->assertArrayHasKey('total_revenue', $sort);
        $this->assertSame(-1, $sort['total_revenue']);
    }

    // ── GroupByField: column type ─────────────────────────────────────────────

    public function test_plain_column_group_by_uses_dollar_ref(): void
    {
        $pipeline   = $this->builder->build($this->parser->parse($this->baseDsl()));
        $groupStage = $this->findStage($pipeline, '$group');

        $this->assertSame('$status', $groupStage['_id']);
    }

    public function test_multi_column_group_by_produces_composite_id(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['group_by'] = ['status', 'region'];

        $pipeline   = $this->builder->build($this->parser->parse($dsl));
        $groupStage = $this->findStage($pipeline, '$group');

        $this->assertIsArray($groupStage['_id']);
        $this->assertArrayHasKey('status', $groupStage['_id']);
        $this->assertArrayHasKey('region', $groupStage['_id']);
        $this->assertSame('$status', $groupStage['_id']['status']);
        $this->assertSame('$region', $groupStage['_id']['region']);
    }

    // ── GroupByField: date_trunc type ─────────────────────────────────────────

    public function test_date_trunc_group_by_produces_dateTrunc_operator(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [[
            'type'        => 'date_trunc',
            'column'      => 'created_at',
            'granularity' => 'month',
            'alias'       => 'created_month',
        ]];

        $pipeline   = $this->builder->build($this->parser->parse($dsl));
        $groupStage = $this->findStage($pipeline, '$group');

        $id = $groupStage['_id'];
        $this->assertIsArray($id);
        $this->assertArrayHasKey('$dateTrunc', $id);
        $this->assertSame('month', $id['$dateTrunc']['unit']);
    }

    public function test_date_trunc_year_granularity(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [[
            'type'        => 'date_trunc',
            'column'      => 'created_at',
            'granularity' => 'year',
        ]];

        $pipeline   = $this->builder->build($this->parser->parse($dsl));
        $groupStage = $this->findStage($pipeline, '$group');

        $this->assertSame('year', $groupStage['_id']['$dateTrunc']['unit']);
    }

    public function test_date_trunc_id_includes_toDate(): void
    {
        $dsl             = $this->baseDsl();
        $dsl['group_by'] = [[
            'type'        => 'date_trunc',
            'column'      => 'created_at',
            'granularity' => 'day',
        ]];

        $pipeline   = $this->builder->build($this->parser->parse($dsl));
        $groupStage = $this->findStage($pipeline, '$group');

        $this->assertArrayHasKey('$toDate', $groupStage['_id']['$dateTrunc']['date']);
        $this->assertSame('$created_at', $groupStage['_id']['$dateTrunc']['date']['$toDate']);
    }

    // ── Aggregation expressions ───────────────────────────────────────────────

    public function test_sum_aggregation_in_group_stage(): void
    {
        $pipeline   = $this->builder->build($this->parser->parse($this->baseDsl()));
        $groupStage = $this->findStage($pipeline, '$group');

        $this->assertArrayHasKey('total_revenue', $groupStage);
        $this->assertArrayHasKey('$sum', $groupStage['total_revenue']);
        $this->assertSame('$amount', $groupStage['total_revenue']['$sum']);
    }

    public function test_count_aggregation_sums_1(): void
    {
        $dsl                   = $this->baseDsl();
        $dsl['aggregations'][] = ['function' => 'count', 'column' => '_id', 'alias' => 'order_count'];

        $pipeline   = $this->builder->build($this->parser->parse($dsl));
        $groupStage = $this->findStage($pipeline, '$group');

        $this->assertSame(1, $groupStage['order_count']['$sum']);
    }

    public function test_count_distinct_uses_addToSet(): void
    {
        $dsl                   = $this->baseDsl();
        $dsl['aggregations'][] = ['function' => 'count_distinct', 'column' => 'customer_id', 'alias' => 'unique_customers'];

        $pipeline   = $this->builder->build($this->parser->parse($dsl));
        $groupStage = $this->findStage($pipeline, '$group');

        $this->assertArrayHasKey('$addToSet', $groupStage['unique_customers']);
    }

    // ── $project stage after $group ───────────────────────────────────────────

    public function test_post_group_project_removes_id(): void
    {
        $pipeline     = $this->builder->build($this->parser->parse($this->baseDsl()));
        $projectStage = $this->findStage($pipeline, '$project');

        $this->assertNotNull($projectStage);
        $this->assertSame(0, $projectStage['_id']);
    }

    public function test_post_group_project_maps_group_by_column(): void
    {
        $pipeline     = $this->builder->build($this->parser->parse($this->baseDsl()));
        $projectStage = $this->findStage($pipeline, '$project');

        $this->assertArrayHasKey('status', $projectStage);
    }

    // ── Filter operators ──────────────────────────────────────────────────────

    public function test_gt_filter_becomes_gt_operator(): void
    {
        $dsl            = $this->baseDsl();
        $dsl['filters'] = [
            'operator'   => 'AND',
            'conditions' => [['field' => 'amount', 'operator' => '>', 'value' => 100]],
        ];

        $pipeline   = $this->builder->build($this->parser->parse($dsl));
        $matchStage = $this->findStage($pipeline, '$match');

        $this->assertSame(['$gt' => 100], $matchStage['amount']);
    }

    public function test_in_filter_becomes_in_operator(): void
    {
        $dsl            = $this->baseDsl();
        $dsl['filters'] = [
            'operator'   => 'AND',
            'conditions' => [['field' => 'status', 'operator' => 'in', 'value' => ['active', 'pending']]],
        ];

        $pipeline   = $this->builder->build($this->parser->parse($dsl));
        $matchStage = $this->findStage($pipeline, '$match');

        $this->assertSame(['$in' => ['active', 'pending']], $matchStage['status']);
    }

    public function test_like_filter_becomes_regex(): void
    {
        $dsl            = $this->baseDsl();
        $dsl['filters'] = [
            'operator'   => 'AND',
            'conditions' => [['field' => 'name', 'operator' => 'like', 'value' => '%foo%']],
        ];

        $pipeline   = $this->builder->build($this->parser->parse($dsl));
        $matchStage = $this->findStage($pipeline, '$match');

        $this->assertArrayHasKey('$regex', $matchStage['name']);
    }

    // ── Export pipeline (no $facet) ───────────────────────────────────────────

    public function test_build_for_export_has_no_facet_stage(): void
    {
        $pipeline = $this->builder->buildForExport($this->parser->parse($this->baseDsl()));

        $facetStages = array_filter($pipeline, fn($s) => array_key_exists('$facet', $s));
        $this->assertEmpty($facetStages);
    }

    public function test_build_for_export_retains_group_stage(): void
    {
        $pipeline   = $this->builder->buildForExport($this->parser->parse($this->baseDsl()));
        $groupStage = $this->findStage($pipeline, '$group');

        $this->assertNotNull($groupStage);
    }

    // ── Pagination in $facet ──────────────────────────────────────────────────

    public function test_facet_data_has_skip_and_limit(): void
    {
        $dsl                = $this->baseDsl();
        $dsl['pagination']  = ['page' => 2, 'per_page' => 10];

        $pipeline  = $this->builder->build($this->parser->parse($dsl));
        $lastStage = end($pipeline);
        $data      = $lastStage['$facet']['data'];

        $skipOp  = array_filter($data, fn($s) => array_key_exists('$skip', $s));
        $limitOp = array_filter($data, fn($s) => array_key_exists('$limit', $s));

        $this->assertNotEmpty($skipOp);
        $this->assertNotEmpty($limitOp);
        $this->assertSame(10, reset($skipOp)['$skip']);
        $this->assertSame(10, reset($limitOp)['$limit']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findStage(array $pipeline, string $operator): ?array
    {
        foreach ($pipeline as $stage) {
            if (array_key_exists($operator, $stage)) {
                return $stage[$operator];
            }
        }
        return null;
    }

    private function baseDsl(): array
    {
        return [
            'source'       => 'mongodb',
            'connection'   => 'mongodb',
            'table'        => 'orders',
            'fields'       => [
                ['column' => 'id'],
                ['column' => 'status'],
            ],
            'aggregations' => [
                ['function' => 'sum', 'column' => 'amount', 'alias' => 'total_revenue'],
            ],
            'group_by'  => ['status'],
            'order_by'  => [['column' => 'total_revenue', 'direction' => 'desc']],
            'pagination' => ['page' => 1, 'per_page' => 25],
        ];
    }
}
