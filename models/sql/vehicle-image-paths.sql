SELECT vi.vehicle_id, vi.path
FROM {PREFIX}vehicle_images vi
JOIN {TABLE} v ON v.id = vi.vehicle_id AND v.is_published = 1
JOIN {PREFIX}pages pg ON pg.id = v.page_id AND pg.deleted_at IS NULL
ORDER BY vi.vehicle_id ASC, vi.position ASC, vi.id ASC
