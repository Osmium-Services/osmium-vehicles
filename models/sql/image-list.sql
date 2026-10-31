SELECT id, vehicle_id, path, position
FROM {TABLE}
WHERE vehicle_id = :vehicle_id
ORDER BY position ASC, id ASC
