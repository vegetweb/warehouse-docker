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
 * @package Services
 * @subpackage Data
 * @author	Indicia Team
 * @license	http://www.gnu.org/licenses/gpl.html GPL 3.0
 * @link 	http://code.google.com/p/indicia/
 */
 
/**
 * Class providing species_alerts web services.
 */
require_once(DOCROOT.'client_helpers/data_entry_helper.php');
class Species_alerts_Controller extends Data_Service_Base_Controller {
  /*
   * Web service function that gets called and then passes onto a function to store the species_alert in the database.
   */
  public function register() {
    try {
      $this->authenticate('write');
      self::string_validate_mandatory('email');
      self::string_validate_mandatory('surname');
      self::int_key_validate_mandatory('website_id');
      self::boolean_validate('alert_on_entry');
      self::boolean_validate('alert_on_verify');
      self::int_key_validate('location_id');
      if (!empty($_GET['user_id']))
        $userId = $_GET['user_id'];
      else {
        // User was not logged in when subscribing, so use their details to find or create a warehouse user id.
        $emailIdentifierObject = new stdClass();
        $emailIdentifierObject->type="email";      
        $emailIdentifierObject->identifier=$_GET["email"];
        $userIdentificationData['identifiers']=json_encode(array($emailIdentifierObject));
        //Also pass through these fields so if a new user is required then the system can fill in the database details
        $userIdentificationData['surname']=$_GET["surname"];
        $userIdentificationData['first_name']=$_GET["first_name"];      
        //Call existing user identifier code that will either fetch an existing user for that email, or create a new one.
        $userDetails=user_identifier::get_user_id($userIdentificationData, $_GET["website_id"]);
        if (!empty($userDetails['userId']))
          $userId = $userDetails['userId'];
        else {
          $userId = $userDetails['possibleMatches'][0]['user_id'];
        }
      }
      //Store the species alert for the user (which is either a new or existing user as determined by get_user_id)
      self::store_species_alert($userId);
      //Automatically register the user to receive email notifications if they have never had any settings at all
      try {
        $readAuth = data_entry_helper::get_read_auth(0-$userId, kohana::config('indicia.private_key'));
        $freqSettingsData = data_entry_helper::get_report_data(array(
          'dataSource'=>'library/user_email_notification_settings/user_email_notification_settings_inc_deleted',
          'readAuth'=>$readAuth,
          'extraParams'=>array('user_id' => $userId)
        ));
        if (empty($freqSettingsData))
          self::store_user_email_notification_setting($userId);
      } catch (exception $e) {
        kohana::log('debug', "Unable to register user ".$userId." for email notifications, perhaps that module is not installed?.");
      } 
    } 
    catch (Exception $e) {
      $this->handle_error($e);
    }
  }
  
  /*
   * Create the Species Alert record to submit and save it to the database
   * @todo Shouldn't the validation here be built into the model, and the results extracted
   * from a failed save?
   */
  private function store_species_alert($userId) {
    // load existing or create a new record
    if (!empty($_GET['id']))
      $alertRecordSubmissionObj = ORM::factory('species_alert', $_GET['id']);
    else
      $alertRecordSubmissionObj = ORM::factory('species_alert');
    //The user id can be either a new user or exsting user, this has already been sorted out by the get_user_id function, so 
    //by this point we don't care about whether the user is new or existing, we are just dealing with a user id given to us by that function.
    //No need to validate as the user_id comes from get_user_id
    $alertRecordSubmissionObj->user_id=$userId;   
    //Region to receive alerts for.
    $alertRecordSubmissionObj->location_id = empty($_GET['location_id']) ? null : $_GET['location_id'];
    //Already checked this has been filled in so don't need to do this again
    $alertRecordSubmissionObj->website_id=$_GET['website_id'];
    //At least one of this must be supplied to identifiy the taxon
    self::at_least_one_field_required(array('taxon_meaning_id','external_key','taxon_list_id'));
    $alertRecordSubmissionObj->taxon_meaning_id = empty($_GET['taxon_meaning_id']) ? null : $_GET['taxon_meaning_id'];
    $alertRecordSubmissionObj->external_key = empty($_GET['external_key']) ? null : $_GET['external_key'];
    $alertRecordSubmissionObj->taxon_list_id = empty($_GET['taxon_list_id']) ? null : $_GET['taxon_list_id'];
    
    //If boolean isn't supplied just assume as false
    $alertRecordSubmissionObj->alert_on_entry = empty($_GET['alert_on_entry']) ? 'f' : $_GET['alert_on_entry'];
    $alertRecordSubmissionObj->alert_on_verify = empty($_GET['alert_on_verify']) ? 'f' : $_GET['alert_on_verify'];
    //Fill in the Created/Updated data fields in the record row
    $alertRecordSubmissionObj->set_metadata($alertRecordSubmissionObj);
    $alertRecordSubmissionObj->save();
  }
  
