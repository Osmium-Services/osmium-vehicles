ALTER TABLE {PREFIX}vehicles ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER page_id;

UPDATE {PREFIX}vehicles v
JOIN (
    SELECT a.id, (SELECT COUNT(*) FROM {PREFIX}vehicles b WHERE b.created_at > a.created_at OR (b.created_at = a.created_at AND b.id > a.id)) AS place
    FROM {PREFIX}vehicles a
) ranked ON ranked.id = v.id
SET v.sort_order = ranked.place;
