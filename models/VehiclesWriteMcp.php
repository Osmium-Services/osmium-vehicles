<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Models;

use Osmium\Core\Library\McpToolException;
use Osmium\Core\Library\ServiceHooks;
use Osmium\Core\Library\ServiceLoader;
use Osmium\Core\Models\ServiceModel;
use Osmium\Modules\Admin\Models\Changelog;
use Osmium\Modules\Admin\Models\Page;
use Osmium\Modules\Admin\Services\AdminPages;

/**
 * Vehicles' list and change tools for the /mcp endpoint, offered through core's `mcp.tools` hook: list the live
 * vehicles, change a status, move a vehicle to the bin and bring it back. Delete and restore reuse VehicleBin, so
 * they behave exactly like the admin screen. There is no publishing and no permanent delete. Nothing here returns a
 * registration, description text, features or specs.
 */
class VehiclesWriteMcp
{
    private const LIST_LIMIT = 100;
    private const RECORD_TYPE = 'vehicle';

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>> tool descriptors
     */
    public static function tools(array $payload): array
    {
        $idAndName = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'The vehicle id from vehicles_list'],
                'expected_name' => ['type' => 'string', 'description' => "The vehicle's exact name as listed (make and model). The call is refused if it does not match the id"],
            ],
            'required' => ['id', 'expected_name'],
        ];

        return [
            [
                'name' => 'vehicles_list',
                'description' => 'The live vehicles (not in the bin), newest first, up to ' . self::LIST_LIMIT . ', each with id, name (make and model), year, status, published (true or false) and page address. The result says when there are more. Read only. It never returns registrations, description text, features or specs.',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()],
                'handler' => self::class . '::listVehicles',
            ],
            [
                'name' => 'vehicle_status_set',
                'description' => 'Change the status of a live vehicle to one of ' . \implode(', ', VehiclePresenter::STATUSES) . ' (exact spelling). Give the vehicle id and its exact expected_name. Only the status changes: nothing else about the vehicle and not whether it is published. Undo by setting the old status again (the result names it).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => $idAndName['properties'] + [
                        'status' => ['type' => 'string', 'enum' => VehiclePresenter::STATUSES, 'description' => 'The new status, exactly as written'],
                    ],
                    'required' => ['id', 'expected_name', 'status'],
                ],
                'write' => true,
                'destructive' => true,
                'handler' => self::class . '::setStatus',
            ],
            [
                'name' => 'vehicle_delete',
                'description' => 'Move a vehicle to the bin (a soft delete: its page goes to the Pages bin and nothing is lost). Optionally send its old address to another page of this site with redirect_target (a path starting with /). Undo with vehicle_restore.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => $idAndName['properties'] + [
                        'redirect_target' => ['type' => 'string', 'description' => 'Optional page of this site to send the old address to, such as /stock/'],
                    ],
                    'required' => ['id', 'expected_name'],
                ],
                'write' => true,
                'destructive' => true,
                'handler' => self::class . '::delete',
            ],
            [
                'name' => 'vehicle_restore',
                'description' => 'Bring a deleted vehicle back from the bin. Give its id and exact expected_name.',
                'inputSchema' => $idAndName,
                'write' => true,
                'handler' => self::class . '::restore',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function listVehicles(array $call): array
    {
        $vehicles = new Vehicle($call['dataSource']);

        return self::shapeList($vehicles->listLive(self::LIST_LIMIT + 1));
    }

    /**
     * @param array<int, array<string, mixed>> $rows from vehicle-list-live.sql, up to one more than the cap
     * @return array{vehicles: array<int, array<string, mixed>>, more: bool}
     */
    public static function shapeList(array $rows): array
    {
        $more = \count($rows) > self::LIST_LIMIT;
        $vehicles = [];

        foreach (\array_slice($rows, 0, self::LIST_LIMIT) as $row) {
            $vehicles[] = [
                'id' => (int) $row['id'],
                'name' => VehiclesMcp::name($row),
                'year' => isset($row['year']) ? (int) $row['year'] : null,
                'status' => $row['status'] === null ? null : (string) $row['status'],
                'published' => (int) $row['is_published'] === 1,
                'url' => (string) ($row['url'] ?? ''),
            ];
        }

        return ['vehicles' => $vehicles, 'more' => $more];
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function setStatus(array $call): array
    {
        $arguments = $call['arguments'] ?? [];
        [$id, $expectedName] = self::idAndName($arguments);
        $status = self::requireStatus($arguments['status'] ?? null);
        $vehicles = new Vehicle($call['dataSource']);
        $vehicle = $vehicles->getById($id);
        self::requireName($vehicle, $expectedName, $id);

        $oldStatus = $vehicle['status'] === null ? null : (string) $vehicle['status'];
        if ($oldStatus === $status) throw new McpToolException("Vehicle {$id} already has the status {$status}.");

        $vehicles->setStatus($id, $status);
        $name = VehiclesMcp::name($vehicle);
        self::logger($call)('Updated', $id, VehiclePresenter::displayName($vehicle));
        self::announceSaved($call, $vehicle);

        return [
            'id' => $id,
            'name' => $name,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'undo' => $oldStatus === null ? 'The old status was empty: set it in the admin' : "vehicle_status_set with id {$id}, the same name and status {$oldStatus}",
        ];
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function delete(array $call): array
    {
        $arguments = $call['arguments'] ?? [];
        [$id, $expectedName] = self::idAndName($arguments);
        $redirect = $arguments['redirect_target'] ?? '';
        if (!\is_string($redirect)) throw new McpToolException('redirect_target must be text, a path starting with /.');

        $vehicles = new Vehicle($call['dataSource']);
        self::requireName($vehicles->getById($id), $expectedName, $id);

        try {
            $vehicle = self::bin($call, $vehicles)->delete($id, $redirect);
        } catch (\Exception $e) {
            throw new McpToolException($e->getMessage());
        }

        return ['deleted' => true, 'id' => $id, 'name' => VehiclesMcp::name($vehicle), 'undo' => "vehicle_restore with id {$id} and the same name"];
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function restore(array $call): array
    {
        [$id, $expectedName] = self::idAndName($call['arguments'] ?? []);
        $vehicles = new Vehicle($call['dataSource']);
        self::requireName($vehicles->getDeletedById($id), $expectedName, $id);

        try {
            $vehicle = self::bin($call, $vehicles)->restore($id);
        } catch (\Exception $e) {
            throw new McpToolException($e->getMessage());
        }

        return ['restored' => true, 'id' => $id, 'name' => VehiclesMcp::name($vehicle)];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{0: int, 1: string}
     */
    public static function idAndName(array $arguments): array
    {
        $id = $arguments['id'] ?? null;
        $name = $arguments['expected_name'] ?? null;
        $valid = \is_int($id) && $id > 0 && \is_string($name) && \trim($name) !== '';
        if (!$valid) throw new McpToolException('Give the vehicle id (a whole number) and its exact expected_name.');

        return [$id, \trim($name)];
    }

    /**
     * Refuses unless the vehicle exists and its name (make and model, as vehicles_list shows it) is exactly the
     * one given, so a slip of the id cannot change the wrong vehicle.
     *
     * @param array<string, mixed>|null $vehicle
     */
    public static function requireName(?array $vehicle, string $expectedName, int $id): void
    {
        if ($vehicle === null) throw new McpToolException("No matching vehicle with id {$id}. Check vehicles_list.");
        if (VehiclesMcp::name($vehicle) !== $expectedName) {
            throw new McpToolException("The name does not match vehicle {$id}. Check vehicles_list and try again.");
        }
    }

    /** Exact, case-sensitive match against the statuses the admin offers */
    public static function requireStatus(mixed $status): string
    {
        $isListed = \is_string($status) && \in_array($status, VehiclePresenter::STATUSES, true);
        if (!$isListed) throw new McpToolException('The status must be exactly one of: ' . \implode(', ', VehiclePresenter::STATUSES) . '.');

        return $status;
    }

    /** @param array<string, mixed> $call */
    private static function bin(array $call, Vehicle $vehicles): VehicleBin
    {
        return new VehicleBin($vehicles, self::pages($call), self::logger($call));
    }

    /** @param array<string, mixed> $call */
    private static function pages(array $call): AdminPages
    {
        return new AdminPages(
            page: new Page($call['dataSource'], 'pages'),
            urlTrailingSlash: $call['osmium']->config->site->urlTrailingSlash ?? false,
        );
    }

    /**
     * The page now shows a different status, so tell services (IndexNow) the page was saved, as the admin's save
     * does through AdminPages. Never throws: the change has already been made.
     *
     * @param array<string, mixed> $call
     * @param array<string, mixed> $vehicle from Vehicle::getById
     */
    private static function announceSaved(array $call, array $vehicle): void
    {
        try {
            $hooks = new ServiceHooks(new ServiceLoader(new ServiceModel($call['dataSource'])));
            $hooks->dispatch(event: 'page.saved', payload: [
                'pageId' => (int) $vehicle['page_id'],
                'url' => (string) $vehicle['url'],
                'robots' => (string) ($vehicle['robots'] ?? 'index,follow'),
                'oldUrl' => null,
                'dataSource' => $call['dataSource'],
            ]);
        } catch (\Throwable $e) {
            \error_log('vehicle_status_set page.saved hook failed: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $call
     * @return \Closure(string, int, string): void
     */
    private static function logger(array $call): \Closure
    {
        $changelog = new Changelog($call['dataSource'], 'changelog');
        $changelog->setGetUser(fn(): array => ['username' => $call['actor']]);

        return fn(string $action, int $id, string $name) => $changelog->log(
            description: $action . ': ' . $name,
            recordType: self::RECORD_TYPE,
            recordId: $id,
        );
    }
}
