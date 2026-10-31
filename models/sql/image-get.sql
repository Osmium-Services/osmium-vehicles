SELECT id, vehicle_id, path, position
FROM {TABLE}
WHERE id = :id
LIMIT 1
