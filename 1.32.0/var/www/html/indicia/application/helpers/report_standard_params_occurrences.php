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
 * @package	Core
 * @subpackage Helpers
 * @author	Indicia Team
 * @license	http://www.gnu.org/licenses/gpl.html GPL
 * @link 	http://code.google.com/p/indicia/
 */

defined('SYSPATH') or die('No direct script access.');

/**
 * Helper class to provide standardised reporting parameters for occurrences data
 * reports.
 */
class report_standard_params_occurrences {

  /**
   * Returns a list of the parameter names which have been deprecated and should be converted
   * to newer parameter names. Maintains backwards compatibility with clients that are not
   * running the latest code. Returns an array, with each element being a sub array containing
   * the old and new parameter names. A third optional element in the sub-array can be set to
   * TRUE to enable string quoting in the output.
   * @return array
   */
  public static function getDeprecatedParameters() {
    return array(
      array('location_id', 'location_list'),
      array('survey_id', 'survey_list'),
      array('indexed_location_id', 'indexed_location_list'),
      array('input_form', 'input_form_list', TRUE)
    );
  }
  
  /**
   * @return array List of parameters that have an associated operation parameter. E.g. along
   * with the occurrence_id parameter you can supply occurrence_id_op='>=' to define the operation
   * to be applied in the filter.
   * @return array
   */
  public static function getOperationParameters() {
    return array(
      'occurrence_id' => array('datatype'=>'lookup', 'display'=>'ID operation',
        'description'=>'Operator to use in conjunction with a value provided in the occurrence_id parameter.',
        'lookup_values'=>'=:is,>=:is at least,<=:is at most'
      ),
      'website_list' => array('datatype'=>'lookup', 'default'=>'in', 'display'=>'Website IDs mode',
        'description'=>'Include or exclude the list of websites provided in the website_list parameter',
        'lookup_values'=>'in:Include,not in:Exclude'
      ),
      'survey_list' => array('datatype'=>'lookup', 'default'=>'in', 'display'=>'Survey IDs mode',
        'description'=>'Include or exclude the list of surveys provided in the survey_list parameter', 'lookup_values'=>'in:Include,not in:Exclude'
      ),
      'input_form_list' => array('datatype'=>'lookup', 'default'=>'in', 'display'=>'Input forms mode',
        'description'=>'Include or exclude the list of input forms', 'lookup_values'=>'in:Include,not in:Exclude'
      ),
      'location_list' => array('datatype'=>'lookup', 'default'=>'in', 'display'=>'Location IDs mode',
        'description'=>'Include or exclude the list of locations', 'lookup_values'=>'in:Include,not in:Exclude'
      ),
      'indexed_location_list' => array('datatype'=>'lookup', 'default'=>'in', 'display'=>'Indexed location IDs mode',
        'description'=>'Include or exclude the list of indexed locations', 'lookup_values'=>'in:Include,not in:Exclude'
      ),
      'taxon_rank_sort_order' => array('datatype'=>'lookup', 'default'=>'', 'display'=>'Taxon rank mode',
        'description'=>'Mode for filtering by taxon rank in the hierarchy',
        'lookup_values'=>'=:include only this level in the hierarchy,>=:include this level and lower,<=:include this level and higher'
      ),
      'identification_difficulty' => array('datatype'=>'lookup', 'default'=>'', 'display'=>'Identification difficulty operation',
        'description'=>'Identification difficulty lookup operation', 'lookup_values'=>'=:is,>=:is at least,<=:is at most'
      ),
    );
  }

