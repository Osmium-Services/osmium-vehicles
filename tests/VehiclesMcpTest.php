<?php

declare(strict_types=1);

namespace Tests\Services\Vehicles;

use Osmium\Services\Vehicles\Models\VehiclesMcp;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/VehiclesMcp.php';

/**
 * The vehicles_attention tool: read only, counts, each problem rule, the cap, and no personal text in the output.
 */
final class VehiclesMcpTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private function row(array $overrides = []): array
    {
        return $overrides + [
            'id' => 1, 'make' => 'Fiat', 'model' => 'Roller Team', 'year' => 2014, 'status' => 'For Sale', 'price' => '34950.00',
            'mileage' => 21000, 'is_published' => 1, 'created_at' => '2027-01-01 00:00:00', 'url' => 'stock/fiat',
            'description_length' => 500, 'image_count' => 3,
        ];
    }

    private function days(int $days): string
    {
        return \date('Y-m-d H:i:s', self::NOW - $days * 86400 - 60);
    }

    private function problems(array $rows, array $missing = []): array
    {
        return VehiclesMcp::shape($rows, $missing, self::NOW)['published_problems'];
    }

    public function test_tool_is_read_only_with_no_input(): void
    {
        $tools = VehiclesMcp::tools([]);

        $this->assertCount(1, $tools);
        $this->assertSame('vehicles_attention', $tools[0]['name']);
        $this->assertArrayNotHasKey('write', $tools[0]);
        $this->assertEquals(new \stdClass(), $tools[0]['inputSchema']['properties']);
        $this->assertSame(VehiclesMcp::class . '::attention', $tools[0]['handler']);
        $this->assertStringContainsString('Read only', $tools[0]['description']);
    }

    public function test_counts_and_by_status(): void
    {
        $out = VehiclesMcp::shape([
            $this->row(['id' => 1, 'status' => 'For Sale']),
            $this->row(['id' => 2, 'status' => 'For Sale', 'is_published' => 0]),
            $this->row(['id' => 3, 'status' => 'Sold']),
            $this->row(['id' => 4, 'status' => null]),
        ], [], self::NOW);

        $this->assertSame(['live' => 4, 'published' => 3, 'hidden' => 1, 'by_status' => ['For Sale' => 2, 'Sold' => 1, 'none' => 1]], $out['counts']);
    }

    public function test_a_healthy_vehicle_has_no_problems(): void
    {
        foreach ($this->problems([$this->row(['created_at' => $this->days(5)])]) as $problem) {
            $this->assertSame(0, $problem['total']);
        }
    }

    public function test_each_rule_flags_only_its_vehicle(): void
    {
        $fresh = ['created_at' => $this->days(1)];
        $p = $this->problems([
            $this->row($fresh + ['id' => 1, 'image_count' => 0]),
            $this->row($fresh + ['id' => 2]),
            $this->row($fresh + ['id' => 3, 'price' => null]),
            $this->row($fresh + ['id' => 4, 'price' => '0.00']),
            $this->row($fresh + ['id' => 5, 'description_length' => 99]),
            $this->row($fresh + ['id' => 6, 'year' => null]),
            $this->row($fresh + ['id' => 7, 'mileage' => null]),
        ], [2]);

        $ids = static fn(string $k): array => \array_column($p[$k]['vehicles'], 'id');
        $this->assertSame([1], $ids('no_photos'));
        $this->assertSame([2], $ids('photo_file_missing'));
        $this->assertSame([3, 4], $ids('no_price'));
        $this->assertSame([5], $ids('short_description'));
        $this->assertSame([6], $ids('no_year'));
        $this->assertSame([7], $ids('no_mileage'));
        $this->assertSame(0, $p['listed_over_90_days']['total']);
    }

    public function test_description_of_exactly_100_is_not_short(): void
    {
        $p = $this->problems([$this->row(['created_at' => $this->days(1), 'description_length' => 100])]);

        $this->assertSame(0, $p['short_description']['total']);
    }

    public function test_listed_over_90_days_ignores_sold_in_any_case(): void
    {
        $p = $this->problems([
            $this->row(['id' => 1, 'created_at' => $this->days(91)]),
            $this->row(['id' => 2, 'created_at' => $this->days(91), 'status' => 'SOLD']),
            $this->row(['id' => 3, 'created_at' => $this->days(91), 'status' => 'Sold - collected']),
            $this->row(['id' => 4, 'created_at' => $this->days(90)]),
            $this->row(['id' => 5, 'created_at' => $this->days(91), 'status' => null]),
        ]);

        $this->assertSame([1, 5], \array_column($p['listed_over_90_days']['vehicles'], 'id'));
        $this->assertSame(91, $p['listed_over_90_days']['vehicles'][0]['days_listed']);
    }

    public function test_lists_are_capped_at_25_with_the_full_total(): void
    {
        $rows = [];
        for ($i = 1; $i <= 30; $i++) {
            $rows[] = $this->row(['id' => $i, 'price' => null, 'created_at' => $this->days(1)]);
        }

        $p = $this->problems($rows);

        $this->assertSame(30, $p['no_price']['total']);
        $this->assertCount(25, $p['no_price']['vehicles']);
    }

    public function test_hidden_vehicles_never_appear_in_problem_lists(): void
    {
        $p = $this->problems([$this->row(['is_published' => 0, 'image_count' => 0, 'price' => null, 'year' => null, 'mileage' => null, 'description_length' => 0])], [1]);

        foreach ($p as $problem) {
            $this->assertSame(0, $problem['total']);
            $this->assertSame([], $problem['vehicles']);
        }
    }

    public function test_entry_shape_name_and_status_cap(): void
    {
        $long = \str_repeat('x', 50);
        $p = $this->problems([$this->row(['make' => ' Fiat ', 'model' => '', 'status' => $long, 'price' => null, 'created_at' => $this->days(10)])]);

        $this->assertSame(
            ['id' => 1, 'name' => 'Fiat', 'year' => 2014, 'status' => \str_repeat('x', 30), 'url' => 'stock/fiat', 'days_listed' => 10],
            $p['no_price']['vehicles'][0],
        );
    }

    public function test_personal_text_never_reaches_the_output(): void
    {
        $row = $this->row(['price' => null, 'registration' => 'AB12 CDE', 'description' => 'SECRET DESCRIPTION', 'features' => 'SECRET FEATURES', 'specs' => 'SECRET SPECS', 'engine' => 'SECRET ENGINE']);
        $json = (string) \json_encode(VehiclesMcp::shape([$row], [], self::NOW));

        foreach (['AB12', 'SECRET', '"registration"', '"description"', '"features"', '"specs"'] as $needle) {
            $this->assertStringNotContainsString($needle, $json);
        }
    }
}
