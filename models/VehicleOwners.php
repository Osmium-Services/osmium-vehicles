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
        return self::owned($payload['osmium']->dataSource);
    }

    /**
     * The vehicles' pages and listing page, for the `sitemap.groups` hook: one child sitemap of its own.
     *
     * @param array{dataSource: object} $payload
     * @return array{key: string, label: string, pageIds: array<int, int>}
     */
    public static function sitemapGroups(array $payload): array
    {
        return ['key' => 'vehicles', 'label' => 'Vehicles', 'pageIds' => \array_keys(self::owned($payload['dataSource']))];
    }

    /** @return array<int, array{label: string, editUrl: string}> keyed by page id */
    private static function owned(object $dataSource): array
    {
        $vehicles = new Vehicle($dataSource);

        $owned = [];
        foreach ($vehicles->ownedPages() as $row) {
            $owned[(int) $row['page_id']] = ['label' => 'Vehicles', 'editUrl' => 'vehicles/edit/?id=' . (int) $row['id']];
        }

        $indexPageId = $vehicles->indexPageId(VehicleSettings::get()['listingSlug']);
        if ($indexPageId !== null) $owned[$indexPageId] = ['label' => 'Vehicles', 'editUrl' => 'vehicles/'];

        return $owned;
    }
}
