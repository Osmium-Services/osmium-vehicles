SELECT
    v.id, v.make, v.model, v.year, v.status, v.price, v.mileage, v.is_published, v.created_at,
    pg.url,
    CHAR_LENGTH(TRIM(COALESCE(v.description, ''))) AS description_length,
    (SELECT COUNT(*) FROM {PREFIX}vehicle_images vi WHERE vi.vehicle_id = v.id) AS image_count
FROM {TABLE} v
JOIN {PREFIX}pages pg ON pg.id = v.page_id AND pg.deleted_at IS NULL
ORDER BY v.created_at DESC, v.id DESC
