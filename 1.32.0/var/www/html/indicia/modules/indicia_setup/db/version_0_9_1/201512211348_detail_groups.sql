CREATE OR REPLACE VIEW detail_groups AS
 SELECT g.id,
    g.title,
    g.code,
    g.group_type_id,
    g.description,
    g.from_date,
    g.to_date,
    g.private_records,
    g.website_id,
    g.joining_method,
    g.filter_id,
    f.definition AS filter_definition,
    g.created_by_id,
    c.username AS created_by,
    g.updated_by_id,
    u.username AS updated_by,
    g.logo_path,
        CASE
            WHEN g.joining_method='P' OR g.joining_method='R' THEN btrim(regexp_replace(regexp_replace(lower(g.title::text), '[ ]'::text, '-'::text, 'g'::text), '[^a-z0-9\-]'::text, ''::text, 'g'::text), '-'::text)
            ELSE NULL::text
        END AS url_safe_title,
    g.implicit_record_inclusion,
    g.licence_id,
    l.code AS licence_code
   FROM groups g
     LEFT JOIN filters f ON f.id = g.filter_id AND f.deleted = false
     LEFT JOIN licences l ON l.id = g.licence_id AND l.deleted = false
     JOIN users c ON c.id = g.created_by_id
     JOIN users u ON u.id = g.updated_by_id
  WHERE g.deleted = false;