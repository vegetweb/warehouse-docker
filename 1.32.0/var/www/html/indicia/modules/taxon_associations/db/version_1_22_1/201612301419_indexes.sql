CREATE INDEX fki_taxon_association_from_taxon_meaning
  ON taxon_associations(from_taxon_meaning_id);
CREATE INDEX fki_taxon_association_to_taxon_meaning
  ON taxon_associations(to_taxon_meaning_id);