CREATE INDEX ix_occurrence_associations_from_occurrence_id ON occurrence_associations(from_occurrence_id);
CREATE INDEX ix_occurrence_associations_to_occurrence_id ON occurrence_associations(to_occurrence_id);