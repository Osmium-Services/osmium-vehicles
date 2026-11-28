<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Models;

/**
 * What the vehicles service adds to the dashboard's quick links tile row (dashboard.quick_links).
 */
class VehicleDashboardLinks
{
    /**
     * @param array{osmium: object} $payload
     * @return array<string, string> the Manage Vehicles tile
     */
    public static function quickLink(array $payload): array
    {
        return [
            'label' => 'Vehicles',
            'title' => 'Manage Vehicles',
            'icon' => 'bx-car',
            'button' => 'View Vehicles',
            'url' => 'vehicles/',
        ];
    }
}
