<?php defined('SYSPATH') or die('No direct script access.');

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
 * @subpackage Models
 * @author	Indicia Team
 * @license	http://www.gnu.org/licenses/gpl.html GPL
 * @link 	http://code.google.com/p/indicia/
 */

/**
 * Model class for the Users table.
 *
 * @package	Core
 * @subpackage Models
 * @link	http://code.google.com/p/indicia/wiki/DataModel
 */
class Summariser_definition_Model extends ORM {
  public $search_field='id';

  protected $belongs_to = array('survey', 'created_by'=>'user', 'updated_by'=>'user');
  protected $has_and_belongs_to_many = array();

  public function validate(Validation $array, $save = FALSE) {
    // uses PHP trim() to remove whitespace from beginning and end of all fields before validation
    $array->pre_filter('trim'); 
    $array->add_rules('survey_id', 'required');
    $array->add_rules('period_type', 'required', 'chars[W,M]');
    $array->add_rules('period_start', 'required');
    $array->add_rules('period_one_contains', 'required');
    $array->add_rules('data_combination_method', 'required', 'chars[A,L,M,S]');
    $array->add_rules('data_rounding_method', 'required', 'chars[D,N,U,X]');
    $array->add_rules('interpolation', 'required', 'chars[L]');
    $array->add_rules('first_value', 'required', 'chars[X,H]');
    $array->add_rules('last_value', 'required', 'chars[X,H]');
    $array->add_rules('max_records_per_cycle', 'required', 'integer', 'minimum[1]');
    
    // Explicitly add those fields for which we don't do validation
    $this->unvalidatedFields = array(
    		'occurrence_attribute_id',
    		'calculate_estimates',
    		'check_for_missing',
    		'season_limits',
    		'deleted'
    );
    
    return parent::validate($array, $save);
  }

}
