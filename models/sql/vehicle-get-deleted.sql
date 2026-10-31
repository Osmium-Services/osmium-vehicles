SELECT v.id, v.page_id, v.make, v.model, pg.title
FROM {TABLE} v
JOIN {PREFIX}pages pg ON pg.id = v.page_id AND pg.deleted_at IS NOT NULL
WHERE v.id = :id
LIMIT 1
