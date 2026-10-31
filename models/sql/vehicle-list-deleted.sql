SELECT
    v.id, v.page_id, v.status, v.make, v.model, v.year, v.is_published, v.updated_at,
    pg.url,
    pg.title,
    pg.deleted_at,
    CL.user_name AS changed_by,
    CU.avatar AS changed_by_avatar
FROM {TABLE} v
JOIN {PREFIX}pages pg ON pg.id = v.page_id AND pg.deleted_at IS NOT NULL
LEFT JOIN {PREFIX}changelog AS CL ON CL.id = (SELECT MAX(id) FROM {PREFIX}changelog WHERE record_type = 'vehicle' AND record_id = v.id)
LEFT JOIN {PREFIX}users AS CU ON CU.id = CL.user_id
ORDER BY pg.deleted_at DESC, v.id DESC
