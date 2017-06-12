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
 * @package    Core
 * @subpackage Libraries
 * @author    Indicia Team
 * @license    http://www.gnu.org/licenses/gpl.html GPL
 * @link     http://code.google.com/p/indicia/
 */
 
/**
 * This is a view which outputs a parameters entry form to capture values that will apply to every row during an import, such as the website id.
 */
 
require_once(DOCROOT.'client_helpers/import_helper.php');
$auth = import_helper::get_read_write_auth(0-$_SESSION['auth_user']->id, kohana::config('indicia.private_key'));

echo import_helper::importer(array(
  'model' => $this->controllerpath,
  'auth' => $auth,
  'switches' => array('activate_parent_sample_method_filter' => 't',
  						'activate_global_sample_method' => 't',
  						'activate_location_location_type_filter' => 't',
  						'occurrence_associations' => 't')  
));
import_helper::$dumped_resources[] = 'jquery';
import_helper::$dumped_resources[] = 'jquery-ui';
echo import_helper::dump_javascript();

?>


