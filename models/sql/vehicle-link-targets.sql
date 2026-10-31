SELECT pg.id, pg.url, pg.title
FROM {PREFIX}pages pg
LEFT JOIN {TABLE} v ON v.page_id = pg.id
WHERE pg.deleted_at IS NULL
  AND pg.url <> 'error'
  AND (v.id IS NULL OR v.is_published = 1)
ORDER BY pg.title, pg.url
