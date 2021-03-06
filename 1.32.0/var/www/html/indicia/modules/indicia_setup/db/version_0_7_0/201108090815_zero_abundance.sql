﻿-- use a procedure to add the column so it can do if not exists

CREATE FUNCTION addcol()
RETURNS void 
AS $BODY$
BEGIN
  IF NOT EXISTS(
    SELECT * FROM information_schema.COLUMNS
    WHERE COLUMN_NAME='zero_abundance' AND TABLE_NAME='occurrences'
  )
  THEN
    ALTER TABLE occurrences
      ADD COLUMN zero_abundance boolean NOT NULL DEFAULT 'f';
  END IF;
RETURN;
END;
$BODY$ LANGUAGE plpgsql;

SELECT addcol();

DROP FUNCTION addcol();

COMMENT ON COLUMN occurrences.zero_abundance IS 'Flag that is set to true when a record indicates the absence of something rather than presence of something.';

DROP VIEW detail_occurrences;

CREATE OR REPLACE VIEW detail_occurrences AS 
 SELECT o.id, o.confidential, o.comment, o.taxa_taxon_list_id, ttl.taxon_meaning_id, o.record_status, o.determiner_id, t.taxon, 
     s.entered_sref, s.entered_sref_system, s.geom, st_astext(s.geom) AS wkt, s.location_name, s.survey_id, s.date_start, 
     s.date_end, s.date_type, s.location_id, l.name AS location, l.code AS location_code, s.recorder_names, 
     (d.first_name::text || ' '::text) || d.surname::text AS determiner, o.website_id, 
     o.created_by_id, c.username AS created_by, o.created_on, o.updated_by_id, u.username AS updated_by, o.updated_on, 
     o.downloaded_flag, o.sample_id, o.deleted, o.zero_abundance
   FROM occurrences o
   JOIN samples s ON s.id = o.sample_id AND s.deleted = false
   LEFT JOIN people d ON d.id = o.determiner_id AND d.deleted = false
   LEFT JOIN locations l ON l.id = s.location_id AND l.deleted = false
   LEFT JOIN taxa_taxon_lists ttl ON ttl.id = o.taxa_taxon_list_id AND ttl.deleted = false
   LEFT JOIN taxa t ON t.id = ttl.taxon_id AND t.deleted = false
   LEFT JOIN surveys su ON s.survey_id = su.id AND su.deleted = false
   JOIN users c ON c.id = o.created_by_id
   JOIN users u ON u.id = o.updated_by_id
  WHERE o.deleted = false;

DROP VIEW list_occurrences;

CREATE OR REPLACE VIEW list_occurrences AS 
 SELECT su.title AS survey, l.name AS location, s.date_start, s.date_end, s.date_type, s.entered_sref, s.entered_sref_system, 
     t.taxon, o.website_id, o.id, s.recorder_names, o.zero_abundance
   FROM occurrences o
   JOIN samples s ON o.sample_id = s.id AND s.deleted = false
   LEFT JOIN locations l ON s.location_id = l.id
   LEFT JOIN taxa_taxon_lists ttl ON o.taxa_taxon_list_id = ttl.id
   LEFT JOIN taxa t ON ttl.taxon_id = t.id
   LEFT JOIN surveys su ON s.survey_id = su.id AND su.deleted = false
  WHERE o.deleted = false;