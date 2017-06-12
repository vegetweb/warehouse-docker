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
 * Model class for the Surveys table.
 *
 * @package	Core
 * @subpackage Models
 * @link	http://code.google.com/p/indicia/wiki/DataModel
 */
class Survey_Model extends ORM_Tree {

  protected $ORM_Tree_children = "surveys";
  
  protected $has_many = array(
    'sample_media'
  );
  
  protected $belongs_to = array(
      'owner'=>'person',
      'website',
      'created_by'=>'user',
      'updated_by'=>'user');
  
  // Declare that this model has child attributes, and the name of the node in the submission which contains them
  protected $has_attributes=true;
  protected $attrs_submission_name='srvAttributes';
  public $attrs_field_prefix='srvAttr';

  public function validate(Validation $array, $save = FALSE) {
    $array->pre_filter('trim');
    $array->add_rules('title', 'required');
    $array->add_rules('website_id', 'required');
    $this->unvalidatedFields = array(
      'description',
      'deleted',
      'parent_id',
      'owner_id',
      'auto_accept',
      'auto_accept_max_difficulty'
    );
    return parent::validate($array, $save);
  }

}