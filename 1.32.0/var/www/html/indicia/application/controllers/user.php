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
 * Controller providing CRUD access to the list of Warehouse users.
 *
 * @package	Core
 * @subpackage Controllers
 */
class User_Controller extends Gridview_Base_Controller {

  private $allowedPersonIds;

  public function __construct() {
    $this->auth_filter = array('admin_user_id' => 4);
  	parent::__construct('user');
    $this->pagetitle = "Users";
    $this->model = new User_Model();
    // use a report to load the users list so the parameters can be more complex
    $this->gridReport = 'library/users/users_list';
    // apply permissions for the users you can administer
    if (!$this->auth->logged_in('CoreAdmin'))
      $this->auth_filter = array('field' => 'admin_user_id', 'values' => $_SESSION['auth_user']->id);
  }

  /**
   * Override the default page action (which loads the main grid of users) to add notes to the page as
   * a flash.
   */
  public function page($page_no, $filter=null) {
    parent::page($page_no, $filter);
  }

  /**
   * Override the default action columns so we get Edit User, Edit Person and Send Forgotten Pwd Email links.
   */
  protected function get_action_columns() {
    return array(
      array(
        'caption'=>'Edit user details',
        'url'=>'user/edit_from_person/{person_id}'
      ),
      array(
        'caption'=>'Edit person details',
        'url'=>'person/edit_from_user/{person_id}'
      ),
      array(
        'caption'=>'Send forgotten password email',
        'url'=>'forgotten_password/send_from_user/{person_id}'
      )
    );
  }

  protected function password_fields($password = '', $password2 = '')
  {
    return '<li><label for="password">Password</label><input id="password" name="password" value="'.html::specialchars($password).'" />' .
        html::error_message($this->model->getError('password')) .
        '</li><li><label for="password">Repeat Password</label><input id="password2" name="password2" value="'.html::specialchars($password2).'" /></li>';
  }

  // Due to the way the Users gridview is displayed (ie driven off the person table)
  // there is no specific create function, as the edit function handles this when there
  // is no user record for the specified person id.

  /**
   * Subsiduary Action for user/edit page.
   * Displays a page allowing modification of an existing user or creation of a new user
   * driven by ther person id.
   */
  public function edit_from_person($id) {
    $allowedPersonIds = $this->getAllowedPersonIds();
    if (!is_null($id) && $allowedPersonIds!==true && !in_array($id, $allowedPersonIds)) {
      $this->access_denied();
      return;
    }
    $this->model = new User_Model(array('person_id' => $id, 'deleted'=>'f'));
    $websites = ORM::Factory('website')->in_allowed_websites()->find_all();
    if ( $this->model->loaded ) {
      $this->setView('user/user_edit', 'User', array('password_field' => ''));
      foreach ($websites as $website) {
        $users_website = ORM::factory('users_website', array('user_id' => $this->model->id, 'website_id' => $website->id));
        $this->model->users_websites[$website->id]=
            array(
              'id' => $website->id
              ,'name' => 'website_'.$website->id
              ,'title' => $website->title
              ,'value' => ($users_website->loaded ? $users_website->site_role_id : null)
              );
      }
    } else {
      // new user
      $login_config = Kohana::config('login');
      $person = ORM::factory('person', $id);
       if ($person->email_address == null)
          {
           $this->setError('Invocation error: missing email address', 'You cannot create user details for a person who has no email_address');
          }
      else
      {
        $this->setView('user/user_edit', 'User',
          array('password_field' => $this->password_fields($login_config['default_password'], $login_config['default_password'])));
        $this->template->content->model->person_id = $id;
        $this->template->content->model->username = $this->new_username($person);
        foreach ($websites as $website)
          $this->model->users_websites[$website->id]=
              array(
                'id' => $website->id
                ,'name' => 'website_'.$website->id
                ,'title' => $website->title
                ,'value' => null
                );
      }
    }
    $this->defineEditBreadcrumbs();
  }

  protected function new_username($person)
  {
    $minlen=5;
    $inc=1;
    if($person->first_name=='')
      $base_username = $person->surname;
    else
      $base_username = $person->first_name.'_'.$person->surname;
    $base_username = strtolower(preg_replace("/[^A-Za-z]/", "_", $base_username)); 
    if(strlen($base_username) < $minlen)
      $username = sprintf($base_username.'%0'.($minlen-strlen($base_username)).'d', $inc++);
    else {
      $inc++; // numbers bolted on start at 2 on purpose.
      $username = $base_username;
    }
    // check for uniqueness
    while(ORM::factory('user', array('username'=>$username))->loaded){
      if(strlen($base_username) < $minlen)
        $username = sprintf($base_username.'%0'.($minlen-strlen($base_username)).'d', $inc++);
      else
        $username = sprintf($base_username.'%d', $inc++);
    }
    return $username;
  }

