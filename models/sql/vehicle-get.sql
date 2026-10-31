SELECT
    v.id, v.page_id, v.status, v.make, v.model, v.year, v.registration, v.mileage, v.price, v.engine, v.transmission, v.fuel, v.description, v.features, v.specs, v.is_published, v.created_at, v.updated_at,
    pg.url,
    pg.title,
    pg.description AS meta_description,
    pg.robots
FROM {TABLE} v
JOIN {PREFIX}pages pg ON pg.id = v.page_id AND pg.deleted_at IS NULL
WHERE v.id = :id
LIMIT 1
