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
 * @package	Scratchpad
 * @subpackage Models
 * @author	Indicia Team
 * @license	http://www.gnu.org/licenses/gpl.html GPL
 * @link 	http://code.google.com/p/indicia/
 */

/**
 * Model class for the scratchpad_list_entries table.
 *
 * @package	Core
 * @subpackage Models
 * @link	http://code.google.com/p/indicia/wiki/DataModel
 */
class Scratchpad_list_Model extends ORM {

  protected $belongs_to = array(
      'website',
      'created_by'=>'user'
  );
      
  protected $has_many = array('scratchpad_list_entries');

  public function validate(Validation $array, $save = FALSE) {
    $array->pre_filter('trim');
    $array->add_rules('title', 'required');
    $array->add_rules('entity', 'required');
    $array->add_rules('website_id', 'required');
    $array->add_rules('website_id', 'integer');
    $this->unvalidatedFields = array('description', 'expires_on');
    return parent::validate($array, $save);
  }

  /**
   * Return the submission structure, which includes defining taxon and taxon_meaning
   * as the parent (super) models, and the synonyms and commonNames as metaFields which
   * are specially handled.
   *
   * @return array Submission structure for a taxa_taxon_list entry.
   */
  public function get_submission_structure()
  {
    return array(
      'model'=>$this->object_name,
      'metaFields'=>array('entries')
    );
  }

  public function postSubmit($isInsert) {
    if (array_key_exists('metaFields', $this->submission) &&
        array_key_exists('entries', $this->submission['metaFields'])) {
      $entries = explode(';', $this->submission['metaFields']['entries']['value']);
      if (!$isInsert) {
        $this->db->query('delete from scratchpad_list_entries where scratchpad_list_id=' . $this->id .
            ' and entry_id not in (' . implode(',', $entries) . ')');
      }
      foreach ($entries as $entry_id) {
        if ($this->db->query(
            "select 1 from scratchpad_list_entries where scratchpad_list_id=$this->id and entry_id=$entry_id"
            )->count()===0) {
          $this->db->query(
            "insert into scratchpad_list_entries (scratchpad_list_id, entry_id) select $this->id, $entry_id"
          );
        }
      }
    }
    return true;
  }

}