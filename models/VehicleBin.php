<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Models;

use Osmium\Modules\Admin\Services\AdminPages;

/**
 * Soft delete and restore for vehicles. The page goes to the Pages bin and the vehicle row stays, so a restore brings
 * it all back. Permanent delete is admin-only and lives in the controller.
 */
class VehicleBin
{
    /** @param \Closure(string, int, string): void $log called with the action ('Deleted', 'Restored'), vehicle id and name */
    public function __construct(
        private Vehicle $vehicles,
        private AdminPages $pages,
        private \Closure $log,
    ) {}

    /** An optional redirect sends the old address to another page of the site until the vehicle is restored */
    public function delete(int $id, string $redirectTarget = ''): array
    {
        $vehicle = $this->vehicles->getById($id);
        if (!$vehicle) throw new \InvalidArgumentException('Vehicle not found');

        $redirectTarget = \trim($redirectTarget);
        $isSitePath = $redirectTarget === '' || (\str_starts_with($redirectTarget, '/') && !\str_starts_with($redirectTarget, '//'));
        if (!$isSitePath) throw new \InvalidArgumentException('Redirect to a page of this site, starting with /');

        $this->pages->delete(id: (int) $vehicle['page_id'], input: ['redirect_target' => $redirectTarget]);
        $this->vehicles->touch($id);
        ($this->log)('Deleted', $id, VehiclePresenter::displayName($vehicle));

        return $vehicle;
    }

    public function restore(int $id): array
    {
        $vehicle = $this->vehicles->getDeletedById($id);
        if (!$vehicle) throw new \InvalidArgumentException('Deleted vehicle not found');

        $this->pages->restore((int) $vehicle['page_id']);
        $this->vehicles->touch($id);
        ($this->log)('Restored', $id, VehiclePresenter::displayName($vehicle));

        return $vehicle;
    }
}
