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
 * @subpackage Plugins
 * @author	Indicia Team
 * @license	http://www.gnu.org/licenses/gpl.html GPL
 * @link 	http://code.google.com/p/indicia/
 */

/**
 * Declare a handler for Channel Island grid references.
 * @return array Spatial system metadata
 */
function sref_channel_islands_sref_systems() {
  return array(
    'guernsey' => array(
      'title' => 'Guernsey Grid',
      'srid' => 3108,
      'treat_srid_as_x_y_metres' => true
    ), 'jersey' => array(
      'title' => 'Jersey Grid',
      'srid' => 3109,
      'treat_srid_as_x_y_metres' => true
    )
  );
}