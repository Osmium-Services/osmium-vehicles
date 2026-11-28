<?php

declare(strict_types=1);

namespace Tests\Services\Vehicles;

use Osmium\Services\Vehicles\Models\VehicleDashboardLinks;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/VehicleDashboardLinks.php';

/**
 * The dashboard tile mirrors the shop's Products tile and opens the vehicles list.
 */
final class VehicleDashboardLinksTest extends TestCase
{
    public function test_the_tile_opens_the_vehicles_list(): void
    {
        $tile = VehicleDashboardLinks::quickLink(['osmium' => new \stdClass()]);

        $this->assertSame('vehicles/', $tile['url']);
        $this->assertSame('Manage Vehicles', $tile['title']);
        $this->assertSame(['label', 'title', 'icon', 'button', 'url'], \array_keys($tile));
    }
}
