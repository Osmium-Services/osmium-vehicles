<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\Vehicles\Models\VehicleSettings;

/**
 * Vehicles settings - the address and title of the listing page.
 *
 * Routes:
 *   - index() → /admin/settings/vehicles/  (GET shows the form, POST saves it)
 */
class SettingsController extends AdminController
{
    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->handleSubmit();

        $this->data['admin']['settings'] = VehicleSettings::get();
        $this->data['admin']['settingsSaved'] = $_SESSION['vehicles_settings_saved'] ?? false;
        $this->data['admin']['settingsError'] = $_SESSION['vehicles_settings_error'] ?? false;
        unset($_SESSION['vehicles_settings_saved'], $_SESSION['vehicles_settings_error']);

        $this->setView('vehicles/settings.phtml');
    }

    private function handleSubmit(): void
    {
        if (!$this->admin->auth->validateCsrf()) {
            $_SESSION['vehicles_settings_error'] = 'Invalid form submission. Please try again.';
            $this->redirect('settings/vehicles/');
        }

        $slug = VehicleSettings::cleanSlug((string) ($_POST['listing_slug'] ?? ''));
        if ($slug === '') {
            $_SESSION['vehicles_settings_error'] = 'The listing address cannot be empty.';
            $this->redirect('settings/vehicles/');
        }

        VehicleSettings::save(listingSlug: $slug, listingTitle: (string) ($_POST['listing_title'] ?? ''));

        $this->admin->model->changelog->log(
            description: 'Updated Vehicles settings',
            recordType: 'settings',
        );

        $_SESSION['vehicles_settings_saved'] = true;
        $this->redirect('settings/vehicles/');
    }
}
