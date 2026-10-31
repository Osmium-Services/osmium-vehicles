<?php

declare(strict_types=1);

namespace Osmium\Services\Vehicles\Models;

/**
 * Builds a vehicle's IndividualProduct JSON-LD (priced in GBP, used condition), so a theme prints one tag.
 */
class VehicleSchema
{
    /**
     * @param array<string, mixed> $vehicle a shaped vehicle from VehiclePresenter::detail()
     * @param array<string, mixed> $data the page data core hands a claim (site, page, template)
     */
    public static function script(array $vehicle, array $data): string
    {
        $site = $data['site'] ?? [];
        $fqdn = (string) ($site['FQDN'] ?? '');
        $pageUrl = (string) ($site['fullURL'] ?? '');
        $inStock = ($vehicle['status'] ?? '') === VehiclePresenter::FOR_SALE;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'IndividualProduct',
            'name' => $vehicle['displayName'],
            'brand' => ['@type' => 'Brand', 'name' => (string) $vehicle['make']],
            'description' => \trim((string) \preg_replace('/\s+/', ' ', \strip_tags((string) ($vehicle['description'] ?? '')))),
            'image' => \array_map(fn (string $path) => $fqdn . $path, $vehicle['images'] ?? []),
            'url' => $pageUrl,
            'offers' => [
                '@type' => 'Offer',
                'url' => $pageUrl,
                'priceCurrency' => 'GBP',
                'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/UsedCondition',
            ],
        ];

        if (($vehicle['price'] ?? null) !== null) $schema['offers']['price'] = (string) (float) $vehicle['price'];

        $properties = [];
        foreach ($vehicle['specs'] ?? [] as $spec) {
            $properties[] = ['@type' => 'PropertyValue', 'name' => $spec['label'], 'value' => $spec['value']];
        }
        if ($properties) $schema['additionalProperty'] = $properties;

        $schema = \array_filter($schema, fn ($value) => $value !== '' && $value !== []);

        // HEX_TAG keeps a description containing "</script>" from ending the tag early
        $json = \json_encode($schema, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP);

        return '<script type="application/ld+json">' . $json . '</script>';
    }
}
