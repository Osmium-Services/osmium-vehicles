<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Models;

/**
 * Where a dragged item lands in an ordered list: straight after $afterId, or straight before $beforeId when there is
 * nothing to go after (dragged to the top of a page or search result, where the item before it is out of view).
 * Neither: first. Shared by the vehicle list and a vehicle's photos.
 */
class VehicleOrder
{
    /**
     * @param array<int, int> $ids the current order
     * @return array<int, int> the new order
     * @throws \InvalidArgumentException when the item or its anchor is not in the list
     */
    public static function place(array $ids, int $id, int $afterId, int $beforeId): array
    {
        $anchor = $afterId ?: $beforeId;
        if (!\in_array($id, $ids, true) || ($anchor !== 0 && !\in_array($anchor, $ids, true))) throw new \InvalidArgumentException('Not found');
        if ($anchor === $id) return $ids;

        $ids = \array_values(\array_diff($ids, [$id]));
        $position = $anchor === 0 ? 0 : (int) \array_search($anchor, $ids, true) + ($afterId ? 1 : 0);
        \array_splice($ids, $position, 0, [$id]);

        return $ids;
    }
}
