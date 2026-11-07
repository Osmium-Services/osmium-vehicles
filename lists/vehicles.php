<?php

declare(strict_types=1);

use Osmium\Modules\Admin\Core\ListView;

/**
 * Vehicles list, rendered by Osmium\Modules\Admin\Core\ListView. Rows are shaped by VehicleController::formatVehicle().
 */
require_once 'app/core/admin/services/ChangedByCell.php';

$changedBy = ['key' => 'changed_by', 'label' => '', 'type' => 'html', 'sortable' => false, 'render' => fn(array $row, array $context): string => \Osmium\Modules\Admin\Services\ChangedByCell::html($row, (string)($context['avatarUrl'] ?? ''))];

$statusBadge = [
    'key' => 'status_label',
    'label' => 'Status',
    'type' => 'badge',
    'text' => true,
    'classKey' => 'status_class',
    'classPrefix' => 'bg-label-',
];

return [
    'recent' => [
        'id' => 'vehicles-recent',
        'title' => 'Recent Changes',
        'noun' => ['vehicle', 'vehicles'],
        'icon' => 'bx-time-five',
        'rowClass' => static fn(array $row): string => empty($row['deleted_at']) ? '' : 'table-danger',
        'endpoint' => 'vehicles/action/',
        'collapsible' => ['label' => 'Recent Changes ({count})'],
        'hideWhenEmpty' => false,
        'columns' => [
            $changedBy,
            ['key' => 'name', 'label' => 'Vehicle', 'type' => 'text', 'strong' => true, 'fallback' => 'Untitled', 'fill' => true],
            $statusBadge,
            ['key' => 'updated_label', 'label' => 'Updated', 'type' => 'text', 'small' => true, 'nowrap' => true],
        ],
    ],

    'vehicles' => [
        'id' => 'vehicles',
        'title' => 'Vehicles',
        'noun' => ['vehicle', 'vehicles'],
        'endpoint' => 'vehicles/action/',
        'emptyText' => 'No vehicles yet. Use New Vehicle to add the first.',
        'order' => null, // keep the server's newest-first order until a header is clicked
        'grid' => require __DIR__ . '/../grids/vehicles.php',
        'columns' => [
            ['key' => 'image', 'label' => 'Image', 'type' => 'image', 'altKey' => 'name', 'sortable' => false, 'hideBelow' => 'md'],
            [
                'key' => 'name',
                'label' => 'Vehicle',
                'type' => 'text',
                'strong' => true,
                'fallback' => 'Untitled',
                'fill' => true,
                'sub' => ['key' => 'url_path', 'style' => 'link', 'href' => '{url_path}'],
            ],
            $statusBadge,
            ['key' => 'price_label', 'label' => 'Price', 'type' => 'text', 'nowrap' => true, 'sortKey' => 'price_sort'],
        ],
        'actions' => [
            ['label' => 'Edit', 'icon' => 'bx-edit-alt', 'url' => '{edit_url}'],
            ['label' => 'View on site', 'icon' => 'bx-link-external', 'url' => '{url_path}', 'target' => '_blank'],
            ['label' => 'Delete', 'icon' => 'bx-trash', 'event' => 'delete', 'danger' => true],
        ],
    ],

    'deleted' => ListView::deletedPreset(
        definition: [
            'id' => 'vehicles-deleted',
            'title' => 'Deleted Vehicles',
            'noun' => ['vehicle', 'vehicles'],
            'endpoint' => 'vehicles/action/',
            'hideWhenEmpty' => false,
            'columns' => [
                $changedBy,
                [
                    'key' => 'name',
                    'label' => 'Vehicle',
                    'type' => 'text',
                    'strong' => true,
                    'fallback' => 'Untitled',
                    'fill' => true,
                    'sub' => ['key' => 'url_path', 'style' => 'plain'],
                ],
            ],
        ],
        permanentConfirm: 'Permanently delete this vehicle, its photos and its page? This cannot be undone!',
    ),
];
