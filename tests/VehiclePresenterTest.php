<?php

declare(strict_types=1);

namespace Tests\Services\Vehicles;

use Osmium\Services\Vehicles\Models\VehicleFiles;
use Osmium\Services\Vehicles\Models\VehicleHtml;
use Osmium\Services\Vehicles\Models\VehiclePresenter;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/VehiclePresenter.php';
require_once __DIR__ . '/../models/VehicleHtml.php';
require_once __DIR__ . '/../models/VehicleFiles.php';

/**
 * What a theme is handed for a vehicle, and the clean-up of what an admin types.
 */
final class VehiclePresenterTest extends TestCase
{
    private function row(array $overrides = []): array
    {
        return $overrides + [
            'status' => 'For Sale', 'make' => 'Fiat', 'registration' => 'AB12 CDE', 'model' => 'Roller Team', 'year' => 2014, 'mileage' => 21000,
            'price' => '34950.00', 'engine' => '2.3 litre', 'transmission' => '', 'fuel' => 'Diesel',
            'specs' => '[{"label":"Berths","value":"6"},{"label":"","value":"x"}]', 'url' => 'stock/fiat', 'image' => null,
        ];
    }

    public function test_display_name_adds_the_year_only_when_there_is_one(): void
    {
        $this->assertSame('Fiat Roller Team (2014)', VehiclePresenter::displayName($this->row()));
        $this->assertSame('Fiat Roller Team', VehiclePresenter::displayName($this->row(['year' => null])));
    }

    public function test_specs_list_built_in_fields_then_custom_ones_and_skips_blanks(): void
    {
        $specs = VehiclePresenter::specs($this->row());

        $this->assertSame(
            [['label' => 'Registration', 'value' => 'AB12 CDE'], ['label' => 'Mileage', 'value' => '21,000'], ['label' => 'Engine', 'value' => '2.3 litre'], ['label' => 'Fuel', 'value' => 'Diesel'], ['label' => 'Berths', 'value' => '6']],
            $specs,
        );
    }

    public function test_card_replaces_the_raw_specs_column_with_the_list(): void
    {
        $card = VehiclePresenter::card($this->row());

        $this->assertIsArray($card['specs']);
        $this->assertSame('stock/fiat', $card['address']);
    }

    public function test_price_shows_only_when_for_sale_otherwise_the_status(): void
    {
        $this->assertSame('£34,950', VehiclePresenter::card($this->row())['priceDisplay']);
        $this->assertTrue(VehiclePresenter::card($this->row())['isForSale']);

        $sold = VehiclePresenter::card($this->row(['status' => 'Sold']));
        $this->assertSame('SOLD', $sold['priceDisplay']);
        $this->assertFalse($sold['isForSale']);

        $noPrice = VehiclePresenter::card($this->row(['price' => null]));
        $this->assertFalse($noPrice['isForSale']);
    }

    public function test_detail_takes_features_and_the_first_photo_as_the_cover(): void
    {
        $detail = VehiclePresenter::detail($this->row(['features' => '["Solar panel"]']), [['path' => '/a.webp'], ['path' => '/b.webp']]);

        $this->assertSame(['Solar panel'], $detail['features']);
        $this->assertSame('/a.webp', $detail['image']);
        $this->assertSame(['/a.webp', '/b.webp'], $detail['images']);
    }

    public function test_decode_list_survives_bad_json(): void
    {
        $this->assertSame([], VehiclePresenter::decodeList('not json'));
        $this->assertSame([], VehiclePresenter::decodeList(null));
    }

    public function test_description_clean_up_keeps_text_formatting_and_drops_anything_that_runs(): void
    {
        $clean = VehicleHtml::sanitize('<p>Nice <strong>van</strong></p><script>alert(1)</script><p onclick="x()"><a href="javascript:alert(1)">bad</a> <img src="x.png"></p>');

        $this->assertSame('<p>Nice <strong>van</strong></p><p><a>bad</a> </p>', $clean);
    }

    public function test_only_photos_in_the_vehicle_folders_count_as_ours(): void
    {
        $this->assertTrue(VehicleFiles::isVehicleImage('/assets/images/vehicles/12/ab12.webp'));
        $this->assertTrue(VehicleFiles::isVehicleImage('/assets/images/vehicles/12/photo-1.jpg'));
        $this->assertFalse(VehicleFiles::isVehicleImage('/assets/images/vehicles/../../config.png'));
        $this->assertFalse(VehicleFiles::isVehicleImage('/assets/images/blog/a.webp'));
    }
}
