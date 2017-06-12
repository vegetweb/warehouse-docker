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
 * @subpackage Controllers
 * @author	Indicia Team
 * @license	http://www.gnu.org/licenses/gpl.html GPL
 * @link 	http://code.google.com/p/indicia/
 */

/**
 * Controller providing CRUD access to the list of titles for people.
 *
 * @package	Core
 * @subpackage Controllers
 */
class Title_Controller extends Gridview_Base_Controller {

  /**
     * Constructor
     */
  public function __construct()
  {
    parent::__construct('title');
    $this->columns = array(
      'title'=>''
      );
    $this->pagetitle = "Titles";
  }  

  /** 
   * No access to the titles list unless core admin.
   */
  protected function page_authorised()
  {
    return $this->auth->logged_in('CoreAdmin');
  }
}