  protected function show_submit_fail() {
    $page_error=$this->model->getError('general');
    if ($page_error) {
      $this->session->set_flash('flash_error', $page_error);
    }
    $this->setView('user/user_edit', 'User',
        array('password_field' => array_key_exists('password', $_POST) ? $this->password_fields($_POST['password'], $_POST['password2']) : ''));

    // copy the values of the websites into the users_websites array
    $websites = ORM::Factory('website')->in_allowed_websites()->find_all();
    foreach ($websites as $website) {
      if (isset($_POST['website_'.$website->id])) {
        $this->model->users_websites[$website->id]=
          array('id' => $website->id
            ,'name' => 'website_'.$website->id
            ,'title' => $website->title
            ,'value' => (is_numeric($_POST['website_'.$website->id]) ? $_POST['website_'.$website->id] : NULL)
          );
      }
    }
  }
  
  /**
   * If trying to edit an existing user record, ensure the user has rights to a website the logged in user can access.
   * Note that the /user/edit action is not really expected as the user is edited from the person ID, but this is here 
   * as a safety check in case the user tries to guess the url for user editing and the base class edit method kicks in.
   */
  public function record_authorised($id) {
    if ($this->auth->logged_in('CoreAdmin'))
      return true;
    elseif (!is_null($id) AND !is_null($this->auth_filter)) {
      $u = ORM::factory('user', $id);
      $allowedPersonIds = $this->getAllowedPersonIds();
      return $allowedPersonIds!==true && !in_array($u->person_id, $allowedPersonIds);
    }
    return true;
  }

  /**
   * Website admins and core admins area allowed view the users list.
   */
  protected function page_authorised ()
  {
    return $this->auth->logged_in('CoreAdmin') || $this->auth->has_any_website_access('admin');
  }
  
  /**
   * Return a list of the tabs to display for this controller's actions.
   */
  protected function getTabs($name) {
    return array(array(
      'controller' => 'user_identifier',
      'title' => 'Identifiers',
      'actions'=>array('edit')
    ), array(
      'controller' => 'user_identifier/index_from_person',
      'title' => 'Identifiers',
      'actions'=>array('edit_from_person')
    ));
  }

  /**
   * Retrieves a list of person_ids that the user is allowed to edit (i.e. the ones
   * which belong to websites they administer). Returns true if core admin.
   * return Bool|array
   */
  private function getAllowedPersonIds() {
    // cache this list in a property.
    if (!isset($this->allowedPersonIds)) {
      $websites = $this->get_allowed_website_id_list('admin');
      if (!is_null($websites)) {
        // If not core admin, then you can only edit a person if they have a role on one of your websites that you administer or
        // you created the user
        $this->allowedPersonIds = array();
        $list = $this->db
          ->select('people.id')
          ->from('people')
          ->join('users', 'users.person_id', 'people.id')
          ->join('users_websites', 'users_websites.user_id', 'users.id')
          ->where('users_websites.site_role_id IS NOT ', NULL)
          ->where('users.core_role_id IS ', NULL)
          ->where('people.deleted', 'false')
          ->in('users_websites.website_id', $websites)
          ->get();
        foreach ($list as $item) {
          $this->allowedPersonIds[] = $item->id;
        }
        // Also let you edit users that you created
        $list = $this->db
          ->select('people.id')
          ->from('people')
          ->join('users', 'users.person_id', 'people.id', 'LEFT')
          ->where('people.created_by_id', $_SESSION['auth_user']->id)
          ->where('users.core_role_id IS ', NULL)
          ->where('people.deleted', 'false')
          ->get();
        foreach ($list as $item) {
          $this->allowedPersonIds[] = $item->id;
        }
        // Remove duplicates
        $this->allowedPersonIds = array_unique($this->allowedPersonIds, SORT_NUMERIC);
      } else {
        // core admin so allow all
        return $this->allowedPersonIds = true;
      }
    }
    return $this->allowedPersonIds;
  }
  
}