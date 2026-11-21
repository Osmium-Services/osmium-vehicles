SELECT
    v.id, v.make, v.model, v.year, v.status, v.is_published,
    pg.url
FROM {TABLE} v
JOIN {PREFIX}pages pg ON pg.id = v.page_id AND pg.deleted_at IS NULL
ORDER BY v.created_at DESC, v.id DESC
LIMIT :limit
