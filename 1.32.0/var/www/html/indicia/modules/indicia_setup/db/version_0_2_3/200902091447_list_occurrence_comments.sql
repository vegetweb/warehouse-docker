CREATE OR REPLACE VIEW list_occurrence_comments AS
SELECT oc.id, oc."comment", oc.occurrence_id, oc.email_address, u.username
FROM occurrence_comments oc
LEFT JOIN users u ON oc.created_by_id = u.id;