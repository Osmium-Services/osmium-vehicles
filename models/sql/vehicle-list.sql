SELECT
    v.id, v.page_id, v.status, v.make, v.model, v.year, v.mileage, v.price, v.is_published, v.created_at, v.updated_at,
    pg.url,
    pg.title,
    (SELECT vi.path FROM {PREFIX}vehicle_images vi WHERE vi.vehicle_id = v.id ORDER BY vi.position ASC, vi.id ASC LIMIT 1) AS image
FROM {TABLE} v
JOIN {PREFIX}pages pg ON pg.id = v.page_id AND pg.deleted_at IS NULL
ORDER BY v.created_at DESC, v.id DESC