  /*
   * Automatically register the user to receive notification emails when they register for species alerts
   */
  private function store_user_email_notification_setting($userId) {
    //Get configuration for which source types to add if possible
    try {
      $sourceTypes = kohana::config('species_alerts.register_for_notification_emails_source_types');
    } catch (exception $e) {
      $sourceTypes=array('T','C','V','A','S','VT','M');
    }
    if (empty($sourceTypes))
      $sourceTypes=array('T','C','V','A','S','VT','M');
    //Add a notification email setting for each configured source type
    foreach ($sourceTypes as $sourceType) {
      $notificationSettingSubmissionObj = ORM::factory('user_email_notification_setting');
      $notificationSettingSubmissionObj->user_id=$userId;
      $notificationSettingSubmissionObj->notification_source_type=$sourceType;
      //Species alerts default to hourly
      if ($sourceType==='S')
        $notificationSettingSubmissionObj->notification_frequency='IH';
      else
        $notificationSettingSubmissionObj->notification_frequency='D';
      $notificationSettingSubmissionObj->set_metadata($notificationSettingSubmissionObj);
      $notificationSettingSubmissionObj->save();
    }
  }
  
  /*
   * Check that a supplied table key is at least 1 and is a number. Key can be empty
   */
  private function int_key_validate($keyToValidate) {
    if (!empty($_GET[$keyToValidate]) && (!ctype_digit($_GET[$keyToValidate]) || intval($_GET[$keyToValidate])<1)) {
      throw new Exception($keyToValidate.' has not been supplied as an integer greater than 0.');
    }
  }
  
  /*
   * Check that a supplied table key is at least 1 and is a number. Key can't be empty
   */
  private function int_key_validate_mandatory($keyToValidate) {
    if (!array_key_exists($keyToValidate, $_GET) || empty($_GET[$keyToValidate]) || !ctype_digit($_GET[$keyToValidate]) || intval($_GET[$keyToValidate])<1) {
      throw new Exception($keyToValidate.' has not been supplied to the Species Alert service or it has not been supplied as an integer greater than 0.');
    }
  }
  
  /*
   * If a boolean is supplied, we make sure it is of the correct type (if it isn't supplied we don't need to throw an exception 
   * as the system will assume it is false)
   */
  private function boolean_validate($keyToValidate) {     
    if (array_key_exists($keyToValidate, $_GET) && (!isset($_GET[$keyToValidate]) || !in_array($_GET[$keyToValidate], array(1, 0)))) {
      throw new Exception($keyToValidate.' has been supplied to the Species Alert service and it has not been supplied as a boolean.');
    }
  }
  
  /*
   * Validate strings that are required
   */
  private function string_validate_mandatory($keyToValidate) {
    if (!array_key_exists($keyToValidate, $_GET) || empty($_GET[$keyToValidate])) {
      throw new Exception($keyToValidate.' has not been supplied to the Species Alert service or is missing a data value.');
    }
  }
  
  /*
   * Validate an "at least one of" scenerio where is at least one of a list of fields must be filled in
   */
  private function at_least_one_field_required($array) {
    $found = FALSE;
    foreach ($array as $item) {
      if (!empty($_GET[$item])) {
        return;
      }
    }
    throw new Exception('At least one of ' . implode(', ', $array) . ' must be supplied to the Species Alert service.');
  }
}
 
 
 