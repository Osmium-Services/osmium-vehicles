<?php

declare(strict_types=1);

namespace Tests\Services\Vehicles;

use Osmium\Services\Vehicles\Models\VehicleOrder;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/VehicleOrder.php';

class VehicleOrderTest extends TestCase
{
    public function test_an_item_goes_straight_after_its_anchor(): void
    {
        $this->assertSame([2, 3, 1, 4], VehicleOrder::place([1, 2, 3, 4], 1, 3, 0));
    }

    public function test_with_no_item_before_it_the_item_goes_straight_before_the_one_after(): void
    {
        $this->assertSame([4, 1, 2, 3], VehicleOrder::place([1, 2, 3, 4], 4, 0, 1));
    }

    public function test_with_neither_anchor_the_item_goes_first(): void
    {
        $this->assertSame([3, 1, 2], VehicleOrder::place([1, 2, 3], 3, 0, 0));
    }

    public function test_dropping_an_item_on_itself_changes_nothing(): void
    {
        $this->assertSame([1, 2, 3], VehicleOrder::place([1, 2, 3], 2, 2, 0));
    }

    public function test_an_item_or_anchor_that_is_not_in_the_list_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VehicleOrder::place([1, 2, 3], 2, 9, 0);
    }
}
