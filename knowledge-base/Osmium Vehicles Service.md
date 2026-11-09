# Osmium Vehicles Service

Repo `Osmium-Services/osmium-vehicles`, id `osmium-vehicles`, v0.1.9<!--v:osmium-vehicles-->, category content. Built 2026-10-31 from
Vale Motorcaravans' vehicles feature so VMC can be brought onto the current core. Same shape as [[Blog Service (design)]].

## What it is

Vehicles for sale (cars, caravans, motorhomes, vans) written in admin and published as ordinary pages. Nothing in it is
caravan-specific: VMC's `berths` column became the free-form **extra specs** list (label and value pairs), so "Berths: 4" is one entry.

- **Fields**: make, model, year, registration (optional, stored upper case), status (For Sale, Reserved, Sold or unset), price (GBP),
  mileage, engine, transmission, fuel, description (limited HTML), features (list), extra specs (list), shown on the website yes/no.
- **Photos**: any number per vehicle, uploaded in the editor (JPG, PNG, WebP, AVIF), scaled to 1600 wide and saved as WebP under
  `assets/images/vehicles/<id>/`. Drag to reorder, the first is the cover. Permanent delete removes the files.
- **URLs**: a Pages row per vehicle at `<listing slug>/<slug>` (default listing slug `vehicles`, changed in Osmium Vehicles settings;
  VMC would use `stock` so its existing addresses survive). The listing page is created when the first vehicle is saved, or an existing
  page at that address is reused. A changed slug redirects the old address. Delete goes to the Pages bin with an optional redirect.
- **Hidden vehicles** return 404 to visitors and preview for a signed-in admin (`$vehicleIsPreview`), and their page is noindex.

## Split of responsibility

The service owns the tables (`vehicles`, `vehicle_images`), admin, claim and schema. The theme supplies every view; see the Vehicles
rows in `THEME-CONTRACT.md`. "Latest vehicles" for a home page: `(new Vehicle($this->osmium->dataSource))->latest(3)` returns the same
shaped cards as the index. The raw `specs` column is replaced by the computed spec list (Registration, Mileage, Engine, Transmission,
Fuel when set, then the custom ones) in every shaped vehicle.

## Tested (2026-10-31, dev site)

Save, description sanitising, photo upload, reorder and remove, delete with redirect, restore, permanent delete (files and rows),
hidden vehicle 404 and admin preview, validation errors, registration normalising; 8 unit tests. Not tested: a Store install, a real
theme's views, the Pages list "Managed by Vehicles" marker, EXIF rotation (no exif extension locally).

## Not built

Paging on the listing, filters, MCP tools, multiple currencies (GBP only), a draft/scheduled state beyond hidden.

## Moving VMC onto it

VMC runs an older Osmium (no services, no Store, admin as a plain module), so it must first be brought onto the current core.
Then a one-off script moves its `vmc_vehicles`, `vmc_vehicle_images` and `pages.vehicle_id` links across: each vehicle keeps its pages
row, berths becomes an extra spec, and photo paths change from `vehicles/<id>/<file>` (under `public/assets/images/`) to
`/assets/images/vehicles/<id>/<file>`. VMC's per-vehicle `stock/<slug>` module folders and its `stock/vehicle` module go, replaced by
theme views.
