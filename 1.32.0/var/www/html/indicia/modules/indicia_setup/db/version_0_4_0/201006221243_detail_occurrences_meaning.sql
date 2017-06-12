DROP VIEW detail_occurrences;

CREATE OR REPLACE VIEW detail_occurrences AS 
 SELECT o.id, o.confidential, o.comment, o.taxa_taxon_list_id, ttl.taxon_meaning_id, o.determiner_id, t.taxon, s.entered_sref, s.entered_sref_system, s.geom, s.location_name, s.survey_id, s.date_start, s.date_end, s.date_type, s.location_id, l.name AS location, l.code AS location_code, s.recorder_names, (d.first_name::text || ' '::text) || d.surname::text AS determiner, o.website_id, o.created_by_id, c.username AS created_by, o.created_on, o.updated_by_id, u.username AS updated_by, o.updated_on, o.record_status, o.downloaded_flag, o.sample_id, o.deleted
   FROM occurrences o
   JOIN samples s ON s.id = o.sample_id AND s.deleted = false
   LEFT JOIN people d ON d.id = o.determiner_id
   LEFT JOIN locations l ON l.id = s.location_id
   LEFT JOIN taxa_taxon_lists ttl ON ttl.id = o.taxa_taxon_list_id
   LEFT JOIN taxa t ON t.id = ttl.taxon_id
   LEFT JOIN surveys su ON s.survey_id = su.id AND su.deleted = false
   JOIN users c ON c.id = o.created_by_id
   JOIN users u ON u.id = o.updated_by_id
  WHERE o.deleted = false;
