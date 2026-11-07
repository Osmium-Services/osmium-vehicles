<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Modules\Admin\Services\AdminPages;
use Osmium\Services\Vehicles\Models\Vehicle;
use Osmium\Services\Vehicles\Models\VehicleBin;
use Osmium\Services\Vehicles\Models\VehicleFiles;
use Osmium\Services\Vehicles\Models\VehicleHtml;
use Osmium\Services\Vehicles\Models\VehicleImage;
use Osmium\Services\Vehicles\Models\VehiclePresenter;
use Osmium\Services\Vehicles\Models\VehicleSettings;

/**
 * Vehicles admin - the list, the editor, and the AJAX actions behind them.
 *
 * Every vehicle is also an ordinary pages row at <listing address>/<slug> (title, meta description and url live there),
 * so the page, its sitemap entry and its redirects work like any other page. See VehicleClaim for how the public
 * request finds the vehicle again.
 *
 * Routes:
 *   - index()  → /admin/vehicles/
 *   - edit()   → /admin/vehicles/edit/?id=N   (no id: a new vehicle)
 *   - action() → /admin/vehicles/action/      (save, delete, restore, permanently_delete, upload_image, delete_image, reorder_images)
 */
class VehicleController extends AdminController
{
    private const RECORD_TYPE = 'vehicle';

    public function index(): void
    {
        $model = new Vehicle($this->osmium->dataSource);

        $this->data['admin']['isAdmin'] = $this->isAdmin();
        $this->data['admin']['vehicles'] = \array_map($this->formatVehicle(...), $model->listAll());
        $this->data['admin']['recentVehicles'] = \array_map($this->formatRecent(...), $model->listRecent());
        $this->data['admin']['deletedVehicles'] = \array_map($this->formatVehicle(...), $model->listDeleted());
        $this->data['admin']['redirectTargets'] = $this->linkTargets();
        $this->data['admin']['lists'] = require __DIR__ . '/../lists/vehicles.php';

        $this->setView('vehicles/index.phtml');
    }

    public function edit(): void
    {
        $model = new Vehicle($this->osmium->dataSource);

        $id = (int) $this->osmium->getStashedParam(key: 'id', default: 0); // Core moves query params out of $_GET
        $this->osmium->clearStashedQuerystring(); // Or a later "New Vehicle" would reopen this one
        $row = $id ? $model->getById($id) : null;
        if ($id && !$row) $this->redirect('vehicles/');

        $prefix = VehicleSettings::get()['listingSlug'] . '/';
        $row ??= [];
        $this->data['admin']['vehicle'] = [
            'id' => $id,
            'status' => $row['status'] ?? VehiclePresenter::FOR_SALE,
            'make' => $row['make'] ?? '',
            'model' => $row['model'] ?? '',
            'year' => $row['year'] ?? '',
            'registration' => $row['registration'] ?? '',
            'mileage' => $row['mileage'] ?? '',
            'price' => isset($row['price']) ? (string) (float) $row['price'] : '',
            'engine' => $row['engine'] ?? '',
            'transmission' => $row['transmission'] ?? '',
            'fuel' => $row['fuel'] ?? '',
            'description' => $row['description'] ?? '',
            'features' => VehiclePresenter::decodeList($row['features'] ?? null),
            'specs' => VehiclePresenter::decodeList($row['specs'] ?? null),
            'isPublished' => (bool) ($row['is_published'] ?? true),
            'slug' => isset($row['url']) ? \substr($row['url'], \strlen($prefix)) : '',
            'metaDescription' => $row['meta_description'] ?? '',
        ];
        $this->data['admin']['images'] = $id ? (new VehicleImage($this->osmium->dataSource))->listFor($id) : [];
        $this->data['admin']['makes'] = $model->makes();
        $this->data['admin']['models'] = $model->models();
        $this->data['admin']['statuses'] = VehiclePresenter::STATUSES;
        $this->data['admin']['urlPrefix'] = $prefix;
        $this->data['admin']['maxUploadSize'] = \Osmium\Modules\Admin\Services\OsmiumAdmin::getMaxUploadSize()['formatted'] ?? '';
        $this->data['admin']['showSavedMessage'] = $this->consumeSavedFlag();

        $this->setView('vehicles/edit.phtml');
    }

