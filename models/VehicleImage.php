<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/**
 * A vehicle's ordered photo gallery (rows only: VehicleFiles stores and removes the files).
 */
class VehicleImage extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'vehicle_images');
    }

    /** @return array<int, array{id: int|string, vehicle_id: int|string, path: string, position: int|string}> */
    public function listFor(int $vehicleId): array
    {
        $this->database->query($this->loadSqlFile('image-list.sql'));
        $this->database->bind(param: ':vehicle_id', value: $vehicleId);

        return $this->database->resultset();
    }

    public function getById(int $id): ?array
    {
        $this->database->query($this->loadSqlFile('image-get.sql'));
        $this->database->bind(param: ':id', value: $id);
        $row = $this->database->single();

        return $row === false ? null : $row;
    }

    /** Appends to the end of the gallery */
    public function add(int $vehicleId, string $path): int
    {
        $this->database->query($this->loadSqlFile('image-max-position.sql'));
        $this->database->bind(param: ':vehicle_id', value: $vehicleId);
        $position = (int) ($this->database->single()['max_position'] ?? -1) + 1;

        $this->database->query($this->loadSqlFile('image-insert.sql'));
        $this->database->bind(param: ':vehicle_id', value: $vehicleId);
        $this->database->bind(param: ':path', value: $path);
        $this->database->bind(param: ':position', value: $position);
        $this->database->execute();

        return (int) $this->database->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->database->query($this->loadSqlFile('image-delete.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->database->execute();
    }

    /** @param array<int, int> $orderedIds image ids, first to last */
    public function reorder(int $vehicleId, array $orderedIds): void
    {
        foreach (\array_values($orderedIds) as $position => $imageId) {
            $this->database->query($this->loadSqlFile('image-reorder.sql'));
            $this->database->bind(param: ':position', value: $position);
            $this->database->bind(param: ':id', value: (int) $imageId);
            $this->database->bind(param: ':vehicle_id', value: $vehicleId);
            $this->database->execute();
        }
    }
}
