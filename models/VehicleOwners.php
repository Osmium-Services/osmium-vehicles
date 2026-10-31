<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Models;

/**
 * Answers core's `pages.owners` hook so the admin Pages list can mark the vehicles' pages "Managed by Vehicles",
 * link them to the vehicle editor and keep them from being deleted there.
 */
class VehicleOwners
{
    /**
     * @param array{osmium: object} $payload
     * @return array<int, array{label: string, editUrl: string}> keyed by page id
     */
    public static function pages(array $payload): array
    {
        $vehicles = new Vehicle($payload['osmium']->dataSource);

        $owned = [];
        foreach ($vehicles->ownedPages() as $row) {
            $owned[(int) $row['page_id']] = ['label' => 'Vehicles', 'editUrl' => 'vehicles/edit/?id=' . (int) $row['id']];
        }

        $indexPageId = $vehicles->indexPageId(VehicleSettings::get()['listingSlug']);
        if ($indexPageId !== null) $owned[$indexPageId] = ['label' => 'Vehicles', 'editUrl' => 'vehicles/'];

        return $owned;
    }
}
