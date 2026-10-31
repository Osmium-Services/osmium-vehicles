<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Models;

/**
 * Answers core's `page.claim` hook. Two kinds of page are this service's: the listing (the page at the configured
 * listing address) and a published vehicle. Everything else returns null so another service, or the page's own module
 * folder, gets its turn. The theme supplies the views; the claim only names them and hands over the data.
 *
 * A hidden vehicle is shown only to a signed-in admin, with $vehicleIsPreview true (the theme may show a banner).
 *
 * Listing view vehicles/views/index.phtml gets $vehiclesIndex: vehicles (newest first, shaped by VehiclePresenter::card)
 * and total. Vehicle view vehicles/views/vehicle.phtml gets $vehicle (shaped by VehiclePresenter::detail) and
 * $vehicleSchema (a ready <script type="application/ld+json"> tag to echo).
 */
class VehicleClaim
{
    // One listing page lists every vehicle. Paging is not built yet.
    private const INDEX_LIMIT = 500;

    /**
     * @param array{pageId: int, slug: string, osmium: object, data?: array} $payload
     */
    public static function page(array $payload): ?array
    {
        $vehicles = new Vehicle($payload['osmium']->dataSource);

        $isIndex = $payload['slug'] === VehicleSettings::get()['listingSlug'];
        if ($isIndex) return self::index($vehicles);

        $row = $vehicles->findPublishedByPageId($payload['pageId']);
        $isPreview = false;

        if ($row === null && self::isAdminSession()) {
            $row = $vehicles->findByPageId($payload['pageId']);
            $isPreview = $row !== null;
        }

        if ($row === null) return null;

        return self::vehicle($row, $payload, $isPreview);
    }

    /** A signed-in, unlocked admin may view a hidden vehicle on the site; everyone else gets the 404 */
    private static function isAdminSession(): bool
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) return false;

        $isSignedIn = !empty($_SESSION['admin_user_id']) && !empty($_SESSION['admin_login_time']);
        $isLocked = !empty($_SESSION['admin_locked']);

        return $isSignedIn && !$isLocked;
    }

    private static function index(Vehicle $vehicles): array
    {
        $all = $vehicles->published(limit: self::INDEX_LIMIT);

        return [
            'view' => 'vehicles/views/index.phtml',
            'data' => ['vehiclesIndex' => ['vehicles' => $all, 'total' => \count($all)]],
        ];
    }

    private static function vehicle(array $row, array $payload, bool $isPreview): array
    {
        $images = (new VehicleImage($payload['osmium']->dataSource))->listFor((int) $row['id']);
        $vehicle = VehiclePresenter::detail($row, $images);
        $data = ['vehicle' => $vehicle, 'vehicleIsPreview' => $isPreview];

        // Link previews use the first photo; $site['socialImage'] is an absolute URL
        if ($vehicle['image']) $data['site']['socialImage'] = $payload['osmium']->config->site->FQDN . $vehicle['image'];

        $withShareImage = \array_replace_recursive($payload['data'] ?? [], $data);
        $data['vehicleSchema'] = VehicleSchema::script($vehicle, $withShareImage);

        return ['view' => 'vehicles/views/vehicle.phtml', 'data' => $data];
    }
}
