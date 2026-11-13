<?php

declare(strict_types=1);

namespace Tests\Services\Vehicles;

use Osmium\Services\Vehicles\Models\VehicleAdminBar;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/VehicleAdminBar.php';

/**
 * The staff bar's vehicle answers: a vehicle is announced by its id, the listing page as a whole, and each gets its link.
 */
final class VehicleAdminBarTest extends TestCase
{
    public function test_a_vehicle_page_is_announced_by_its_id(): void
    {
        $this->assertSame('vehicle:12', VehicleAdminBar::context(['data' => ['vehicle' => ['id' => 12]]]));
    }

    public function test_the_listing_page_is_announced(): void
    {
        $this->assertSame('vehicles-index:all', VehicleAdminBar::context(['data' => ['vehiclesIndex' => ['vehicles' => [], 'total' => 0]]]));
    }

    public function test_other_pages_announce_nothing(): void
    {
        $this->assertNull(VehicleAdminBar::context(['data' => ['site' => []]]));
        $this->assertNull(VehicleAdminBar::context([]));
    }

    public function test_a_vehicle_links_to_its_edit_form(): void
    {
        $links = VehicleAdminBar::links(['context' => 'vehicle:12']);

        $this->assertSame('vehicles/edit/?id=12', $links[0]['path']);
        $this->assertSame('bx-car', $links[0]['icon']);
        $this->assertTrue($links[0]['primary']);
    }

    public function test_the_listing_links_to_the_vehicles_list(): void
    {
        $links = VehicleAdminBar::links(['context' => 'vehicles-index:all']);

        $this->assertSame('vehicles/', $links[0]['path']);
        $this->assertSame('bx-car', $links[0]['icon']);
    }

    public function test_unknown_or_malformed_contexts_get_no_links(): void
    {
        $this->assertNull(VehicleAdminBar::links(['context' => 'product:5']));
        $this->assertNull(VehicleAdminBar::links(['context' => 'vehicle:abc']));
        $this->assertNull(VehicleAdminBar::links([]));
    }
}
