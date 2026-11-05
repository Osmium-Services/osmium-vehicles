<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

class Vehicle extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'vehicles');
    }

    /** The published vehicle behind a pages row, or null when the page is not a vehicle (or is hidden) */
    public function findPublishedByPageId(int $pageId): ?array
    {
        return $this->one('vehicle-get-published-by-page.sql', [':page_id' => $pageId]);
    }

    /** The vehicle behind a pages row whatever its state, for an admin's preview */
    public function findByPageId(int $pageId): ?array
    {
        return $this->one('vehicle-get-by-page.sql', [':page_id' => $pageId]);
    }

    public function getById(int $id): ?array
    {
        return $this->one('vehicle-get.sql', [':id' => $id]);
    }

    /** A vehicle whose page is in the bin, or null when it is not there or is not deleted */
    public function getDeletedById(int $id): ?array
    {
        return $this->one('vehicle-get-deleted.sql', [':id' => $id]);
    }

    public function listAll(): array
    {
        return $this->many('vehicle-list.sql');
    }

    /** Created or changed in the last 24 hours, newest change first, capped at 10 */
    public function listRecent(): array
    {
        return $this->many('vehicle-list-recent.sql');
    }

    /** Every vehicle whose page is in the bin, most recently deleted first */
    public function listDeleted(): array
    {
        return $this->many('vehicle-list-deleted.sql');
    }

    /**
     * Published vehicles, newest first, each decorated by VehiclePresenter (display name, first image, price text,
     * address). For the listing page and a theme's "latest vehicles".
     */
    public function published(int $limit): array
    {
        $rows = $this->many('vehicle-list-published.sql', [':limit' => $limit], \PDO::PARAM_INT);

        return \array_map(VehiclePresenter::card(...), $rows);
    }

    public function latest(int $count): array
    {
        return $this->published(limit: $count);
    }

    /** @param array<string, mixed> $fields see VehicleController::fields() */
    public function create(int $pageId, array $fields): int
    {
        $this->database->query($this->loadSqlFile('vehicle-insert.sql'));
        $this->database->bind(param: ':page_id', value: $pageId);
        $this->bindFields($fields);
        $this->database->execute();

        return (int) $this->database->lastInsertId();
    }

    public function update(int $id, array $fields): void
    {
        $this->database->query($this->loadSqlFile('vehicle-update.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->bindFields($fields);
        $this->database->execute();
    }

    /** Marks the vehicle as just changed, for bin and restore, which only touch its page */
    public function touch(int $id): void
    {
        $this->run('vehicle-touch.sql', [':id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->run('vehicle-delete.sql', [':id' => $id]);
    }

    /** Every pages row a vehicle sits behind (binned too), as vehicle id and page id, for the admin Pages list */
    public function ownedPages(): array
    {
        return $this->many('vehicle-owned-pages.sql');
    }

    public function indexPageId(string $url): ?int
    {
        $row = $this->one('vehicle-index-page.sql', [':url' => $url]);

        return $row === null ? null : (int) $row['id'];
    }

    /** The listing page's id when it sits in the Pages bin (soft-deleted), so a save can bring it back */
    public function deletedIndexPageId(string $url): ?int
    {
        $row = $this->one('vehicle-deleted-index-page.sql', [':url' => $url]);

        return $row === null ? null : (int) $row['id'];
    }

    /** Pages the delete dialog can redirect to: id, url and title of each */
    public function linkTargets(): array
    {
        return $this->many('vehicle-link-targets.sql');
    }

    private function bindFields(array $fields): void
    {
        foreach (['status', 'make', 'model', 'year', 'registration', 'mileage', 'price', 'engine', 'transmission', 'fuel', 'description', 'features', 'specs'] as $key) {
            $this->database->bind(param: ':' . $key, value: $fields[$key] ?? null);
        }
        $this->database->bind(param: ':is_published', value: !empty($fields['is_published']) ? 1 : 0);
    }

    private function one(string $file, array $params): ?array
    {
        $this->database->query($this->loadSqlFile($file));
        foreach ($params as $name => $value) $this->database->bind(param: $name, value: $value);
        $row = $this->database->single();

        return $row === false ? null : $row;
    }

    private function many(string $file, array $params = [], ?int $type = null): array
    {
        $this->database->query($this->loadSqlFile($file));
        foreach ($params as $name => $value) {
            $type === null ? $this->database->bind(param: $name, value: $value) : $this->database->bind(param: $name, value: $value, type: $type);
        }

        return $this->database->resultset();
    }

    private function run(string $file, array $params): void
    {
        $this->database->query($this->loadSqlFile($file));
        foreach ($params as $name => $value) $this->database->bind(param: $name, value: $value);
        $this->database->execute();
    }
}
