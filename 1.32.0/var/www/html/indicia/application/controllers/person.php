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
 * Controller providing CRUD access to the people data.
 *
 * @package	Core
 * @subpackage Controllers
 */
class Person_Controller extends Gridview_Base_Controller {

  public function __construct() {
    parent::__construct('person');
    $this->pagetitle = "People";
    $this->model = new Person_Model();
    $this->flag_warning = null;

    // use a report to load the users list so the parameters can be more complex
    $this->gridReport = 'library/people/people_list';
    // apply permissions for the users you can administer
    if (!$this->auth->logged_in('CoreAdmin'))
      $this->auth_filter = array('field' => 'admin_user_id', 'values' => $_SESSION['auth_user']->id);
  }
  
  protected function getModelValues() {
    $r = parent::getModelValues();
    $websiteIds = $this->getWebsiteIds($r['person:id']);
    $this->loadAttributes($r, array(
        'website_id'=>$websiteIds
    ));
    return $r;      
  }
  
  /**
   * Load default values either when creating a sample new or reloading after a validation failure.
   * This adds the custome attributes list to the data available for the view. 
   */
  protected function getDefaults() {
    $r = parent::getDefaults();
    // when creating a person, we don't know the websites for the person, so cannot 
    // filter the attribute values available. Therefore they are not displayed until 
    // after the person has been saved. Therefore no need to call loadAttributes.
    // We do when editing after a validation failure though.
    if ($this->model->id!==0) {
      $websiteIds = $this->getWebsiteIds($this->model->id);
      $this->loadAttributes($r, array(
        'website_id'=>$websiteIds
      ));
    }
    return $r;
  }
  
  protected function getWebsiteIds($personId) {
    $person = ORM::Factory('person', $personId);
    $websiteIds = array();
    $r = $this->db->select('users_websites.website_id')
        ->from('people')
        ->join('users','users.person_id','people.id')
        ->join('users_websites', 'users_websites.user_id', 'users.id')
        ->where(array('people.id'=>$personId, 'users.deleted'=>'f'))
        ->where('users_websites.site_role_id is not null')
        ->get()->result_array(false);
    foreach($r as $website) {
      $websiteIds[] = $website['website_id'];
    }
    return $websiteIds;
  }

  protected function return_url($return_url)
  {
    return '<input type="hidden" name="return_url" id="return_url" value="'.html::specialchars($return_url).'" />';
  }

  /**
   * Action for person/create page.
   * Displays a page allowing entry of a new person.
   */
  public function create_from_user() {
  	if (!$this->record_authorised(null)) {
      $this->access_denied();
      return;
    }
    $values = $this->getDefaults();
    $values['return_url']=$this->return_url('user');
    $this->showEditPage($values);
    $this->set_warning();
  }

  /**
   * Subsiduary Action for person/edit page.
   * Displays a page allowing modification of an existing person.
   * This is called from a User Record.
   * When called from User we want to return back to the User gridview on submission for that person
   */
  public function edit_from_user($id = NULL) {
    if ($id == null) {
      $this->setError('Invocation error: missing argument', 'You cannot edit a person through edit_from_user() without a Person ID');
    }
    else if (!$this->record_authorised($id)) {
      $this->access_denied('record with ID='.$id);
    } else {
      $this->model = new Person_Model($id);
      $values = $this->getModelValues();
      $this->showEditPage($values);
    }
  }

  protected function record_authorised ($id)
  {
    if (!is_null($id) AND !is_null($this->auth_filter))
    {
      return (in_array($id, $this->auth_filter['values']));
    }
    return true;
  }
  
  /**
   * You can only access the list of people if at least an editor of one website.
   */
  protected function page_authorised() {
    return $this->auth->logged_in('CoreAdmin') || $this->auth->has_any_website_access('editor');
  }

  protected function set_warning()
  {
    $this->template->content->warning_message='';
    if($this->model->loaded) {
      $user=ORM::factory('user', array('person_id' => $this->model->id));
      if(!is_null($this->gen_auth_filter)){
        // Non Core Admin user
        $my_users_websites=ORM::factory('users_website')->where('user_id', $user->id)->where('site_role_id IS NOT ', null)->in('website_id', $this->gen_auth_filter['values'])->find_all();
        $all_users_websites=ORM::factory('users_website')->where('user_id', $user->id)->where('site_role_id IS NOT ', null)->find_all();
        if($all_users_websites->count() > 0)
          $this->session->set_flash('flash_info', '<li>Warning: This person is set up as a user on '.$all_users_websites->count().' websites, of which you have the Admin role for '.$my_users_websites->count().' website(s).</li>');
      } else {
        // Core Admin user
        $users_websites=ORM::factory('users_website')->where('user_id', $user->id)->where('site_role_id IS NOT ', null)->find_all();
        $this->session->set_flash('flash_info', 'Number of websites this person is a user on: '.$users_websites->count());
      }
    }
  }
}