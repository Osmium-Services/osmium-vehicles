<?php

declare(strict_types=1);

/**
 * Grid (card) layout for a vehicle's Photos list, rendered by Osmium\Modules\Admin\Core\ListViewGrid. Columns use the
 * same vocabulary as lists/vehicle-photos.php but nothing is shared with it. Delete is the card's red corner button; Edit photo opens the viewer where it can be turned.
 */
return [
    'only' => true, // Cards only: no List view for photos
    'columns' => 5,
    'imageHeight' => 120,
    'image' => ['key' => 'path', 'type' => 'image', 'altKey' => 'alt'],
    'meta' => [
        ['key' => 'coverBadge', 'type' => 'raw'],
    ],
    'actions' => [
        ['label' => 'Edit photo', 'icon' => 'bx-zoom-in', 'event' => 'view_photo'],
    ],
    'cornerAction' => [
        'label' => 'Delete',
        'icon' => 'bx-trash',
        'action' => 'delete_photo',
        'confirm' => 'Delete this photo? This cannot be undone.',
        'danger' => true,
    ],
];
