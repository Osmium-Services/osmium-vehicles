SELECT id
FROM {PREFIX}pages
WHERE url = :url AND deleted_at IS NOT NULL
ORDER BY deleted_at DESC
LIMIT 1
