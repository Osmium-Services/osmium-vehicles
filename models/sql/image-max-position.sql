SELECT COALESCE(MAX(position), -1) AS max_position FROM {TABLE} WHERE vehicle_id = :vehicle_id
