<?php
/**
 * Indicia, the OPAL Online Recording Toolkit.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/gpl.html.
 *
 * @package	Data Cleaner
 * @subpackage Plugins
 * @author	Indicia Team
 * @license	http://www.gnu.org/licenses/gpl.html GPL
 * @link 	http://code.google.com/p/indicia/
 */

/**
 * Hook into the data cleaner to declare checks for a species which has not previously been recorded and verified at a site. Only 
 * works for surveys which record against a defined list of locations.
 * @return type array of rules.
 */
function data_cleaner_new_species_for_site_data_cleaner_rules() {
  return array(
    'testType' => 'NewSpeciesForSite',
    'required' => array('Metadata'=>array('SurveyId')),
    'optional' => array('Taxa'=>array('*')),
    'queries' => array(
      array(
        'joins' => 
            "join cache_taxa_taxon_lists cttl on cttl.id=co.taxa_taxon_list_id ".
            "join verification_rule_metadata vrsurvey on vrsurvey.key='SurveyId' 
                and vrsurvey.value=cast(co.survey_id as character varying) and vrsurvey.deleted=false
            join verification_rules vr on vr.id=vrsurvey.verification_rule_id and vr.test_type='NewSpeciesForSite' and vr.deleted=false
            join samples s on s.id=co.sample_id and s.deleted=false
            left join samples sp on sp.id=s.parent_id and sp.deleted=false
            left join (cache_occurrences coprev 
              join samples sprev on sprev.deleted=false and sprev.id=coprev.sample_id 
              left join samples sprevp on sprevp.id=sprev.parent_id and sprevp.deleted=false
            ) on coprev.taxon_meaning_id=co.taxon_meaning_id 
                and coprev.id<>co.id 
                and coalesce(sprev.location_id, sprevp.location_id)=coalesce(s.location_id, sp.location_id)
                and coalesce(sprev.location_id, sprevp.location_id) is not null
                and coprev.record_status='V'
            -- Is there any taxon filter in place?
            left join verification_rule_data filteringtaxa on filteringtaxa.verification_rule_id=vr.id and filteringtaxa.header_name='Taxa' and filteringtaxa.deleted=false
            -- if so, does this record match the filter?
            left join verification_rule_data taxa on taxa.verification_rule_id=vr.id and taxa.header_name='Taxa' and upper(taxa.key)=upper(cttl.preferred_taxon) and taxa.deleted=false",
        'where' =>
            "sprev.id is null
            and (filteringtaxa.id is null or taxa.id is not null)"
      )
    )
  );
}