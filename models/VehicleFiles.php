<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Models;

/**
 * Stores vehicle photos: checks the real file type, applies the camera's rotation, scales anything wider than
 * MAX_WIDTH down, and saves one WebP under assets/images/vehicles/<vehicle id>/. GIF is refused because GD would
 * flatten an animation.
 */
class VehicleFiles
{
    public const URL_PREFIX = '/assets/images/vehicles/';
    private const MAX_WIDTH = 1600;
    private const WEBP_QUALITY = 82;

    private const DECODERS = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
        'image/avif' => 'imagecreatefromavif',
    ];

    /**
     * @param array{tmp_name?: string, name?: string, error?: int, size?: int} $file one entry of $_FILES
     * @return string the public path of the stored photo
     */
    public function store(int $vehicleId, array $file, int $maxBytes, string $documentRoot): string
    {
        $uploadFailed = !isset($file['error']) || $file['error'] !== \UPLOAD_ERR_OK;
        if ($uploadFailed) throw new \InvalidArgumentException('No file uploaded');

        $tooLarge = ($file['size'] ?? 0) > $maxBytes;
        if ($tooLarge) throw new \InvalidArgumentException('File too large');

        $mimeType = (new \finfo(\FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $decoder = self::DECODERS[$mimeType] ?? null;
        if ($decoder === null) throw new \InvalidArgumentException('Use a JPG, PNG, WebP or AVIF image');
        if (!\function_exists($decoder) || !\function_exists('imagewebp')) {
            throw new \InvalidArgumentException("This server can't process {$mimeType} images. Try JPG or PNG instead.");
        }

        $source = $decoder($file['tmp_name']);
        if (!$source) throw new \InvalidArgumentException('That image could not be read');

        if ($mimeType === 'image/jpeg') $source = $this->applyOrientation($source, $file['tmp_name']);

        $image = $this->scaleDown($source);

        $urlDir = self::URL_PREFIX . $vehicleId . '/';
        $directory = \rtrim($documentRoot, '/') . $urlDir;
        if (!\is_dir($directory) && !\mkdir($directory, 0755, true) && !\is_dir($directory)) {
            throw new \RuntimeException('Could not create the vehicle image folder');
        }

        $filename = \bin2hex(\random_bytes(8)) . '.webp';
        $saved = \imagewebp($image, $directory . $filename, self::WEBP_QUALITY);
        if (!$saved) throw new \RuntimeException('Could not save the image');

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

    private function scaleDown(\GdImage $source): \GdImage
    {
        if (\imagesx($source) <= self::MAX_WIDTH) return $this->keepTransparency($source);

        $scaled = \imagescale($source, self::MAX_WIDTH, -1, \IMG_BICUBIC);

        return $scaled ? $this->keepTransparency($scaled) : $source;
    }

    private function keepTransparency(\GdImage $image): \GdImage
    {
        \imagepalettetotruecolor($image);
        \imagealphablending($image, false);
        \imagesavealpha($image, true);

        return $image;
    }

    private function applyOrientation(\GdImage $image, string $path): \GdImage
    {
        if (!\function_exists('exif_read_data')) return $image;

        $exif = @\exif_read_data($path);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) return $image;

        return \imagerotate($image, $angle, 0) ?: $image;
    }
}
