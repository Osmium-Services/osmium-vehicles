<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Models;

/**
 * What the vehicles service tells the front-end staff bar: which vehicle page is on screen (adminbar.context) and the
 * admin link that goes with it (adminbar.links). Both read-only and cheap. The page's data is already there from
 * page.claim: $vehicle on a vehicle page (a hidden preview included), $vehiclesIndex on the listing page.
 */
class VehicleAdminBar
{
    /**
     * @param array{data?: array<string, mixed>} $payload the finished page's view data
     * @return string|null "vehicle:12" on a vehicle, "vehicles-index:all" on the listing, null elsewhere
     */
    public static function context(array $payload): ?string
    {
        $data = $payload['data'] ?? [];

        $vehicleId = (int)($data['vehicle']['id'] ?? 0);
        $isVehicle = $vehicleId > 0;
        if ($isVehicle) return "vehicle:{$vehicleId}";

        $isIndex = isset($data['vehiclesIndex']);

        return $isIndex ? 'vehicles-index:all' : null;
    }

    /**
     * @param array{context?: string, role?: string} $payload
     * @return array<int, array<string, mixed>>|null
     */
    public static function links(array $payload): ?array
    {
        $context = (string)($payload['context'] ?? '');

        $isVehicle = \preg_match('/^vehicle:(\d+)$/', $context, $match) === 1;
        if ($isVehicle) {
            return [['label' => 'Edit vehicle', 'icon' => 'bx-car', 'path' => 'vehicles/edit/?id=' . (int)$match[1], 'primary' => true]];
        }

        $isIndex = $context === 'vehicles-index:all';
        if ($isIndex) return [['label' => 'Vehicles', 'icon' => 'bx-car', 'path' => 'vehicles/', 'primary' => true]];

        return null;
    }
}