  /**
   * Retrieves the list of standard reporting parameters available for this report type.
   * @return array
   */
  public static function getParameters() {
    return array(
      'idlist' => array('datatype'=>'idlist', 'display'=>'List of IDs', 'emptyvalue'=>'', 'fieldname'=>'o.id', 'alias'=>'occurrence_id',
        'description'=>'Comma separated list of occurrence IDs to filter to.'
      ),
      'searchArea' => array('datatype'=>'geometry', 'display'=>'Boundary',
        'description'=>'Boundary to search within, in Well Known Text format using Web Mercator projection.',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"st_intersects(o.public_geom, st_makevalid(st_geomfromtext('#searchArea#',900913)))")
        )
      ),
      'occurrence_id' => array('datatype'=>'integer', 'display'=>'ID',
        'description'=>'Limit to a single record matching this occurrence ID.',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.id #occurrence_id_op# #occurrence_id#")
        )
      ),
      'taxon_rank_sort_order' => array('datatype'=>'integer', 'display'=>'Taxon rank',
        'description'=>'Rank of the identified taxon in the taxonomic hierarchy',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.taxon_rank_sort_order #taxon_rank_sort_order_op# #taxon_rank_sort_order#")
        )
      ),
      'location_name' => array('datatype'=>'text', 'display'=>'Location name',
        'description'=>'Name of location to filter to (contains search)',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.location_name ilike '%#location_name#%'")
        )
      ),
      'location_list' => array('datatype'=>'integer[]', 'display'=>'Location IDs',
        'description'=>'Comma separated list of location IDs',
        'joins' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"JOIN locations #alias:lfilt# on #alias:lfilt#.id #location_list_op# (#location_list#) and #alias:lfilt#.deleted=false " .
            "and st_intersects(coalesce(#alias:lfilt#.boundary_geom, #alias:lfilt#.centroid_geom), #sample_geom_field#) " .
            "and not st_touches(coalesce(#alias:lfilt#.boundary_geom, #alias:lfilt#.centroid_geom), #sample_geom_field#)")
        )
      ),
      'indexed_location_list' => array('datatype'=>'integer[]', 'display'=>'Location IDs (indexed)', 'custom'=>'unique_location_index',
        'description'=>'Comma separated list of location IDs, for locations that are indexed using the spatial index builder',
        'joins' => array(
          // join will be skipped if using a uniquely indexed location type
          array('value'=>'', 'operator'=>'', 'sql'=>"JOIN index_locations_samples #alias:ilsfilt# on #alias:ilsfilt#.sample_id=o.sample_id and #alias:ilsfilt#.location_id #indexed_location_list_op# (#indexed_location_list#)")
        ),
        'wheres' => array(
          // where will be used only if using a uniquely indexed location type
          array('value'=>'', 'operator'=>'', 'sql'=>"o.location_id_#typealias# #indexed_location_list_op# (#indexed_location_list#)")
        )
      ),
      'date_from' => array('datatype'=>'date', 'display'=>'Date from',
        'description'=>'Date of first record to include in the output',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"('#date_from#'='Click here' OR o.date_end >= CAST(COALESCE('#date_from#','1500-01-01') as date))")
        )
      ),
      'date_to' => array('datatype'=>'date', 'display'=>'Date to',
        'description'=>'Date of last record to include in the output',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"('#date_to#'='Click here' OR o.date_start <= CAST(COALESCE('#date_to#','1500-01-01') as date))")
        )
      ),
      'date_age' => array('datatype'=>'text', 'display'=>'Date from time ago',
        'description'=>'E.g. enter "1 week" or "3 days" to define the how old records can be before they are dropped from the report.',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.date_start>now()-'#date_age#'::interval")
        )
      ),
      'input_date_from' => array('datatype'=>'date', 'display'=>'Input date from',
        'description'=>'Input date of first record to include in the output',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 
            'sql'=>"('#input_date_from#'='Click here' OR o.created_on >= '#input_date_from#'::timestamp)")
        )
      ),
      'input_date_to' => array('datatype'=>'date', 'display'=>'Input date to',
        'description'=>'Input date of last record to include in the output',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 
            'sql'=>"('#input_date_to#'='Click here' OR (o.created_on <= '#input_date_to#'::timestamp OR (length('#input_date_to#')<=10 AND o.created_on < cast('#input_date_to#' as date) + '1 day'::interval)))")
        )
      ),
      'input_date_age' => array('datatype'=>'text', 'display'=>'Input date from time ago',
        'description'=>'E.g. enter "1 week" or "3 days" to define the how long ago records can be input before they are dropped from the report.',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.created_on>now()-'#input_date_age#'::interval")
        )
      ),
      'edited_date_from' => array('datatype'=>'date', 'display'=>'Last update date from',
        'description'=>'Last update date of first record to include in the output',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 
            'sql'=>"('#edited_date_from#'='Click here' OR o.updated_on >= '#edited_date_from#'::timestamp)")
        )
      ),
      'edited_date_to' => array('datatype'=>'date', 'display'=>'Last update date to',
        'description'=>'Last update date of last record to include in the output',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 
            'sql'=>"('#edited_date_to#'='Click here' OR (o.updated_on <= '#edited_date_to#'::timestamp OR (length('#edited_date_to#')<=10 AND o.updated_on < cast('#edited_date_to#' as date) + '1 day'::interval)))")
        )
      ),
      'edited_date_age' => array('datatype'=>'text', 'display'=>'Last update date from time ago',
        'description'=>'E.g. enter "1 week" or "3 days" to define the how long ago records can be last updated before they are dropped from the report.',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.updated_on>now()-'#edited_date_age#'::interval")
        )
      ),
      'verified_date_from' => array('datatype'=>'date', 'display'=>'Verification status change date from',
        'description'=>'Verification status change date of first record to include in the output',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"('#verified_date_from#'='Click here' OR o.verified_on >= CAST('#verified_date_from#' as date))")
        )
      ),
      'verified_date_to' => array('datatype'=>'date', 'display'=>'Verification status change date to',
        'description'=>'Verification status change date of last record to include in the output',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"('#verified_date_to#'='Click here' OR o.verified_on < CAST('#verified_date_to#' as date)+'1 day'::interval)")
        )
      ),
      'verified_date_age' => array('datatype'=>'text', 'display'=>'Verification status change date from time ago',
        'description'=>'E.g. enter "1 week" or "3 days" to define the how long ago records can have last had their status changed before they are dropped from the report.',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.verified_on>now()-'#verified_date_age#'::interval")
        )
      ),
      'quality' => array('datatype'=>'lookup', 'display'=>'Quality',
        'description'=>'Minimum quality of records to include',
        'lookup_values'=>'V1:Accepted as correct records only,V:Accepted records only,-3:Reviewer agreed at least plausible,' .
          'C:Recorder was certain,L:Recorder thought the record was at least likely,' .
          'P:Not reviewed,T:Not reviewed but trusted recorder,!D:Exclude queried or not accepted records,!R:Exclude not accepted records,D:Queried records only,'.
          'A:Answered records,R:Not accepted records only,R4:Not accepted because unable to verify records only,DR:Queried or not accepted records,all:All records',
        'wheres' => array(
          array('value'=>'V1', 'operator'=>'equal', 'sql'=>"o.record_status='V' and o.record_substatus=1"),
          array('value'=>'V', 'operator'=>'equal', 'sql'=>"o.record_status='V'"),
          array('value'=>'-3', 'operator'=>'equal', 'sql'=>"(o.record_status='V' or o.record_substatus<=3)"),
          array('value'=>'C', 'operator'=>'equal', 'sql'=>"o.record_status<>'R' and o.certainty='C'"),
          array('value'=>'L', 'operator'=>'equal', 'sql'=>"o.record_status<>'R' and o.certainty in ('C','L')"),
          array('value'=>'P', 'operator'=>'equal', 'sql'=>"o.record_status='C' and o.record_substatus is null and (o.query<>'Q' or o.query is null)"),
          array('value'=>'T', 'operator'=>'equal', 'sql'=>"o.record_status='C' and o.record_substatus is null"),
          array('value'=>'!D', 'operator'=>'equal', 'sql'=>"(o.record_status not in ('R','D') and (o.query<>'Q' or o.query is null))"),
          array('value'=>'!R', 'operator'=>'equal', 'sql'=>"o.record_status<>'R'"),
          array('value'=>'D', 'operator'=>'equal', 'sql'=>"(o.record_status='D' or o.query='Q')"),
          array('value'=>'A', 'operator'=>'equal', 'sql'=>"o.query='A'"),
          array('value'=>'R', 'operator'=>'equal', 'sql'=>"o.record_status='R'"),
          array('value'=>'R4', 'operator'=>'equal', 'sql'=>"o.record_status='R' and o.record_substatus=4"),
          array('value'=>'DR', 'operator'=>'equal', 'sql'=>"(o.record_status in ('R','D') or o.query='Q')"),
          // The all filter does not need any SQL
        ),
        'joins' => array(
          array('value'=>'T', 'operator'=>'equal', 'sql'=>
            "LEFT JOIN index_locations_samples #alias:ilstrust# on #alias:ilstrust#.sample_id=o.sample_id
  JOIN user_trusts #alias:ut# on (#alias:ut#.survey_id=o.survey_id
      OR #alias:ut#.taxon_group_id=o.taxon_group_id
      OR (#alias:ut#.location_id=#alias:ilstrust#.location_id or #alias:ut#.location_id is null)
    )
    AND #alias:ut#.deleted=false
    AND ((o.survey_id = #alias:ut#.survey_id) or (#alias:ut#.survey_id is null and (#alias:ut#.taxon_group_id is not null or #alias:ut#.location_id is not null)))
    AND ((o.taxon_group_id = #alias:ut#.taxon_group_id) or (#alias:ut#.taxon_group_id is null and (#alias:ut#.survey_id is not null or #alias:ut#.location_id is not null)))
    AND ((#alias:ilstrust#.location_id = #alias:ut#.location_id) OR (#alias:ut#.location_id IS NULL and (#alias:ut#.survey_id is not null or #alias:ut#.taxon_group_id is not null)))
    AND o.created_by_id = #alias:ut#.user_id")
        )
      ),
      'exclude_sensitive'=>array('datatype'=>'boolean', 'display'=>'Exclude sensitive records',
        'description'=>'Exclude sensitive records?',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.sensitive=false")
        )
      ),
      'confidential'=>array('datatype'=>'boolean', 'display'=>'Confidential records',
        'description'=>'Filtering based on confidential status of the record',
        'lookup_values'=>'t:Confidential records only,f:Exclude confidential records,all:All records',
        'wheres' => array(
          array('value'=>'t', 'operator'=>'equal', 'sql'=>"o.confidential='t'"),
          array('value'=>'f', 'operator'=>'equal', 'sql'=>"o.confidential='f'"),
          // nothing to do for all case
        )
      ),
      'release_status' => array('datatype'=>'lookup', 'display'=>'Release status',
        'description'=>'Release status of the record',
        'lookup_values'=>'R:Released,RM:Released by other recorders plus my own unreleased records;U:Unreleased because part of a project that has not yet released the records,' .
          'P:Recorder has requested a precheck before release,A:All',
        'wheres' => array(
          array('value'=>'R', 'operator'=>'equal', 'sql'=>"o.release_status='R'"),
          array('value'=>'U', 'operator'=>'equal', 'sql'=>"o.release_status='U'"),
          array('value'=>'P', 'operator'=>'equal', 'sql'=>"o.release_status='P'"),
          array('value'=>'RM', 'operator'=>'equal', 'sql'=>"o.release_status='R' or o.created_by_id=#user_id#"),
          // The all filter does not need any SQL
        ),
      ),
      'marine_flag' => array('datatype'=>'lookup', 'display'=>'Marine flag',
        'description'=>'Marine species filtering?',
        'lookup_values'=>'A:Include marine and non-marine species,Y:Only marine species,N:Exclude marine species',
        'wheres' => array(
          array('value'=>'Y', 'operator'=>'equal', 'sql'=>"o.marine_flag=true"),
          array('value'=>'N', 'operator'=>'equal', 'sql'=>"(o.marine_flag is null or o.marine_flag=false)"),
          // The all filter does not need any SQL
        ),
      ),
      'autochecks' => array('datatype'=>'lookup', 'display'=>'Automated checks',
        'description'=>'Filter to only include records that have passed or failed automated checks',
        'lookup_values'=>'N:Not filtered,F:Include only records that fail checks,P:Include only records which pass checks',
        'wheres' => array(
          array('value'=>'F', 'operator'=>'equal', 'sql'=>"o.data_cleaner_result = 'f'"),
          array('value'=>'P', 'operator'=>'equal', 'sql'=>"o.data_cleaner_result = 't'")
        )
      ),
      'has_photos' => array('datatype'=>'boolean', 'display'=>'Photo records only',
        'description'=>'Only include records which have photos?',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.media_count>0")
        )
      ),
      'user_id' => array('datatype'=>'integer', 'display'=>"Current user's warehouse ID"),
      'my_records' => array('datatype'=>'boolean', 'display'=>"Only include my records",
        'wheres' => array(
          array('value'=>'1', 'operator'=>'equal', 'sql'=>"o.created_by_id=#user_id#")
        )
      ),
      'created_by_id' => array('datatype' => 'integer', 'display'=>'Limit to records created by this user ID',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.created_by_id=#created_by_id#")
        )
      ),
      'group_id' => array('datatype'=>'integer', 'display'=>"ID of a group to filter to the members of",
        'description'=>'Specify the ID of a recording group. This filters the report to the members of the group.',
        'joins' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"join groups_users #alias:gu# on #alias:gu#.user_id=o.created_by_id and #alias:gu#.group_id=#group_id# and #alias:gu#.deleted=false")
        ),
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.group_id=#group_id#")
        )
      ),
      'implicit_group_id' => array('datatype'=>'integer', 'display'=>"ID of a group to filter to the members of",
        'description'=>'Specify the ID of a recording group. This filters the report to the members of the group.',
        'joins' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"join groups_users #alias:gu# on #alias:gu#.user_id=o.created_by_id and #alias:gu#.group_id=#implicit_group_id# and #alias:gu#.deleted=false")
        )
      ),
      'website_list' => array('datatype'=>'integer[]', 'display'=>"Website IDs",
        'description'=>'Comma separated list of IDs of websites to limit to within the set of ' .
          'websites you have permission to access records for.',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.website_id #website_list_op# (#website_list#)")
        )
      ),
      'survey_list' => array('datatype'=>'integer[]', 'display'=>"Survey IDs",
        'description'=>'Comma separated list of IDs of survey datasets to limit to.',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.survey_id #survey_list_op# (#survey_list#)")
        )
      ),
      'input_form_list' => array('datatype'=>'text[]', 'display'=>"Input forms",
        'description'=>'Comma separated list of input form paths',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.input_form #input_form_list_op# (#input_form_list#)")
        )
      ),
      'taxon_group_list' => array('datatype'=>'integer[]', 'display'=>"Taxon Group IDs",
        'description'=>'Comma separated list of IDs of taxon groups to limit to.',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.taxon_group_id in (#taxon_group_list#)")
        )
      ),
      'taxa_taxon_list_list' => array('datatype'=>'integer[]', 'display'=>"Taxa taxon list IDs",
        'description'=>'Comma separated list of preferred IDs',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.taxa_taxon_list_external_key in (#taxa_taxon_list_list#)")
        ),
        'preprocess' => // faster than embedding this query in the report
          "with recursive q as (
    select id, external_key
    from cache_taxa_taxon_lists t
    where id in (#taxa_taxon_list_list#)
    union all
    select tc.id, tc.external_key
    from q
    join cache_taxa_taxon_lists tc on tc.parent_id = q.id
  ) select '''' || array_to_string(array_agg(distinct external_key::varchar), ''',''') || '''' from q"
      ),
      // version of the above optimised for searching for higher taxa
      'higher_taxa_taxon_list_list' => array('datatype'=>'integer[]', 'display'=>"Higher taxa taxon list IDs",
        'description'=>'Comma separated list of preferred IDs. Optimised for searches at family level or higher',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.family_taxa_taxon_list_id in (#higher_taxa_taxon_list_list#)")
        ),
        'preprocess' => // faster than embedding this query in the report
          "with recursive q as (
    select id, family_taxa_taxon_list_id
    from cache_taxa_taxon_lists t
    where id in (#higher_taxa_taxon_list_list#)
    union all
    select tc.id, tc.family_taxa_taxon_list_id
    from q
    join cache_taxa_taxon_lists tc on tc.parent_id = q.id and tc.taxon_rank_sort_order<=180
  ) select array_to_string(array_agg(distinct family_taxa_taxon_list_id::varchar), ',') from q"
      ),
      'taxon_meaning_list' => array('datatype'=>'integer[]', 'display'=>"Taxon meaning IDs",
        'description'=>'Comma separated list of taxon meaning IDs',
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.taxon_meaning_id in (#taxon_meaning_list#)")
        ),
        'preprocess' => // faster than embedding this query in the report
          "with recursive q as (
    select id, taxon_meaning_id
    from cache_taxa_taxon_lists t
    where taxon_meaning_id in (#taxon_meaning_list#)
    union all
    select tc.id, tc.taxon_meaning_id
    from q
    join cache_taxa_taxon_lists tc on tc.parent_id = q.id
  ) select array_to_string(array_agg(distinct taxon_meaning_id::varchar), ',') from q"
      ),
      'taxon_designation_list' => array('datatype'=>'integer[]', 'display'=>'Taxon designations',
        'description'=>'Comma separated list of taxon designation IDs',
        'joins' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>
            "join taxa_taxon_lists ttlpref on ttlpref.id=o.preferred_taxa_taxon_list_id and ttlpref.deleted=false\n" .
            "join taxa_taxon_designations ttd on ttd.taxon_id=ttlpref.taxon_id and ttd.deleted=false " .
            "and ttd.taxon_designation_id in (#taxon_designation_list#)")
        ),
      ),
      'identification_difficulty' => array('datatype'=>'integer', 'display'=>'Identification difficulty',
        'description'=>'Identification difficulty on a scale of 1 to 5',
        'wheres' => array(
          array('value'=>'', 'operator'=>'',
            'sql'=>"coalesce(o.identification_difficulty, 0) #identification_difficulty_op# #identification_difficulty#")
        )
      ),
    );
  }
  
  /**
   * When the cache tables were restructured some of the fields and logic in the SQL for parameters changed. This 
   * function allows filter SQL to be mapped back to SQL compatible with the old structure and is used by reports
   * that have not been migrated to benefit from the new structure (e.g. if they use the cache_occurrences view). 
   * Not implemented for samples.
   */
  public static function getLegacyStructureParameters() {
    return array(
       'input_date_from' => array(
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 
            'sql'=>"('#input_date_from#'='Click here' OR o.cache_created_on >= '#input_date_from#'::timestamp)")
        )
      ),
      'input_date_to' => array(
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 
            'sql'=>"('#input_date_to#'='Click here' OR (o.cache_created_on <= '#input_date_to#'::timestamp " .
                "OR (length('#input_date_to#')<=10 AND o.cache_created_on < cast('#input_date_to#' as date) + '1 day'::interval)))")
        )
      ),
      'input_date_age' => array(
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.cache_created_on>now()-'#input_date_age#'::interval")
        )
      ),
      'edited_date_from' => array(
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 
            'sql'=>"('#edited_date_from#'='Click here' OR o.cache_updated_on >= '#edited_date_from#'::timestamp)")
        )
      ),
      'edited_date_to' => array(
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 
            'sql'=>"('#edited_date_to#'='Click here' OR (o.cache_updated_on <= '#edited_date_to#'::timestamp " .
                "OR (length('#edited_date_to#')<=10 AND o.cache_updated_on < cast('#edited_date_to#' as date) + '1 day'::interval)))")
        )
      ),
      'edited_date_age' => array(
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.cache_updated_on>now()-'#edited_date_age#'::interval")
        )
      ),
      'exclude_sensitive'=>array('wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.sensitivity_precision is null")
        )
      ),
      'marine_flag' => array(
        'joins' => array(
          array('value'=>'', 'operator'=>'', 'standard_join'=>'prefcttl')
        ),
        'wheres' => array(
          array('value'=>'Y', 'operator'=>'equal', 'sql'=>"prefcttl.marine_flag=true"),
          array('value'=>'N', 'operator'=>'equal', 'sql'=>"(prefcttl.marine_flag is null or prefcttl.marine_flag=false)"),
          // The all filter does not need any SQL
        ),
      ),
      'autochecks' => array(
        'wheres' => array(
          array('value'=>'F', 'operator'=>'equal', 'sql'=>"o.data_cleaner_info is not null and o.data_cleaner_info<>'pass'"),
          array('value'=>'P', 'operator'=>'equal', 'sql'=>"o.data_cleaner_info = 'pass'")
        )
      ),
      'has_photos' => array(
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"o.images is not null")
        )
      ),
      'taxon_rank_sort_order' => array(
        'joins' => array(
          array('value'=>'', 'operator'=>'', 'standard_join'=>'prefcttl')
        ),
        'wheres' => array(
          array('value'=>'', 'operator'=>'', 'sql'=>"prefcttl.taxon_rank_sort_order #taxon_rank_sort_order_op# #taxon_rank_sort_order#")
        )
      ),
      'confidential' => array(
        'wheres' => array() // disables the confidential filter on legacy reports
      )
    );
  }

  /**
   * Returns an array of the parameters which have defaults and their associated default values.
   * @return array
   */
  public static function getDefaultParameterValues() {
    return array(
      'occurrence_id_op'=>'=',
      'taxon_rank_sort_order_op'=>'=',
      'website_list_op'=>'in',
      'survey_list_op'=>'in',
      'input_form_list_op'=>'in',
      'location_list_op'=>'in',
      'indexed_location_list_op'=>'in',
      'identification_difficulty_op'=>'=',
      'occurrence_id_op_context'=>'=',
      'website_list_op_context'=>'in',
      'survey_list_op_context'=>'in',
      'input_form_list_op_context'=>'in',
      'location_list_op_context'=>'in',
      'indexed_location_list_op_context'=>'in',
      'identification_difficulty_op_context'=>'=',
      'release_status'=>'R',
      'confidential'=>'f'
    );
  }
}