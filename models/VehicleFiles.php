<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Models;

use Osmium\Core\Library\ImageTools;

/**
 * Stores vehicle photos as one WebP under assets/images/vehicles/<vehicle id>/. The checking, rotation and scaling
 * are core's ImageTools; this class picks the folder and the file name, and removes what it stored.
 */
class VehicleFiles
{
    public const URL_PREFIX = '/assets/images/vehicles/';
    private const MAX_WIDTH = 1600;
    private const WEBP_QUALITY = 82;

    /**
     * @param array{tmp_name?: string, name?: string, error?: int, size?: int} $file one entry of $_FILES
     * @return string the public path of the stored photo
     */
    public function store(int $vehicleId, array $file, int $maxBytes, string $documentRoot): string
    {
        $urlDir = self::URL_PREFIX . $vehicleId . '/';
        $filename = \bin2hex(\random_bytes(8)) . '.webp';

        (new ImageTools())->storeAsWebp(
            file: $file,
            maxBytes: $maxBytes,
            path: \rtrim($documentRoot, '/') . $urlDir . $filename,
            maxWidth: self::MAX_WIDTH,
            quality: self::WEBP_QUALITY,
        );

        return $urlDir . $filename;
    }

    /** Removes one stored photo. Only files this service stored are touched. */
    public function remove(string $path, string $documentRoot): void
    {
        if (!self::isVehicleImage($path)) return;

        $file = \rtrim($documentRoot, '/') . $path;
        if (\is_file($file)) @\unlink($file);
    }

    /** Removes a vehicle's photo folder once its photos are gone (nothing happens if files are left) */
    public function removeFolder(int $vehicleId, string $documentRoot): void
    {
        @\rmdir(\rtrim($documentRoot, '/') . self::URL_PREFIX . $vehicleId);
    }

    /** Whether a stored path is one of ours (also true for photos imported into the same folder, any web format) */
    public static function isVehicleImage(string $path): bool
    {
        return \preg_match('#^' . \preg_quote(self::URL_PREFIX, '#') . '\d+/[A-Za-z0-9._-]+\.(webp|jpe?g|png|avif)$#', $path) === 1;
    }
}
