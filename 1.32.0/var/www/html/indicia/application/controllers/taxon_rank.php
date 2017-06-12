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
 * @package Core
 * @subpackage Controllers
 * @author	Indicia Team
 * @link http://code.google.com/p/indicia/
 * @license http://www.gnu.org/licenses/gpl.html GPL
 */

/**
 * Controller for the taxon_rank page.
 *
 * @package Core
 * @subpackage Controllers
 */
class Taxon_rank_Controller extends Gridview_Base_Controller {

  /**
   * Constructor
   */
  public function __construct()
  {
    parent::__construct('taxon_rank');
    $this->columns = array(
      'id'=>'',
      'rank'=>'',
      'sort_order'=>'');
    $this->pagetitle = "Taxon ranks";
  }

  public function page_authorised()
  {
    return $this->auth->logged_in('CoreAdmin');
  }
}