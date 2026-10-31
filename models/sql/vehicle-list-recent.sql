SELECT
    v.id, v.page_id, v.status, v.make, v.model, v.year, v.is_published, v.created_at, v.updated_at,
    pg.url,
    pg.title,
    pg.deleted_at,
    CL.user_name AS changed_by,
    CU.avatar AS changed_by_avatar
FROM {TABLE} v
JOIN {PREFIX}pages pg ON pg.id = v.page_id
LEFT JOIN {PREFIX}changelog AS CL ON CL.id = (SELECT MAX(id) FROM {PREFIX}changelog WHERE record_type = 'vehicle' AND record_id = v.id)
LEFT JOIN {PREFIX}users AS CU ON CU.id = CL.user_id
WHERE v.created_at >= NOW() - INTERVAL 24 HOUR OR v.updated_at >= NOW() - INTERVAL 24 HOUR OR pg.deleted_at >= NOW() - INTERVAL 24 HOUR
ORDER BY GREATEST(COALESCE(v.updated_at, v.created_at), v.created_at, COALESCE(pg.deleted_at, v.created_at)) DESC, v.id DESC
LIMIT 10
