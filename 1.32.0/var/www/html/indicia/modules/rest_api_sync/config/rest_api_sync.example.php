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
 * @package	Modules
 * @subpackage Cache builder
 * @author	Indicia Team
 * @license	http://www.gnu.org/licenses/gpl.html GPL
 * @link 	http://code.google.com/p/indicia/
 */

/**
 * Define the database ID used to identify this system in the network.
 */
$config['user_id'] = 'ABC';

/**
 * Master species checklist to lookup against.
 */
$config['taxon_list_id'] = 1;

/**
 * Which sample attribute will we use to store the dataset name for records which came from
 * remote systems?
 */
$config['dataset_name_attr_id'] = 99;

// The following configuration is a temporary definition of the projects available for 
// each website.
// @todo Move this configuration into a database table.
$config['servers']=array(
  // keyed by server system ID
  'XYZ' => array(
    // the local website registration used to store each project
    'website_id' => 5,
    // remote API URL
    'url' => 'http://localhost/indicia/index.php/services/rest',
    // secret shared with the remote API
    'shared_secret' => '123password',
    // Optional. Which resources will we try to retrieve from this API?
    'resources' => array('taxon-observations', 'annotations')
  )
);