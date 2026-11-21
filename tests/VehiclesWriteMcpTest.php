<?php

declare(strict_types=1);

namespace Tests\Services\Vehicles;

use Osmium\Core\Library\McpToolException;
use Osmium\Services\Vehicles\Models\VehiclePresenter;
use Osmium\Services\Vehicles\Models\VehiclesMcp;
use Osmium\Services\Vehicles\Models\VehiclesWriteMcp;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/VehiclePresenter.php';
require_once __DIR__ . '/../models/VehiclesMcp.php';
require_once __DIR__ . '/../models/VehiclesWriteMcp.php';

/**
 * The list and change tools: descriptors, guards, status validation, and no personal text in the output.
 */
final class VehiclesWriteMcpTest extends TestCase
{
    private function byName(): array
    {
        $tools = [];
        foreach (VehiclesWriteMcp::tools([]) as $tool) {
            $tools[$tool['name']] = $tool;
        }

        return $tools;
    }

    public function test_only_the_three_changing_tools_are_write_tools(): void
    {
        $tools = $this->byName();
        $this->assertSame(['vehicles_list', 'vehicle_status_set', 'vehicle_delete', 'vehicle_restore'], \array_keys($tools));
        $this->assertArrayNotHasKey('write', $tools['vehicles_list']);
        foreach (['vehicle_status_set', 'vehicle_delete', 'vehicle_restore'] as $name) {
            $this->assertTrue($tools[$name]['write']);
        }
    }

    public function test_no_tool_offers_publish_or_permanent_delete(): void
    {
        foreach (\array_keys($this->byName()) as $name) {
            $this->assertStringNotContainsString('publish', $name);
            $this->assertStringNotContainsString('permanent', $name);
        }
    }

    public function test_id_and_name_guard(): void
    {
        $this->assertSame([5, 'Fiat Roller'], VehiclesWriteMcp::idAndName(['id' => 5, 'expected_name' => '  Fiat Roller ']));

        $bad = [[], ['id' => '5', 'expected_name' => 'x'], ['id' => 0, 'expected_name' => 'x'], ['id' => 5.0, 'expected_name' => 'x'],
            ['id' => 5, 'expected_name' => ''], ['id' => 5, 'expected_name' => '  '], ['id' => 5, 'expected_name' => 7], ['id' => 5]];
        foreach ($bad as $arguments) {
            try {
                VehiclesWriteMcp::idAndName($arguments);
                $this->fail('Accepted ' . \json_encode($arguments));
            } catch (McpToolException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_name_mismatch_and_missing_vehicle_are_refused(): void
    {
        VehiclesWriteMcp::requireName(['make' => ' Fiat ', 'model' => 'Roller Team'], 'Fiat Roller Team', 1);
        $this->addToAssertionCount(1);

        $this->expectException(McpToolException::class);
        VehiclesWriteMcp::requireName(['make' => 'Fiat', 'model' => 'Roller Team'], 'fiat roller team', 1);
    }

    public function test_missing_vehicle_is_refused(): void
    {
        $this->expectException(McpToolException::class);
        VehiclesWriteMcp::requireName(null, 'Fiat', 1);
    }

    public function test_status_must_be_one_of_the_exact_list(): void
    {
        foreach (VehiclePresenter::STATUSES as $status) {
            $this->assertSame($status, VehiclesWriteMcp::requireStatus($status));
        }
        foreach (['for sale', 'SOLD', 'Sold ', 'Available', '', null, 1, ['Sold']] as $bad) {
            try {
                VehiclesWriteMcp::requireStatus($bad);
                $this->fail('Accepted ' . \json_encode($bad));
            } catch (McpToolException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_list_shape_has_no_personal_text_and_flags_more(): void
    {
        $row = ['id' => '3', 'make' => 'Fiat', 'model' => 'Roller Team', 'year' => 2014, 'status' => 'Sold', 'is_published' => 1, 'url' => 'stock/fiat',
            'registration' => 'AB12CDE', 'description' => 'secret text', 'features' => '["x"]', 'specs' => '[]'];
        $result = VehiclesWriteMcp::shapeList([$row]);

        $this->assertFalse($result['more']);
        $this->assertSame(['id' => 3, 'name' => 'Fiat Roller Team', 'year' => 2014, 'status' => 'Sold', 'published' => true, 'url' => 'stock/fiat'], $result['vehicles'][0]);
        $json = \json_encode($result);
        foreach (['AB12CDE', 'secret', 'registration', 'description', 'features', 'specs'] as $leak) {
            $this->assertStringNotContainsString($leak, $json);
        }

        $many = \array_fill(0, 101, $row);
        $capped = VehiclesWriteMcp::shapeList($many);
        $this->assertTrue($capped['more']);
        $this->assertCount(100, $capped['vehicles']);
    }

    public function test_list_name_matches_the_attention_tool(): void
    {
        $row = ['make' => ' Swift ', 'model' => ' Escape '];
        $this->assertSame('Swift Escape', VehiclesMcp::name($row));
        $this->assertSame('Swift Escape', VehiclesWriteMcp::shapeList([$row + ['id' => 1, 'year' => null, 'status' => null, 'is_published' => 0, 'url' => 'a']])['vehicles'][0]['name']);
    }
}
