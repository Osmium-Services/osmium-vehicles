<?php

declare(strict_types=1);

/**
 * Grid (card) layout for the Vehicles list, rendered by Osmium\Modules\Admin\Core\ListViewGrid. Columns use the
 * same vocabulary as lists/vehicles.php but nothing is shared with it. The actions are a copy of the list's for now.
 */
return [
    'columns' => 4,
    'imageHeight' => 130,
    'image' => ['key' => 'image', 'type' => 'image', 'altKey' => 'name'],
    'title' => [
        'key' => 'name',
        'type' => 'text',
        'strong' => true,
        'fallback' => 'Untitled',
    ],
    'meta' => [
        ['key' => 'price_label', 'type' => 'text', 'strong' => true],
        ['key' => 'status_label', 'type' => 'badge', 'text' => true, 'classKey' => 'status_class', 'classPrefix' => 'bg-label-'],
    ],
    'actionsAt' => 'bottom-end',
    'actions' => [
        ['label' => 'Edit', 'icon' => 'bx-edit-alt', 'url' => '{edit_url}'],
        ['label' => 'View on site', 'icon' => 'bx-link-external', 'url' => '{url_path}', 'target' => '_blank'],
        ['label' => 'Delete', 'icon' => 'bx-trash', 'event' => 'delete', 'danger' => true],
    ],
];
