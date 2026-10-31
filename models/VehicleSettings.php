<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Models;

/**
 * This service's settings, read from app/config/services/osmium-vehicles.json.php (the file-based convention the
 * other services use). Missing file means the defaults: the listing lives at /vehicles.
 */
class VehicleSettings
{
    public const CONFIG_PATH = 'app/config/services/osmium-vehicles.json.php';

    private static ?array $settings = null;

    public static function get(): array
    {
        if (self::$settings !== null) return self::$settings;

        $settings = self::defaults();

        if (\file_exists(self::CONFIG_PATH)) {
            $content = (string) \file_get_contents(self::CONFIG_PATH);
            $jsonStart = \strpos(haystack: $content, needle: '{');
            $stored = $jsonStart === false ? null : \json_decode(\substr(string: $content, offset: $jsonStart), associative: true);

            if (\is_array($stored['vehicles'] ?? null)) {
                $settings = \array_merge($settings, \array_intersect_key($stored['vehicles'], $settings));
            }
        }

        $settings['listingSlug'] = self::cleanSlug((string) $settings['listingSlug']) ?: self::defaults()['listingSlug'];
        $settings['listingTitle'] = \trim((string) $settings['listingTitle']) ?: self::defaults()['listingTitle'];

        return self::$settings = $settings;
    }

    /**
     * @param string $listingSlug address of the page listing every vehicle, and the prefix of each vehicle's address
     * @param string $listingTitle title given to that page when the first vehicle is saved
     */
    public static function save(string $listingSlug, string $listingTitle): void
    {
        $dir = \dirname(self::CONFIG_PATH);
        if (!\is_dir($dir)) \mkdir(directory: $dir, permissions: 0755, recursive: true);

        $data = ['vehicles' => ['listingSlug' => self::cleanSlug($listingSlug), 'listingTitle' => \trim($listingTitle)]];

        $json = \json_encode(value: $data, flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        \file_put_contents(self::CONFIG_PATH, "<?php exit(); ?>\n" . $json . "\n");

        self::$settings = null;
    }

    public static function cleanSlug(string $text): string
    {
        $slug = \preg_replace('/[^a-z0-9]+/', '-', \strtolower(\trim($text))) ?? '';

        return \trim($slug, '-');
    }

    private static function defaults(): array
    {
        return ['listingSlug' => 'vehicles', 'listingTitle' => 'Vehicles'];
    }
}