    private function consumeSavedFlag(): bool
    {
        $wasSaved = isset($_SESSION['vehicle_saved']);
        unset($_SESSION['vehicle_saved']);

        return $wasSaved;
    }

    public function action()
    {
        \header('Content-Type: application/json');

        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        if (!$isPost) $this->admin->jsonError('Method not allowed');

        $input = $this->admin->auth->getJsonInput();
        $csrfValid = $this->admin->auth->validateCsrfJson($input);
        if (!$csrfValid) $this->admin->jsonError('Invalid request token. Please refresh and try again.');

        $action = $input['action'] ?? '';
        $id = (int) ($input['id'] ?? 0);

        try {
            $result = match ($action) {
                'save' => $this->save(id: $id, input: $input),
                'delete' => $this->deleteVehicle($id, (string) ($input['redirect_target'] ?? '')),
                'restore' => $this->restoreVehicle($id),
                'permanently_delete' => $this->permanentlyDeleteVehicle($id),
                'upload_image' => $this->uploadImage($id, $_FILES['image'] ?? []),
                'delete_image' => $this->deleteImage($id, (int) ($input['image_id'] ?? 0)),
                'reorder_images' => $this->reorderImages($id, (array) ($input['order'] ?? [])),
                default => throw new \InvalidArgumentException('Unknown action'),
            };

            $this->admin->jsonSuccess($result);
        } catch (\Exception $e) {
            $this->admin->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // Action helpers
    // =========================================================================

    private function save(int $id, array $input): array
    {
        $fields = $this->fields($input);

        $slug = VehicleSettings::cleanSlug((string) ($input['slug'] ?? '') ?: ($fields['make'] . ' ' . $fields['model'] . ' ' . ($fields['year'] ?? '')));
        if ($slug === '') throw new \InvalidArgumentException('Address is required');

        $model = new Vehicle($this->osmium->dataSource);
        $existing = $id === 0 ? null : $model->getById($id);
        if ($id !== 0 && !$existing) throw new \InvalidArgumentException('Vehicle not found');

        $name = VehiclePresenter::displayName($fields);
        $pageFields = [
            'url' => VehicleSettings::get()['listingSlug'] . '/' . $slug,
            'title' => $name,
            'description' => \trim((string) ($input['meta_description'] ?? '')),
            'robots' => $fields['is_published'] ? 'index,follow' : 'noindex,nofollow', // Hidden stays out of the sitemap
            'parent_page_id' => $this->listingPageId(),
        ];

        $pages = $this->pages();
        if ($id === 0) {
            $pageId = $pages->create($pageFields);
            try {
                $id = $model->create($pageId, $fields);
            } catch (\Throwable $e) {
                $pages->permanentDelete($pageId); // No vehicle to show for it: free the address instead of leaving an orphan page
                throw $e;
            }
            $this->logChange(action: 'Created', id: $id, name: $name);
        } else {
            $pages->update((int) $existing['page_id'], $pageFields); // Redirects the old url when the slug changed
            $model->update($id, $fields);
            $this->logChange(action: 'Updated', id: $id, name: $name);
        }

        $_SESSION['vehicle_saved'] = true;

        return ['success' => true, 'id' => $id];
    }

    /**
     * The form input as the columns it fills. Blank numbers become null, the description is cleaned of anything
     * outside the editor's allow-list, and the lists are stored as JSON.
     *
     * @return array<string, mixed>
     */
    private function fields(array $input): array
    {
        $make = \trim((string) ($input['make'] ?? ''));
        $model = \trim((string) ($input['model'] ?? ''));
        if ($make === '' || $model === '') throw new \InvalidArgumentException('Make and model are required');

        $status = (string) ($input['status'] ?? '');
        $status = \in_array($status, VehiclePresenter::STATUSES, true) ? $status : null;

        $features = \array_values(\array_filter(\array_map(static fn ($f) => \trim((string) $f), (array) ($input['features'] ?? []))));

        $specs = [];
        foreach ((array) ($input['specs'] ?? []) as $spec) {
            $label = \trim((string) ($spec['label'] ?? ''));
            $value = \trim((string) ($spec['value'] ?? ''));
            if ($label !== '' && $value !== '') $specs[] = ['label' => \mb_substr($label, 0, 60), 'value' => \mb_substr($value, 0, 120)];
        }

        return [
            'status' => $status,
            'make' => \mb_substr($make, 0, 100),
            'model' => \mb_substr($model, 0, 255),
            'year' => $this->wholeNumber($input['year'] ?? null),
            'registration' => $this->registration($input['registration'] ?? null),
            'mileage' => $this->wholeNumber($input['mileage'] ?? null),
            'price' => $this->money($input['price'] ?? null),
            'engine' => \mb_substr(\trim((string) ($input['engine'] ?? '')), 0, 100) ?: null,
            'transmission' => \mb_substr(\trim((string) ($input['transmission'] ?? '')), 0, 100) ?: null,
            'fuel' => \mb_substr(\trim((string) ($input['fuel'] ?? '')), 0, 50) ?: null,
            'description' => VehicleHtml::sanitize((string) ($input['description'] ?? '')) ?: null, // The editor's DOMPurify is only a courtesy
            'features' => $features ? \json_encode($features, JSON_UNESCAPED_UNICODE) : null,
            'specs' => $specs ? \json_encode($specs, JSON_UNESCAPED_UNICODE) : null,
            'is_published' => !empty($input['is_published']),
        ];
    }

    private function wholeNumber(mixed $value): ?int
    {
        $text = \trim((string) $value);
        if ($text === '') return null;
        if (!\ctype_digit($text)) throw new \InvalidArgumentException('Year and mileage must be whole numbers');

        return (int) $text;
    }

    /** Upper case with single spaces, so "ab12  cde" is stored as "AB12 CDE" */
    private function registration(mixed $value): ?string
    {
        $text = \mb_strtoupper(\trim((string) \preg_replace('/\s+/', ' ', (string) $value)));

        return $text === '' ? null : \mb_substr($text, 0, 20);
    }

    private function money(mixed $value): ?string
    {
        $text = \trim((string) $value);
        if ($text === '') return null;
        if (!\is_numeric($text) || (float) $text < 0) throw new \InvalidArgumentException('Price must be a number');

        return \number_format((float) $text, 2, '.', '');
    }

    /**
     * Soft delete: the page goes to the Pages bin and the vehicle row stays, so a restore brings it all back.
     * An optional redirect sends the old address to another page of the site until the vehicle is restored
     */
    private function deleteVehicle(int $id, string $redirectTarget): array
    {
        $this->bin()->delete($id, $redirectTarget);

        return ['success' => true];
    }

    private function restoreVehicle(int $id): array
    {
        $this->bin()->restore($id);

        return ['success' => true];
    }

    /**
     * Admins only, and only from the bin. The page goes for good too: a restored page would be an empty 404,
     * and a soft-deleted row would keep the address from being reused
     */
    private function permanentlyDeleteVehicle(int $id): array
    {
        if (!$this->isAdmin()) throw new \RuntimeException('Permission denied');

        $model = new Vehicle($this->osmium->dataSource);
        $vehicle = $model->getDeletedById($id);
        if (!$vehicle) throw new \InvalidArgumentException('Deleted vehicle not found');

        $images = new VehicleImage($this->osmium->dataSource);
        $files = new VehicleFiles();
        foreach ($images->listFor($id) as $image) {
            $files->remove((string) $image['path'], $_SERVER['DOCUMENT_ROOT']);
        }
        $files->removeFolder($id, $_SERVER['DOCUMENT_ROOT']);

        $model->delete($id); // The gallery rows go with it
        $this->pages()->permanentDelete((int) $vehicle['page_id']);
        $this->logChange(action: 'Permanently deleted', id: $id, name: VehiclePresenter::displayName($vehicle));

        return ['success' => true];
    }

    private function uploadImage(int $vehicleId, array $file): array
    {
        if (!(new Vehicle($this->osmium->dataSource))->getById($vehicleId)) throw new \InvalidArgumentException('Save the vehicle before adding photos');

        $maxUpload = \Osmium\Modules\Admin\Services\OsmiumAdmin::getMaxUploadSize();
        $path = (new VehicleFiles())->store(vehicleId: $vehicleId, file: $file, maxBytes: $maxUpload['bytes'], documentRoot: $_SERVER['DOCUMENT_ROOT']);
        $imageId = (new VehicleImage($this->osmium->dataSource))->add($vehicleId, $path);

        return ['success' => true, 'image' => ['id' => $imageId, 'path' => $path]];
    }

    private function deleteImage(int $vehicleId, int $imageId): array
    {
        $images = new VehicleImage($this->osmium->dataSource);
        $image = $images->getById($imageId);
        if (!$image || (int) $image['vehicle_id'] !== $vehicleId) throw new \InvalidArgumentException('Photo not found');

        (new VehicleFiles())->remove((string) $image['path'], $_SERVER['DOCUMENT_ROOT']);
        $images->delete($imageId);

        return ['success' => true];
    }

    private function reorderImages(int $vehicleId, array $order): array
    {
        (new VehicleImage($this->osmium->dataSource))->reorder($vehicleId, \array_map('intval', $order));

        return ['success' => true];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * @return array<int, array{title: string, url: string}>
     */
    private function linkTargets(): array
    {
        $trailingSlash = $this->osmium->config->site->urlTrailingSlash ?? false;
        $targets = [];
        foreach ((new Vehicle($this->osmium->dataSource))->linkTargets() as $page) {
            $isHome = \in_array($page['url'], ['', '/'], true);
            $targets[] = [
                'title' => (string) ($page['title'] ?: $page['url']),
                'url' => $isHome ? '/' : '/' . $page['url'] . ($trailingSlash ? '/' : ''),
            ];
        }

        return $targets;
    }

    /** The listing page, created the first time a vehicle is saved so vehicles have breadcrumbs to lead back to */
    private function listingPageId(): ?int
    {
        $settings = VehicleSettings::get();
        $vehicles = new Vehicle($this->osmium->dataSource);
        $existing = $vehicles->indexPageId($settings['listingSlug']);
        if ($existing !== null) return $existing;

        $binned = $vehicles->deletedIndexPageId($settings['listingSlug']); // Its address is still taken: bring it back rather than fail on the clash
        if ($binned !== null) {
            $this->pages()->restore($binned);

            return $binned;
        }

        return $this->pages()->create([
            'url' => $settings['listingSlug'],
            'title' => $settings['listingTitle'],
            'description' => '',
            'robots' => 'index,follow',
        ]);
    }

    private function pages(): AdminPages
    {
        return new AdminPages(
            page: $this->admin->model->page,
            urlTrailingSlash: $this->osmium->config->site->urlTrailingSlash ?? false,
        );
    }

    private function bin(): VehicleBin
    {
        return new VehicleBin(new Vehicle($this->osmium->dataSource), $this->pages(), $this->logChange(...));
    }

    private function formatVehicle(array $row): array
    {
        $trailingSlash = $this->osmium->config->site->urlTrailingSlash ?? false;
        $status = (string) ($row['status'] ?? '');
        $isPublished = (bool) $row['is_published'];

        return $row + [
            'name' => VehiclePresenter::displayName($row),
            'url_path' => '/' . $row['url'] . ($trailingSlash ? '/' : ''),
            'edit_url' => $this->getBasePath() . 'vehicles/edit/?id=' . (int) $row['id'],
            'status_label' => $isPublished ? ($status ?: 'Unset') : 'Hidden',
            'status_class' => $isPublished ? match ($status) {
                'For Sale' => 'success',
                'Reserved' => 'warning',
                'Sold' => 'danger',
                default => 'secondary',
            } : 'secondary',
            'price_label' => isset($row['price']) ? VehiclePresenter::money($row['price']) : '-',
            'price_sort' => (float) ($row['price'] ?? 0),
            'date_sort' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private function formatRecent(array $row): array
    {
        $changed = \max($row['updated_at'] ?? '', $row['deleted_at'] ?? '', $row['created_at']);

        return $this->formatVehicle($row) + ['updated_label' => \date('j M Y, g:ia', \strtotime((string) $changed))];
    }

    private function logChange(string $action, int $id, string $name): void
    {
        $this->admin->model->changelog->log(
            description: $action . ': ' . $name,
            recordType: self::RECORD_TYPE,
            recordId: $id,
        );
    }
}
