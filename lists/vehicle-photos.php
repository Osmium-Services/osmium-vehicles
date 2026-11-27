<?php

declare(strict_types=1);

/**
 * The Photos list on a vehicle's edit page, rendered by ListView. Row fields come from VehicleController::photoRows().
 * Cards only (no List view). Its drag handle posts 'reorder_photo' (the vehicles list has its own 'reorder'); the first photo is the cover.
 */
return [
    'id' => 'vehicle-photos',
    'title' => 'Photos',
    'noun' => ['photo', 'photos'],
    'endpoint' => 'vehicles/action/',
    'note' => 'Shown in this order. The first photo is the cover.',
    'emptyText' => 'No photos yet - drop some above.',
    'order' => null, // Server order: display order
    'orderable' => 'reorder_photo',
    'grid' => require __DIR__ . '/../grids/vehicle-photos.php',
    'columns' => [
        ['key' => 'path', 'label' => '', 'type' => 'image', 'altKey' => 'alt', 'fill' => true, 'sortable' => false],
    ],
];
