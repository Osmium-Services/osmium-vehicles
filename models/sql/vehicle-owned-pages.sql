SELECT v.id, v.page_id
FROM {TABLE} v
JOIN {PREFIX}pages pg ON pg.id = v.page_id
