<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Models;

/**
 * Turns a vehicle row into what a theme prints. Pure.
 *
 * Every shaped vehicle carries: displayName ("Make Model (Year)"), isForSale, priceDisplay (the price, or the
 * upper-cased status when there is no price to show), address (no leading slash), image (first photo path or null),
 * features (list of strings) and specs (list of label/value pairs: Registration, Mileage, Engine, Transmission and Fuel when set,
 * then the custom ones typed in admin).
 */
class VehiclePresenter
{
    public const FOR_SALE = 'For Sale';
    public const STATUSES = ['For Sale', 'Reserved', 'Sold'];

    /** A row from a list query */
    public static function card(array $row): array
    {
        $isForSale = ($row['status'] ?? null) === self::FOR_SALE && ($row['price'] ?? null) !== null;

        return \array_merge($row, [
            'displayName' => self::displayName($row),
            'isForSale' => $isForSale,
            'priceDisplay' => $isForSale ? self::money($row['price']) : \strtoupper((string) ($row['status'] ?? '')),
            'address' => $row['url'] ?? '',
            'specs' => self::specs($row),
        ]);
    }

    /** A full row plus its photos (rows from VehicleImage::listFor) */
    public static function detail(array $row, array $images): array
    {
        $card = self::card($row);
        $card['features'] = self::decodeList($row['features'] ?? null);
        $card['images'] = \array_column($images, 'path');
        $card['image'] = $card['images'][0] ?? null;

        return $card;
    }

    public static function displayName(array $row): string
    {
        $name = \trim(($row['make'] ?? '') . ' ' . ($row['model'] ?? ''));
        $year = $row['year'] ?? null;

        return $year ? "{$name} ({$year})" : $name;
    }

    /** @return array<int, array{label: string, value: string}> */
    public static function specs(array $row): array
    {
        $specs = [];
        $registration = \trim((string) ($row['registration'] ?? ''));
        if ($registration !== '') $specs[] = ['label' => 'Registration', 'value' => $registration];
        $mileage = $row['mileage'] ?? null;
        if ($mileage !== null && $mileage !== '') $specs[] = ['label' => 'Mileage', 'value' => \number_format((int) $mileage)];
        foreach (['engine' => 'Engine', 'transmission' => 'Transmission', 'fuel' => 'Fuel'] as $key => $label) {
            $value = \trim((string) ($row[$key] ?? ''));
            if ($value !== '') $specs[] = ['label' => $label, 'value' => $value];
        }

        foreach (self::decodeList($row['specs'] ?? null) as $custom) {
            if (!\is_array($custom)) continue;
            $label = \trim((string) ($custom['label'] ?? ''));
            $value = \trim((string) ($custom['value'] ?? ''));
            if ($label !== '' && $value !== '') $specs[] = ['label' => $label, 'value' => $value];
        }

        return $specs;
    }

    public static function money(mixed $price): string
    {
        return '£' . \number_format((float) $price);
    }

    /** @return array<int, mixed> */
    public static function decodeList(?string $json): array
    {
        $list = \json_decode((string) $json, true);

        return \is_array($list) ? \array_values($list) : [];
    }
}
