<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Models;

/**
 * Vehicles' tool for the /mcp endpoint, offered through core's `mcp.tools` hook: stock health for the vehicles for sale.
 * Read only, and it never returns a registration, description text, features or specs: counts, names, years, statuses
 * and page addresses only.
 */
class VehiclesMcp
{
    private const LIST_CAP = 25;
    private const STATUS_CAP = 30;
    private const SHORT_DESCRIPTION = 100;
    private const OLD_DAYS = 90;

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>> tool descriptors
     */
    public static function tools(array $payload): array
    {
        return [
            [
                'name' => 'vehicles_attention',
                'description' => 'Stock health for the vehicles for sale: how many are live, published and hidden (by status too), then the published vehicles that are not sold and need attention, each list with a total and up to 25 vehicles (id, name, year, status, page address, days listed): no photos, a photo file missing on disk, no price, a description under 100 characters, no year, no mileage, and listed over 90 days. Sold vehicles are left out of these lists (they still count in the totals). Binned vehicles are left out. Read only. It never returns registrations, description text, features or specs.',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()],
                'handler' => self::class . '::attention',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function attention(array $call): array
    {
        $vehicles = new Vehicle($call['dataSource']);
        $rows = $vehicles->attentionRows();
        $missing = self::missingPhotoIds($vehicles->publishedImagePaths(), (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));

        return self::shape($rows, $missing, \time());
    }

    /**
     * @param array<int, array<string, mixed>> $rows from vehicle-attention.sql
     * @param array<int, int> $missingPhotoIds ids of vehicles with at least one photo file missing on disk
     * @return array<string, mixed>
     */
    public static function shape(array $rows, array $missingPhotoIds, int $now): array
    {
        $missing = \array_flip($missingPhotoIds);
        $problems = [];
        $published = 0;
        $forSale = 0;
        $byStatus = [];

        foreach ($rows as $row) {
            $status = self::status($row['status'] ?? null);
            $key = $status ?? 'none';
            $byStatus[$key] = ($byStatus[$key] ?? 0) + 1;
            if ((int) $row['is_published'] !== 1) continue;

            $published++;
            if (self::isSold($row)) continue;

            $forSale++;
            $entry = self::entry($row, $now);
            foreach (self::flags($row, $entry, isset($missing[(int) $row['id']])) as $problem) {
                $problems[$problem][] = $entry;
            }
        }

        return [
            'counts' => ['live' => \count($rows), 'published' => $published, 'published_for_sale' => $forSale, 'hidden' => \count($rows) - $published, 'by_status' => $byStatus],
            'published_problems' => self::capped($problems),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $imagePaths vehicle_id and path of each photo
     * @return array<int, int>
     */
    private static function missingPhotoIds(array $imagePaths, string $documentRoot): array
    {
        $root = \rtrim($documentRoot, '/');
        $missing = [];

        foreach ($imagePaths as $image) {
            $found = \is_file($root . (string) $image['path']);
            if (!$found) $missing[(int) $image['vehicle_id']] = (int) $image['vehicle_id'];
        }

        return \array_values($missing);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function isSold(array $row): bool
    {
        return \stripos((string) ($row['status'] ?? ''), 'sold') !== false;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $entry
     * @return array<int, string> the problem names that apply
     */
    private static function flags(array $row, array $entry, bool $photoMissing): array
    {
        $price = $row['price'] ?? null;
        $tests = [
            'no_photos' => (int) $row['image_count'] === 0,
            'photo_file_missing' => $photoMissing,
            'no_price' => $price === null || (float) $price === 0.0,
            'short_description' => (int) $row['description_length'] < self::SHORT_DESCRIPTION,
            'no_year' => ($row['year'] ?? null) === null,
            'no_mileage' => ($row['mileage'] ?? null) === null,
            'listed_over_90_days' => $entry['days_listed'] > self::OLD_DAYS,
        ];

        return \array_keys(\array_filter($tests));
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, name: string, year: int|null, status: string|null, url: string, days_listed: int}
     */
    private static function entry(array $row, int $now): array
    {
        $created = \strtotime((string) ($row['created_at'] ?? ''));
        $days = $created === false ? 0 : \max(0, \intdiv($now - $created, 86400));

        return [
            'id' => (int) $row['id'],
            'name' => \trim(\trim((string) ($row['make'] ?? '')) . ' ' . \trim((string) ($row['model'] ?? ''))),
            'year' => isset($row['year']) ? (int) $row['year'] : null,
            'status' => self::status($row['status'] ?? null),
            'url' => (string) ($row['url'] ?? ''),
            'days_listed' => $days,
        ];
    }

    private static function status(mixed $status): ?string
    {
        $text = \trim((string) $status);
        if ($text === '') return null;

        return \mb_substr($text, 0, self::STATUS_CAP);
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $problems
     * @return array<string, array{total: int, vehicles: array<int, array<string, mixed>>}>
     */
    private static function capped(array $problems): array
    {
        $names = ['no_photos', 'photo_file_missing', 'no_price', 'short_description', 'no_year', 'no_mileage', 'listed_over_90_days'];
        $result = [];

        foreach ($names as $name) {
            $list = $problems[$name] ?? [];
            $result[$name] = ['total' => \count($list), 'vehicles' => \array_slice($list, 0, self::LIST_CAP)];
        }

        return $result;
    }
}
