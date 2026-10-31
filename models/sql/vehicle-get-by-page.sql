SELECT v.id, v.page_id, v.status, v.make, v.model, v.year, v.registration, v.mileage, v.price, v.engine, v.transmission, v.fuel, v.description, v.features, v.specs, v.is_published, v.created_at, v.updated_at
FROM {TABLE} v
WHERE v.page_id = :page_id
LIMIT 1
